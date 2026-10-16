<?php

declare(strict_types=1);

// Signs a PDF with CryptoProCadesSigner through the real CSP in both modes
// and validates the result with php-pdf.
//
//   THUMB=<sha1> [PIN=…] [ROOT=root.cer] php smoke.php

require getenv('AUTOLOAD') ?: __DIR__.'/vendor/autoload.php';

use Dskripchenko\PhpPdf\Pdf\Document;
use Dskripchenko\PhpPdf\Pdf\Signature\Appearance\SignatureAppearance;
use Dskripchenko\PhpPdf\Pdf\Signature\PdfSigner;
use Dskripchenko\PhpPdf\Pdf\Signature\Validation\SignatureValidator;
use Dskripchenko\PhpPdf\Pdf\Signature\Validation\TrustStore;
use Dskripchenko\PhpPdf\Pdf\SignatureConfig;
use Dskripchenko\PhpPdfCryptoPro\CryptoProCadesSigner;
use Dskripchenko\PhpPdfCryptoPro\SignMode;

$thumb = (string) getenv('THUMB');
$pin = getenv('PIN') ?: null;
$root = getenv('ROOT') ?: null;
$out = getenv('OUT') ?: '.';

$doc = Document::new();
$doc->addPage();
$pdf = $doc->toBytes();
$failed = false;

foreach ([SignMode::Hash, SignMode::Content] as $mode) {
    try {
        $signer = CryptoProCadesSigner::fromThumbprint($thumb, pin: $pin, mode: $mode);
        $signed = PdfSigner::fromBytes($pdf)
            ->addField('signature', page: 0, x: 60, y: 60, width: 250, height: 75, appearance: SignatureAppearance::simple())
            ->sign(new SignatureConfig(signer: $signer, reason: 'smoke'));
        file_put_contents("$out/smoke-{$mode->name}.pdf", $signed);

        $trust = $root !== null ? TrustStore::fromPem(self_pem((string) file_get_contents($root))) : TrustStore::system();
        $s = SignatureValidator::fromBytes($signed)->validate($trust)->signatures[0];
        printf("%-7s %s | %s | valid=%s errors=%s warnings=%s\n", $mode->name,
            $signer->certificate()->subjectCommonName(), $signer->digestAlgorithm()->value,
            var_export($s->isValid(), true), json_encode($s->errors, JSON_UNESCAPED_UNICODE), json_encode($s->warnings, JSON_UNESCAPED_UNICODE));
        $failed = $failed || ! $s->isValid();
    } catch (Throwable $e) {
        printf("%-7s FAILED %s: %s\n", $mode->name, $e::class, $e->getMessage());
        $failed = true;
    }
}
exit($failed ? 1 : 0);

/** DER or PEM certificate → PEM. */
function self_pem(string $cert): string
{
    return str_contains($cert, '-----BEGIN')
        ? $cert
        : "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($cert), 64, "\n")."-----END CERTIFICATE-----\n";
}
