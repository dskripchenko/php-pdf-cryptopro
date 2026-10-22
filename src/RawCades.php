<?php

declare(strict_types=1);

namespace Dskripchenko\PhpPdfCryptoPro;

/**
 * The CryptoPro CSP operation {@see CryptoProRawSigner} needs on top of
 * {@see Cades}: a bare signature value over a hash, with no CMS around it.
 * {@see PhpCades} does it through CryptoPro's PHP extension.
 *
 * A separate interface rather than a new {@see Cades} method, so that
 * existing implementations of Cades keep working.
 */
interface RawCades
{
    /**
     * The signature value over a hash, as the CSP returns it: CryptoAPI
     * gives GOST and RSA values little-endian, and the caller picks the
     * byte order that verifies.
     *
     * @param  object  $certificate  The certificate as {@see Cades} found it.
     * @param  string  $hashHex  The hash, hex.
     * @param  int  $hashAlgorithm  The CAdESCOM hash algorithm id (see {@see PhpCades::hashAlgorithm()}).
     * @return string The signature value, binary.
     */
    public function signRawHash(object $certificate, string $hashHex, int $hashAlgorithm, #[\SensitiveParameter] ?string $pin): string;
}
