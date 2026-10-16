<?php

declare(strict_types=1);

namespace Dskripchenko\PhpPdfCryptoPro\Tests;

use Dskripchenko\PhpPdfCryptoPro\CryptoProCadesSigner;
use Dskripchenko\PhpPdfCryptoPro\CryptoProException;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WithoutExtensionTest extends TestCase
{
    #[Test]
    #[RunInSeparateProcess]
    public function without_phpcades_the_signer_says_so(): void
    {
        $this->expectException(CryptoProException::class);
        $this->expectExceptionMessage('the phpcades extension is not loaded');
        CryptoProCadesSigner::fromThumbprint('00');
    }

    #[Test]
    public function an_unknown_code_keeps_the_original_message(): void
    {
        $e = CryptoProException::from(new \Exception('Something odd (0x80001234)'), 'signing the hash');

        self::assertSame(0x80001234, $e->cryptoApiCode);
        self::assertSame('CryptoPro CSP: signing the hash failed (Something odd (0x80001234))', $e->getMessage());
    }
}
