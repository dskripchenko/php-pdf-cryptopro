<?php

declare(strict_types=1);

// A stand-in for CryptoPro's phpcades extension, backed by the openssl
// extension, so PhpCades can be run without CryptoPro CSP. It mimics the
// calls PhpCades makes and records them in FakeCsp.

use Dskripchenko\PhpPdf\Pdf\Signature\Cms\CmsBuilder;
use Dskripchenko\PhpPdf\Pdf\Signature\Signer\OpenSslSigner;
use Dskripchenko\PhpPdfCryptoPro\Tests\Support\FakeCsp;
use Dskripchenko\PhpPdfCryptoPro\Tests\Support\TestPki;

if (class_exists('CPStore', false)) {
    return;
}

class CPStore
{
    public function Open(int $location, string $name, int $mode): void
    {
        FakeCsp::$calls[] = ['Open', $location, $name, $mode];
    }

    public function get_Certificates(): CPCertificates
    {
        return new CPCertificates([new CPCertificate]);
    }
}

class CPCertificates
{
    /** @param list<CPCertificate> $items */
    public function __construct(private array $items) {}

    public function Find(int $type, mixed $criteria, int $validOnly): CPCertificates
    {
        FakeCsp::$calls[] = ['Find', $type, $criteria];
        $match = static fn (CPCertificate $c): bool => match ($type) {
            0 => $criteria === TestPki::thumbprint(),
            1 => str_contains(implode(', ', (array) openssl_x509_parse(TestPki::get()['certPem'])['subject']), (string) $criteria),
            default => false,
        };

        return new CPCertificates(array_values(array_filter($this->items, $match)));
    }

    public function Count(): int
    {
        return count($this->items);
    }

    public function Item(int $index): CPCertificate
    {
        return $this->items[$index - 1];
    }
}

class CPCertificate
{
    public function PrivateKey(): CPPrivateKey
    {
        return new CPPrivateKey;
    }

    public function Export(int $encoding): string
    {
        FakeCsp::$calls[] = ['Export', $encoding];
        $pem = TestPki::get()['certPem'];

        return (string) preg_replace('/-----[A-Z ]+-----|\s+/', '', $pem);
    }
}

class CPSigner
{
    public ?string $pin = null;

    public int $options = 0;

    public function set_Certificate(object $certificate): void {}

    public function set_Options(int $options): void
    {
        $this->options = $options;
    }

    public function set_KeyPin(string $pin): void
    {
        $this->pin = $pin;
        FakeCsp::$givenPin = $pin;
    }
}

class CPPrivateKey
{
    /** Like the real CSP: a wrong PIN fails at once. */
    public function set_KeyPin(string $pin): void
    {
        FakeCsp::$givenPin = $pin;
        FakeCsp::checkPin();
    }
}

class CPRawSignature
{
    /**
     * An RSA PKCS#1 v1.5 value over the hash, hex — reversed, the way
     * CryptoAPI returns signature values.
     */
    public function SignHash(CPHashedData $hashed, CPCertificate $certificate): string
    {
        FakeCsp::$calls[] = ['RawSignHash', $hashed->algorithm, $hashed->hex];
        FakeCsp::checkPin();
        if (FakeCsp::$wrongKey) {
            return strtoupper(bin2hex(random_bytes(256)));
        }
        // DigestInfo for SHA-256 (the fake's key is RSA): sign the given hash, not data.
        $digestInfo = (string) hex2bin('3031300d060960864801650304020105000420').(string) hex2bin($hashed->hex);
        if (! openssl_private_encrypt($digestInfo, $value, TestPki::get()['keyPem'], OPENSSL_PKCS1_PADDING)) {
            throw new RuntimeException('openssl_private_encrypt failed');
        }

        return strtoupper(bin2hex(strrev($value)));
    }
}

class CPHashedData
{
    public int $algorithm = 0;

    public string $hex = '';

    public function set_Algorithm(int $algorithm): void
    {
        $this->algorithm = $algorithm;
    }

    public function SetHashValue(string $hex): void
    {
        $this->hex = $hex;
    }
}

class CPSignedData
{
    private int $encoding = 0;

    private string $content = '';

    public function set_ContentEncoding(int $encoding): void
    {
        $this->encoding = $encoding;
    }

    public function set_Content(string $content): void
    {
        $this->content = $content;
    }

    public function SignHash(CPHashedData $hashed, CPSigner $signer, int $type, int $encoding): string
    {
        FakeCsp::$calls[] = ['SignHash', $hashed->algorithm, $hashed->hex, $signer->options, $type, $encoding];
        FakeCsp::checkPin();
        $pki = TestPki::get();
        $key = new OpenSslSigner($pki['certPem'], $pki['keyPem']);
        $builder = new CmsBuilder($key->certificate(), [], $key->digestAlgorithm(), new DateTimeImmutable);
        $attributes = $builder->signedAttributes((string) hex2bin($hashed->hex));

        return base64_encode($builder->assemble($attributes, $key->sign($attributes)));
    }

    public function SignCades(CPSigner $signer, int $type, int $detached, int $encoding): string
    {
        FakeCsp::$calls[] = ['SignCades', $this->encoding, $signer->options, $type, $detached, $encoding];
        FakeCsp::checkPin();
        $content = $this->encoding === 1 ? (string) base64_decode($this->content, true) : $this->content;
        $pki = TestPki::get();
        $dir = sys_get_temp_dir().'/phpcades-fake-'.bin2hex(random_bytes(6));
        mkdir($dir, 0700, true);
        try {
            file_put_contents("$dir/in", $content);
            if (! openssl_cms_sign("$dir/in", "$dir/out", $pki['certPem'], $pki['keyPem'], [], OPENSSL_CMS_DETACHED | OPENSSL_CMS_BINARY, OPENSSL_ENCODING_DER)) {
                throw new RuntimeException('openssl_cms_sign failed');
            }

            return base64_encode((string) file_get_contents("$dir/out"));
        } finally {
            array_map('unlink', (array) glob("$dir/*"));
            rmdir($dir);
        }
    }
}
