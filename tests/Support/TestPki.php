<?php

declare(strict_types=1);

namespace Dskripchenko\PhpPdfCryptoPro\Tests\Support;

/** A throwaway root and signer, made with the openssl extension once per run. */
final class TestPki
{
    /** @var array{rootPem: string, certPem: string, keyPem: string}|null */
    private static ?array $made = null;

    /** @return array{rootPem: string, certPem: string, keyPem: string} */
    public static function get(): array
    {
        if (self::$made !== null) {
            return self::$made;
        }
        $config = ['digest_alg' => 'sha256', 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];

        $rootKey = openssl_pkey_new($config);
        $rootCsr = openssl_csr_new(['commonName' => 'Test Root'], $rootKey, $config) ?: throw new \RuntimeException('csr');
        $root = openssl_csr_sign($rootCsr, null, $rootKey, 30, $config + ['x509_extensions' => 'v3_ca'], 1) ?: throw new \RuntimeException('root');

        $key = openssl_pkey_new($config);
        $csr = openssl_csr_new(['commonName' => 'Иванов Иван', 'organizationName' => 'ООО Ромашка'], $key, $config) ?: throw new \RuntimeException('csr');
        $cert = openssl_csr_sign($csr, $root, $rootKey, 30, $config + ['x509_extensions' => 'usr_cert'], 2) ?: throw new \RuntimeException('cert');

        openssl_x509_export($root, $rootPem);
        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($key, $keyPem);

        return self::$made = ['rootPem' => $rootPem, 'certPem' => $certPem, 'keyPem' => $keyPem];
    }

    public static function thumbprint(): string
    {
        return strtoupper((string) openssl_x509_fingerprint(self::get()['certPem'], 'sha1'));
    }
}
