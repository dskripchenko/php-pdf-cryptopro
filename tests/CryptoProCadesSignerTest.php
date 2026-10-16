<?php

declare(strict_types=1);

namespace Dskripchenko\PhpPdfCryptoPro\Tests;

use Dskripchenko\PhpPdf\Pdf\Document;
use Dskripchenko\PhpPdf\Pdf\Signature\Appearance\SignatureAppearance;
use Dskripchenko\PhpPdf\Pdf\Signature\Appearance\TextBlock;
use Dskripchenko\PhpPdf\Pdf\Signature\PdfSigner;
use Dskripchenko\PhpPdf\Pdf\Signature\Signer\DigestAlgorithm;
use Dskripchenko\PhpPdf\Pdf\Signature\Validation\SignatureValidator;
use Dskripchenko\PhpPdf\Pdf\Signature\Validation\TrustStore;
use Dskripchenko\PhpPdf\Pdf\SignatureConfig;
use Dskripchenko\PhpPdfCryptoPro\CryptoProCadesSigner;
use Dskripchenko\PhpPdfCryptoPro\CryptoProException;
use Dskripchenko\PhpPdfCryptoPro\PhpCades;
use Dskripchenko\PhpPdfCryptoPro\SignMode;
use Dskripchenko\PhpPdfCryptoPro\Store;
use Dskripchenko\PhpPdfCryptoPro\Tests\Support\FakeCsp;
use Dskripchenko\PhpPdfCryptoPro\Tests\Support\TestPki;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The signer driven through PhpCades against a fake of the phpcades
 * extension (tests/Support/fake-phpcades.php).
 */
final class CryptoProCadesSignerTest extends TestCase
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

    private static function assertValid(string $pdf): void
    {
        $report = SignatureValidator::fromBytes($pdf)->validate(TrustStore::fromPem(TestPki::get()['rootPem']));
        self::assertTrue($report->allValid(), implode('; ', $report->signatures[0]->errors ?? []));
    }

    #[Test]
    public function signs_a_hash_in_one_call(): void
    {
        $signer = CryptoProCadesSigner::fromThumbprint(TestPki::thumbprint());
        $pdf = PdfSigner::fromBytes(self::pdf())->sign(new SignatureConfig(signer: $signer));

        self::assertValid($pdf);
        $sign = array_values(array_filter(FakeCsp::$calls, static fn (array $c): bool => $c[0] === 'SignHash'));
        self::assertCount(1, $sign);
        [, $algorithm, $hex, $options, $type] = $sign[0];
        self::assertSame(4, $algorithm, 'SHA-256 for an RSA key');
        self::assertMatchesRegularExpression('/^[0-9A-F]{64}$/', $hex);
        self::assertSame(1, $options, 'The whole chain by default');
        self::assertSame(1, $type, 'CAdES-BES');
    }

    #[Test]
    public function signs_the_content_when_asked(): void
    {
        $signer = CryptoProCadesSigner::fromThumbprint(TestPki::thumbprint(), mode: SignMode::Content, wholeChain: false);
        $pdf = PdfSigner::fromBytes(self::pdf())->sign(new SignatureConfig(signer: $signer));

        self::assertValid($pdf);
        $sign = array_values(array_filter(FakeCsp::$calls, static fn (array $c): bool => $c[0] === 'SignCades'));
        self::assertCount(1, $sign);
        [, $encoding, $options, , $detached] = $sign[0];
        self::assertSame(1, $encoding, 'Binary content goes in as base64');
        self::assertSame(2, $options, 'Only the signer certificate');
        self::assertTrue($detached);
    }

    #[Test]
    public function a_new_document_is_signed_with_a_stamp_from_the_csp_certificate(): void
    {
        $doc = Document::new();
        $doc->addPage()->addSignatureField('sig', 72, 600, 250, 60, new SignatureAppearance([new TextBlock('Serial {serial}')]));
        $doc->sign(new SignatureConfig(signer: CryptoProCadesSigner::fromThumbprint(TestPki::thumbprint())));
        $pdf = $doc->toBytes();

        self::assertValid($pdf);
        self::assertSame(1, preg_match('@/Subtype\s*/Widget@', $pdf));
        self::assertContains('Export', array_column(FakeCsp::$calls, 0), 'The certificate for the stamp comes from the CSP');
    }

    #[Test]
    public function the_thumbprint_may_have_spaces_and_lower_case(): void
    {
        $spaced = strtolower(implode(' ', str_split(TestPki::thumbprint(), 2)));
        $signer = CryptoProCadesSigner::fromThumbprint($spaced);

        self::assertSame(TestPki::get()['certPem'], self::pem($signer->certificate()->der));
        self::assertSame(['Find', 0, TestPki::thumbprint()], FakeCsp::$calls[1]);
    }

    #[Test]
    public function finds_by_subject_in_the_machine_store(): void
    {
        $signer = CryptoProCadesSigner::fromSubject('Ромашка', store: Store::LocalMachine);

        self::assertSame(['Open', 1, 'My', 0], FakeCsp::$calls[0]);
        self::assertSame('Иванов Иван', $signer->certificate()->subjectCommonName());
    }

    #[Test]
    public function a_missing_certificate_says_where_it_looked(): void
    {
        $this->expectException(CryptoProException::class);
        $this->expectExceptionMessage('no certificate with thumbprint 00 11 in the current user store');
        CryptoProCadesSigner::fromThumbprint('00 11');
    }

    #[Test]
    public function the_pin_reaches_the_container(): void
    {
        FakeCsp::$pin = '1234';
        $pdf = PdfSigner::fromBytes(self::pdf())->sign(new SignatureConfig(
            signer: CryptoProCadesSigner::fromThumbprint(TestPki::thumbprint(), pin: '1234'),
        ));

        self::assertValid($pdf);
    }

    #[Test]
    public function a_wrong_pin_is_explained(): void
    {
        FakeCsp::$pin = '1234';
        try {
            PdfSigner::fromBytes(self::pdf())->sign(new SignatureConfig(
                signer: CryptoProCadesSigner::fromThumbprint(TestPki::thumbprint(), pin: '0000'),
            ));
            self::fail('Expected a CryptoProException');
        } catch (CryptoProException $e) {
            self::assertSame(0x8010006B, $e->cryptoApiCode);
            self::assertStringContainsString('signing the hash failed: wrong PIN', $e->getMessage());
            self::assertStringContainsString('(Ошибка при подписании. (0x8010006B))', $e->getMessage());
        }
    }

    #[Test]
    public function the_digest_follows_the_key_or_is_given(): void
    {
        self::assertSame(DigestAlgorithm::Sha256, CryptoProCadesSigner::fromThumbprint(TestPki::thumbprint())->digestAlgorithm());
        self::assertSame(DigestAlgorithm::Sha512, CryptoProCadesSigner::fromThumbprint(TestPki::thumbprint(), digest: DigestAlgorithm::Sha512)->digestAlgorithm());
    }

    #[Test]
    public function gost_digests_map_to_the_cadescom_ids(): void
    {
        self::assertSame(101, PhpCades::hashAlgorithm(DigestAlgorithm::Gost256));
        self::assertSame(102, PhpCades::hashAlgorithm(DigestAlgorithm::Gost512));
    }

    private static function pem(string $der): string
    {
        return "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END CERTIFICATE-----\n";
    }
}
