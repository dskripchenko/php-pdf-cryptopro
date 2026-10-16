<?php

declare(strict_types=1);

namespace Dskripchenko\PhpPdfCryptoPro;

/**
 * A CryptoPro CSP failure, with the CryptoAPI error code (`0x8…`) when the
 * extension gave one and a plain explanation of the common ones.
 */
final class CryptoProException extends \RuntimeException
{
    /** Common CryptoAPI / smart card codes and what to check. */
    private const KNOWN = [
        0x8010006B => 'wrong PIN for the key container',
        0x8010006C => 'the PIN is blocked: too many wrong attempts',
        0x8010006E => 'the operation was cancelled (a PIN dialog cannot be shown to a server process; pass the PIN)',
        0x80090016 => 'the key container does not exist or PHP has no rights to it',
        0x8009200B => 'the certificate has no private key linked to it',
        0x80092004 => 'the certificate was not found',
        0x800B010A => 'the certificate chain could not be built to a trusted root',
        0x800B0101 => 'the certificate has expired or is not yet valid',
        0x80090008 => 'the algorithm is not supported: is the CSP licence valid and does it cover GOST R 34.10-2012?',
    ];

    public function __construct(string $message, public readonly ?int $cryptoApiCode = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /** Wraps an error thrown by the extension, naming the operation that failed. */
    public static function from(\Throwable $e, string $operation): self
    {
        $raw = trim($e->getMessage());
        $code = preg_match('/0x([0-9A-Fa-f]{8})/', $raw, $m) === 1 ? (int) hexdec($m[1]) : null;
        $hint = $code !== null ? (self::KNOWN[$code] ?? null) : null;

        $message = "CryptoPro CSP: $operation failed";
        if ($hint !== null) {
            $message .= ": $hint";
        }
        if ($raw !== '') {
            $message .= " ($raw)";
        }

        return new self($message, $code, $e);
    }
}
