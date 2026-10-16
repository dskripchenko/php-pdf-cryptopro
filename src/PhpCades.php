<?php

declare(strict_types=1);

namespace Dskripchenko\PhpPdfCryptoPro;

use Dskripchenko\PhpPdf\Pdf\Signature\Signer\DigestAlgorithm;

/**
 * {@see Cades} through CryptoPro's PHP extension (`phpcades`, the
 * `CPStore` / `CPSigner` / `CPSignedData` classes of the CAdES SDK).
 *
 * Constants are read by name when the extension defines them and fall back
 * to the CAdESCOM values otherwise, since builds of the extension differ in
 * what they export.
 */
final class PhpCades implements Cades
{
    public function __construct()
    {
        if (! class_exists('CPStore')) {
            throw new CryptoProException('CryptoPro CSP: the phpcades extension is not loaded (no CPStore class)');
        }
    }

    /** The CAdESCOM hash algorithm id for a digest. */
    public static function hashAlgorithm(DigestAlgorithm $digest): int
    {
        return match ($digest) {
            DigestAlgorithm::Gost256 => self::constant('CADESCOM_HASH_ALGORITHM_CP_GOST_3411_2012_256', 101),
            DigestAlgorithm::Gost512 => self::constant('CADESCOM_HASH_ALGORITHM_CP_GOST_3411_2012_512', 102),
            DigestAlgorithm::Sha256 => self::constant('CADESCOM_HASH_ALGORITHM_SHA_256', 4),
            DigestAlgorithm::Sha384 => self::constant('CADESCOM_HASH_ALGORITHM_SHA_384', 5),
            DigestAlgorithm::Sha512 => self::constant('CADESCOM_HASH_ALGORITHM_SHA_512', 6),
        };
    }

    public function findByThumbprint(string $thumbprint, Store $store): ?object
    {
        $hex = strtoupper((string) preg_replace('/[^0-9A-Fa-f]/', '', $thumbprint));

        return $this->find(self::constant('CERTIFICATE_FIND_SHA1_HASH', 0), $hex, $store);
    }

    public function findBySubject(string $subject, Store $store): ?object
    {
        return $this->find(self::constant('CERTIFICATE_FIND_SUBJECT_NAME', 1), $subject, $store);
    }

    public function export(object $certificate): string
    {
        try {
            $base64 = (string) $certificate->Export(self::constant('ENCODE_BASE64', 0));
        } catch (\Throwable $e) {
            throw CryptoProException::from($e, 'exporting the certificate');
        }

        return self::decode($base64, 'the exported certificate');
    }

    public function signHash(object $certificate, string $hashHex, int $hashAlgorithm, ?string $pin, bool $wholeChain): string
    {
        try {
            $hashed = new \CPHashedData;
            $hashed->set_Algorithm($hashAlgorithm);
            $hashed->SetHashValue($hashHex);

            $base64 = (string) (new \CPSignedData)->SignHash(
                $hashed,
                $this->signer($certificate, $pin, $wholeChain),
                self::constant('CADES_BES', 1),
                self::constant('ENCODE_BASE64', 0),
            );
        } catch (\Throwable $e) {
            throw CryptoProException::from($e, 'signing the hash');
        }

        return self::decode($base64, 'the signature');
    }

    public function signContent(object $certificate, string $content, ?string $pin, bool $wholeChain): string
    {
        try {
            $data = new \CPSignedData;
            // Binary content goes in as base64, decoded by the CSP.
            $data->set_ContentEncoding(self::constant('BASE64_TO_BINARY', 1));
            $data->set_Content(base64_encode($content));

            $base64 = (string) $data->SignCades(
                $this->signer($certificate, $pin, $wholeChain),
                self::constant('CADES_BES', 1),
                1, // detached; the extension takes an integer, not a bool
                self::constant('ENCODE_BASE64', 0),
            );
        } catch (\Throwable $e) {
            throw CryptoProException::from($e, 'signing the document');
        }

        return self::decode($base64, 'the signature');
    }

    private function find(int $by, string $value, Store $store): ?object
    {
        try {
            $cpStore = new \CPStore;
            $cpStore->Open(
                $store === Store::LocalMachine ? self::constant('LOCAL_MACHINE_STORE', 1) : self::constant('CURRENT_USER_STORE', 2),
                'My',
                self::constant('STORE_OPEN_READ_ONLY', 0),
            );
            $found = $cpStore->get_Certificates()->Find($by, $value, 0);

            return $found->Count() > 0 ? $found->Item(1) : null;
        } catch (\Throwable $e) {
            throw CryptoProException::from($e, 'searching the certificate store');
        }
    }

    private function signer(object $certificate, ?string $pin, bool $wholeChain): \CPSigner
    {
        $signer = new \CPSigner;
        $signer->set_Certificate($certificate);
        $signer->set_Options($wholeChain
            ? self::constant('CERTIFICATE_INCLUDE_WHOLE_CHAIN', 1)
            : self::constant('CERTIFICATE_INCLUDE_END_ENTITY_ONLY', 2));
        if ($pin !== null) {
            $signer->set_KeyPin($pin);
        }

        return $signer;
    }

    private static function constant(string $name, int $fallback): int
    {
        return defined($name) ? (int) constant($name) : $fallback;
    }

    private static function decode(string $base64, string $what): string
    {
        $binary = base64_decode((string) preg_replace('/\s+/', '', $base64), true);
        if ($binary === false || $binary === '') {
            throw new CryptoProException("CryptoPro CSP returned $what not in base64");
        }

        return $binary;
    }
}
