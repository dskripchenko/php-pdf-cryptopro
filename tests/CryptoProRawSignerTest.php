<?php

declare(strict_types=1);

namespace Dskripchenko\PhpPdfCryptoPro\Tests;

use Dskripchenko\PhpPdf\Pdf\Document;
use Dskripchenko\PhpPdf\Pdf\Signature\PdfSigner;
use Dskripchenko\PhpPdf\Pdf\Signature\Signer\Certificate;
use Dskripchenko\PhpPdf\Pdf\Signature\Validation\SignatureValidator;
use Dskripchenko\PhpPdf\Pdf\Signature\Validation\TrustStore;
use Dskripchenko\PhpPdf\Pdf\SignatureConfig;
use Dskripchenko\PhpPdfCryptoPro\CryptoProException;
use Dskripchenko\PhpPdfCryptoPro\CryptoProRawSigner;
use Dskripchenko\PhpPdfCryptoPro\Store;
use Dskripchenko\PhpPdfCryptoPro\Tests\Support\FakeCsp;
use Dskripchenko\PhpPdfCryptoPro\Tests\Support\TestPki;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * CryptoProRawSigner through PhpCades against the fake extension: the CSP
 * signs a hash, php-pdf builds the CMS.
 */
final class CryptoProRawSignerTest extends TestCase
{
    protected function setUp(): void
    {
        FakeCsp::load();
    }

    private static function pdf(): string
    {
        $doc = Document::new();
        $doc->addPage();

        return $doc->toBytes();
    }

    /** The CMS of the first signature, DER. */
    private static function cms(string $pdf): string
    {
        preg_match('/\/Contents\s*<([0-9A-Fa-f]+)>/', $pdf, $m);

        return (string) hex2bin($m[1]);
    }

    #[Test]
    public function signs_a_pdf_that_validates(): void
    {
        $signer = CryptoProRawSigner::fromThumbprint(TestPki::thumbprint());
        $pdf = PdfSigner::fromBytes(self::pdf())->sign(new SignatureConfig(signer: $signer));

        $report = SignatureValidator::fromBytes($pdf)->validate(TrustStore::fromPem(TestPki::get()['rootPem']));
        self::assertTrue($report->allValid(), implode('; ', $report->signatures[0]->errors));
        self::assertSame('ETSI.CAdES.detached', $report->signatures[0]->subFilter);

        $sign = array_values(array_filter(FakeCsp::$calls, static fn (array $c): bool => $c[0] === 'RawSignHash'));
        self::assertCount(1, $sign);
        self::assertSame(4, $sign[0][1], 'SHA-256 for an RSA key');
        self::assertMatchesRegularExpression('/^[0-9A-F]{64}$/', $sign[0][2]);
    }

    #[Test]
    public function the_cms_has_no_signing_time(): void
    {
        $signer = CryptoProRawSigner::fromThumbprint(TestPki::thumbprint());
        $cms = self::cms(PdfSigner::fromBytes(self::pdf())->sign(new SignatureConfig(signer: $signer)));

        // id-signingTime 1.2.840.113549.1.9.5 and id-aa-signingCertificateV2 1.2.840.113549.1.9.16.2.47, DER.
        self::assertStringNotContainsString(hex2bin('06092a864886f70d010905'), $cms, 'PAdES: the time is /M, not a CMS attribute');
        self::assertStringContainsString(hex2bin('060b2a864886f70d010910022f'), $cms);
    }

    #[Test]
    public function passes_the_pin_to_the_key(): void
    {
        FakeCsp::$pin = '1234';
        $signer = CryptoProRawSigner::fromThumbprint(TestPki::thumbprint(), pin: '1234');
        PdfSigner::fromBytes(self::pdf())->sign(new SignatureConfig(signer: $signer));

        self::assertSame('1234', FakeCsp::$givenPin);
    }

    #[Test]
    public function a_wrong_pin_is_a_cryptopro_exception(): void
    {
        FakeCsp::$pin = '1234';
        $signer = CryptoProRawSigner::fromThumbprint(TestPki::thumbprint(), pin: '0000');

        try {
            PdfSigner::fromBytes(self::pdf())->sign(new SignatureConfig(signer: $signer));
            self::fail('No exception');
        } catch (CryptoProException $e) {
            self::assertSame(0x8010006B, $e->cryptoApiCode);
            self::assertStringContainsString('wrong PIN', $e->getMessage());
        }
    }

    #[Test]
    public function a_value_that_does_not_verify_is_refused(): void
    {
        FakeCsp::$wrongKey = true;
        $signer = CryptoProRawSigner::fromThumbprint(TestPki::thumbprint());

        $this->expectException(CryptoProException::class);
        $this->expectExceptionMessage('does not verify against the certificate');
        PdfSigner::fromBytes(self::pdf())->sign(new SignatureConfig(signer: $signer));
    }

    #[Test]
    public function embeds_the_chain_it_is_given(): void
    {
        $root = Certificate::fromPem(TestPki::get()['rootPem']);
        $signer = CryptoProRawSigner::fromThumbprint(TestPki::thumbprint(), chain: [$root]);
        $cms = self::cms(PdfSigner::fromBytes(self::pdf())->sign(new SignatureConfig(signer: $signer)));

        self::assertStringContainsString($root->der, $cms);
    }

    #[Test]
    public function finds_by_subject_in_the_machine_store(): void
    {
        $signer = CryptoProRawSigner::fromSubject('Ромашка', store: Store::LocalMachine);

        self::assertSame('Иванов Иван', $signer->certificate()->subjectCommonName());
        self::assertSame(['Open', 1, 'My', 0], FakeCsp::$calls[0]);
    }

    #[Test]
    public function a_missing_certificate_is_named(): void
    {
        $this->expectException(CryptoProException::class);
        $this->expectExceptionMessage('no certificate with thumbprint 00FF in the current user store');
        CryptoProRawSigner::fromThumbprint('00FF');
    }
}
