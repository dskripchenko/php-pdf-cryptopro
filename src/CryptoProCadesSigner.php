<?php

declare(strict_types=1);

namespace Dskripchenko\PhpPdfCryptoPro;

use Dskripchenko\PhpPdf\Pdf\Signature\Signer\Certificate;
use Dskripchenko\PhpPdf\Pdf\Signature\Signer\CmsSigner;
use Dskripchenko\PhpPdf\Pdf\Signature\Signer\DigestAlgorithm;

/**
 * Signs PDFs with a key held by CryptoPro CSP on the server — a token, the
 * registry, HDIMAGE — through CryptoPro's PHP extension. The key never
 * leaves the CSP.
 *
 * ```php
 * $signer = CryptoProCadesSigner::fromThumbprint($thumbprint, pin: $pin);
 * $pdf = PdfSigner::fromBytes($pdf)->sign(new SignatureConfig(signer: $signer));
 * ```
 */
final class CryptoProCadesSigner implements CmsSigner
{
    private ?Certificate $certificate = null;

    /**
     * @param  object  $handle  The certificate as {@see Cades} found it.
     * @param  list<Certificate>  $chain  Certificates to embed when the CSP does not.
     */
    public function __construct(
        private readonly Cades $cades,
        private readonly object $handle,
        #[\SensitiveParameter] private readonly ?string $pin = null,
        private readonly SignMode $mode = SignMode::Hash,
        private readonly bool $wholeChain = true,
        private readonly array $chain = [],
        private readonly ?DigestAlgorithm $digest = null,
    ) {}

    /**
     * The certificate with this SHA-1 thumbprint — as `certmgr -list` or
     * the certificate's properties show it.
     *
     * @param  ?string  $pin  The key container's PIN; null when it has none or it is cached.
     * @param  bool  $wholeChain  Ask the CSP to embed the chain up to the root (it must be able to build it).
     * @param  list<Certificate>  $chain  Intermediate certificates to embed when the CSP does not.
     * @param  ?Cades  $cades  Defaults to the phpcades extension.
     */
    public static function fromThumbprint(
        string $thumbprint,
        #[\SensitiveParameter] ?string $pin = null,
        Store $store = Store::CurrentUser,
        SignMode $mode = SignMode::Hash,
        bool $wholeChain = true,
        array $chain = [],
        ?DigestAlgorithm $digest = null,
        ?Cades $cades = null,
    ): self {
        $cades ??= new PhpCades;
        $handle = $cades->findByThumbprint($thumbprint, $store)
            ?? throw new CryptoProException(sprintf('CryptoPro CSP: no certificate with thumbprint %s in the %s store', $thumbprint, self::storeName($store)));

        return new self($cades, $handle, $pin, $mode, $wholeChain, $chain, $digest);
    }

    /**
     * The first certificate whose subject contains `$subject` — handy on a
     * test bench. A subject may also match a renewed or another certificate;
     * in production prefer the exact thumbprint.
     *
     * @param  list<Certificate>  $chain
     */
    public static function fromSubject(
        string $subject,
        #[\SensitiveParameter] ?string $pin = null,
        Store $store = Store::CurrentUser,
        SignMode $mode = SignMode::Hash,
        bool $wholeChain = true,
        array $chain = [],
        ?DigestAlgorithm $digest = null,
        ?Cades $cades = null,
    ): self {
        $cades ??= new PhpCades;
        $handle = $cades->findBySubject($subject, $store)
            ?? throw new CryptoProException(sprintf('CryptoPro CSP: no certificate with "%s" in the subject in the %s store', $subject, self::storeName($store)));

        return new self($cades, $handle, $pin, $mode, $wholeChain, $chain, $digest);
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

    public function signCms(string $content, string $digest): string
    {
        return $this->mode === SignMode::Hash
            ? $this->cades->signHash($this->handle, strtoupper(bin2hex($digest)), PhpCades::hashAlgorithm($this->digestAlgorithm()), $this->pin, $this->wholeChain)
            : $this->cades->signContent($this->handle, $content, $this->pin, $this->wholeChain);
    }

    private static function storeName(Store $store): string
    {
        return $store === Store::LocalMachine ? 'local machine' : 'current user';
    }
}
