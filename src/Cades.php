<?php

declare(strict_types=1);

namespace Dskripchenko\PhpPdfCryptoPro;

/**
 * The few CryptoPro CSP operations the signer needs. {@see PhpCades} does
 * them through CryptoPro's PHP extension; tests put a fake in its place.
 *
 * Certificates are handed around as opaque handles: whatever the
 * implementation needs to sign with the certificate later.
 */
interface Cades
{
    /**
     * The certificate with this SHA-1 thumbprint (hex, any case, spaces
     * allowed), or null when the store has none.
     */
    public function findByThumbprint(string $thumbprint, Store $store): ?object;

    /** The first certificate whose subject contains `$subject`, or null. */
    public function findBySubject(string $subject, Store $store): ?object;

    /** The certificate itself, DER. */
    public function export(object $certificate): string;

    /**
     * A CAdES-BES detached CMS over a hash.
     *
     * @param  string  $hashHex  The hash, hex.
     * @param  int  $hashAlgorithm  The CAdESCOM hash algorithm id (see {@see PhpCades::hashAlgorithm()}).
     * @return string The CMS, DER or BER.
     */
    public function signHash(object $certificate, string $hashHex, int $hashAlgorithm, ?string $pin, bool $wholeChain): string;

    /**
     * A CAdES-BES detached CMS over the bytes.
     *
     * @return string The CMS, DER or BER.
     */
    public function signContent(object $certificate, string $content, ?string $pin, bool $wholeChain): string;
}
