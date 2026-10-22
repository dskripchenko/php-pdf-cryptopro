<?php

declare(strict_types=1);

namespace Dskripchenko\PhpPdfCryptoPro;

use Dskripchenko\PhpPdf\Pdf\Signature\Signer\Certificate;
use Dskripchenko\PhpPdf\Pdf\Signature\Signer\DigestAlgorithm;
use Dskripchenko\PhpPdf\Pdf\Signature\Signer\KeyType;
use Dskripchenko\PhpPdf\Pdf\Signature\Signer\Signer;

/**
 * Signs PDFs with a key held by CryptoPro CSP, where php-pdf builds the
 * CMS itself and the CSP only signs a hash (CAdESCOM `RawSignature`).
 *
 * The difference from {@see CryptoProCadesSigner}, whose CMS the CSP
 * builds: the CSP puts a `signing-time` attribute into every CMS it makes,
 * and the PAdES baseline profile (ETSI EN 319 142-1) wants none — the time
 * is the signature dictionary's /M. Signatures made here carry no
 * `signing-time`, so strict PAdES validators accept them. The chain is
 * not built by the CSP either: pass the intermediate certificates as
 * `$chain`.
 *
 * GOST R 34.10-2012 and RSA keys; ECDSA keys sign with CryptoProCadesSigner.
 *
 * ```php
 * $signer = CryptoProRawSigner::fromThumbprint($thumbprint, pin: $pin, chain: $intermediates);
 * $pdf = PdfSigner::fromBytes($pdf)->sign(new SignatureConfig(signer: $signer));
 * ```
 */
final class CryptoProRawSigner implements Signer
{
    private ?Certificate $certificate = null;

    /**
     * @param  object  $handle  The certificate as {@see Cades} found it.
     * @param  list<Certificate>  $chain  Intermediate certificates to embed.
     */
    public function __construct(
        private readonly Cades&RawCades $cades,
        private readonly object $handle,
        #[\SensitiveParameter] private readonly ?string $pin = null,
        private readonly array $chain = [],
        private readonly ?DigestAlgorithm $digest = null,
    ) {}

    /**
     * The certificate with this SHA-1 thumbprint — as `certmgr -list` or
     * the certificate's properties show it.
     *
     * @param  ?string  $pin  The key container's PIN; null when it has none or it is cached.
     * @param  list<Certificate>  $chain  Intermediate certificates to embed, up to (not including) the root.
     * @param  (Cades&RawCades)|null  $cades  Defaults to the phpcades extension.
     */
    public static function fromThumbprint(
        string $thumbprint,
        #[\SensitiveParameter] ?string $pin = null,
        Store $store = Store::CurrentUser,
        array $chain = [],
        ?DigestAlgorithm $digest = null,
        (Cades&RawCades)|null $cades = null,
    ): self {
        $cades ??= new PhpCades;
        $handle = $cades->findByThumbprint($thumbprint, $store)
            ?? throw new CryptoProException(sprintf('CryptoPro CSP: no certificate with thumbprint %s in the %s store', $thumbprint, self::storeName($store)));

        return new self($cades, $handle, $pin, $chain, $digest);
    }

    /**
     * The first certificate whose subject contains `$subject` — handy on a
     * test bench; in production prefer the exact thumbprint.
     *
     * @param  list<Certificate>  $chain
     * @param  (Cades&RawCades)|null  $cades
     */
    public static function fromSubject(
        string $subject,
        #[\SensitiveParameter] ?string $pin = null,
        Store $store = Store::CurrentUser,
        array $chain = [],
        ?DigestAlgorithm $digest = null,
        (Cades&RawCades)|null $cades = null,
    ): self {
        $cades ??= new PhpCades;
        $handle = $cades->findBySubject($subject, $store)
            ?? throw new CryptoProException(sprintf('CryptoPro CSP: no certificate with "%s" in the subject in the %s store', $subject, self::storeName($store)));

        return new self($cades, $handle, $pin, $chain, $digest);
    }

    public function certificate(): Certificate
    {
        return $this->certificate ??= Certificate::fromDer($this->cades->export($this->handle));
    }

    public function chain(): array
    {
        return $this->chain;
    }

    public function digestAlgorithm(): DigestAlgorithm
    {
        return $this->digest ?? DigestAlgorithm::defaultFor($this->certificate());
    }

    /**
     * Hashes `$data` (the CMS signed attributes), has the CSP sign the hash
     * and returns the value in the byte order CMS wants — the one that
     * verifies against the certificate.
     */
    public function sign(string $data): string
    {
        $certificate = $this->certificate();
        if ($certificate->keyType() === KeyType::Ec) {
            throw new CryptoProException('CryptoPro CSP: an ECDSA key cannot sign through CryptoProRawSigner; use CryptoProCadesSigner');
        }
        $digest = $this->digestAlgorithm();
        $raw = $this->cades->signRawHash($this->handle, strtoupper(bin2hex($digest->digest($data))), PhpCades::hashAlgorithm($digest), $this->pin);

        foreach (array_unique([$raw, strrev($raw)]) as $value) {
            if (openssl_verify($data, $value, $certificate->publicKey(), $digest->opensslName()) === 1) {
                return $value;
            }
        }

        throw new CryptoProException('CryptoPro CSP: the signature the CSP returned does not verify against the certificate — is the private key linked to this certificate?');
    }

    private static function storeName(Store $store): string
    {
        return $store === Store::LocalMachine ? 'local machine' : 'current user';
    }
}
