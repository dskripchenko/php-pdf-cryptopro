# dskripchenko/php-pdf-cryptopro

Sign PDFs with [`dskripchenko/php-pdf`](https://github.com/dskripchenko/php-pdf)
using a key held by **CryptoPro CSP** on the server — a token, the
registry, HDIMAGE — through CryptoPro's PHP extension. The key never leaves
the CSP; the signature is PAdES with GOST R 34.10-2012 (or RSA/ECDSA if the
CSP holds such a key).

> 🌐 **English** · [Deutsch](docs/de/README.md) · [Русский](docs/ru/README.md) · [中文](docs/zh/README.md)

[![Tests](https://img.shields.io/github/actions/workflow/status/dskripchenko/php-pdf-cryptopro/tests.yml?branch=main&label=tests&logo=github)](https://github.com/dskripchenko/php-pdf-cryptopro/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/dskripchenko/php-pdf-cryptopro.svg)](https://packagist.org/packages/dskripchenko/php-pdf-cryptopro)
[![License: MIT](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.2-blue.svg)](composer.json)

---

## Usage

```php
use Dskripchenko\PhpPdf\Pdf\Signature\PdfSigner;
use Dskripchenko\PhpPdf\Pdf\SignatureConfig;
use Dskripchenko\PhpPdfCryptoPro\CryptoProCadesSigner;

$signer = CryptoProCadesSigner::fromThumbprint('3A 5F … 9C', pin: $pin);

$signed = PdfSigner::fromBytes($pdf)
    ->addField('signature', page: 0, x: 60, y: 450, width: 250, height: 75, appearance: $stamp)
    ->sign(new SignatureConfig(signer: $signer, timestampAuthority: $tsa));
```

The signer works wherever php-pdf takes one: `PdfSigner::sign()` for an
existing PDF, `Pdf\Document::sign()` and a laid-out document's `signature:`.
The certificate comes from the CSP store, so the stamp shows its owner,
serial number and validity period; the digest follows the key (Streebog-256
or -512 for GOST).

php-pdf checks the signature the CSP returns before embedding it: it covers
exactly this document, it was made with this certificate, it verifies.

## Options

| Argument | Default | Meaning |
|---|---|---|
| `pin` | `null` | The key container's PIN. A server process cannot show the PIN dialog, so pass it unless the container has none |
| `store` | `Store::CurrentUser` | `Store::LocalMachine` for the machine store |
| `mode` | `SignMode::Hash` | `Hash` sends only the document's hash to the CSP (`SignHash`); `Content` sends the bytes (`SignCades`) — for builds of the extension without `SignHash` |
| `wholeChain` | `true` | The CSP embeds the chain up to the root; `false` — the signer certificate only |
| `chain` | `[]` | Intermediate certificates (`Certificate` objects) to embed when the CSP does not |
| `digest` | by the key | Override the digest algorithm |

`fromSubject('ООО Ромашка')` finds a certificate by a part of its subject —
handy on a test bench; in production use the exact thumbprint
(`certmgr -list` shows it as "SHA1 Thumbprint").

Failures are `CryptoProException` with the CryptoAPI code
(`$e->cryptoApiCode`) and a plain explanation of the common ones: a wrong
or blocked PIN, a missing container, no rights to it, a certificate
without a private key, an untrusted chain, an unsupported algorithm.

## Requirements

On the server where PHP signs:

- **PHP 8.2+** with `dskripchenko/php-pdf` 1.18 or later (Composer installs it).
- **CryptoPro CSP 5** with a licence that allows server-side signing, and
  the key container installed for the user PHP runs as (or in the machine
  store with access rights).
- **CryptoPro's PHP extension** (`phpcades`) built for your PHP version and
  loaded: the sources and build steps are at
  [CryptoPro/phpcades](https://github.com/CryptoPro/phpcades); `docker/Dockerfile`
  here does it on Ubuntu 24.04.
- **The OpenSSL gost engine** for GOST keys: php-pdf computes the Streebog
  digest of the document and verifies the returned signature itself. The
  php-pdf repository has a working recipe in `docker/gost/`.

## Trying it on a real CSP

`docker/` builds a container with CryptoPro CSP, phpcades and the gost
engine and signs a PDF with a test GOST certificate in both modes — see
[docker/README.md](docker/README.md).

## Testing without CryptoPro

`CryptoProCadesSigner` talks to the CSP through the small `Cades`
interface; `PhpCades` is its implementation on top of the extension. Pass
your own `Cades` as `cades:` to run the code without CryptoPro — this
package's tests do so with a fake of the extension backed by OpenSSL.

## The php-pdf family

| Package | What it adds |
|---|---|
| [php-pdf](https://github.com/dskripchenko/php-pdf) | The core: HTML with CSS or a builder to PDF, read, merge, fill forms, sign and validate. |
| [laravel-php-pdf](https://github.com/dskripchenko/laravel-php-pdf) | Laravel bridge: a Blade view straight to PDF, `response()->pdf()`, signing from config. |
| [php-pdf-fonts-liberation](https://github.com/dskripchenko/php-pdf-fonts-liberation) | Liberation Sans, Serif and Mono, metric-compatible with Arial, Times New Roman and Courier New (OFL, kept out of the MIT core). |
| **php-pdf-cryptopro** (this package) | Signs with a key in CryptoPro CSP on the server: GOST R 34.10-2012, Russian qualified signatures. |
| [php-pdf-pkcs11](https://github.com/dskripchenko/php-pdf-pkcs11) | Signs with a key on an HSM, a smart card or a USB token, through PKCS#11. |
| [php-pdf-cloud-kms](https://github.com/dskripchenko/php-pdf-cloud-kms) | Signs with a key in AWS KMS, Google Cloud KMS or Azure Key Vault. |

## License

MIT — see [LICENSE](LICENSE). CryptoPro CSP and its PHP extension are
proprietary products of CryptoPro LLC and are licensed separately.
