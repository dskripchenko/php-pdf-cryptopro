# Changelog

All notable changes to `dskripchenko/php-pdf-cryptopro` are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/);
versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed
- `SignMode::Content` failed on the real extension: `SignCades()` takes the
  detached flag as an integer, and a bool was refused under strict types.

### Added
- `docker/` — a container with CryptoPro CSP, phpcades (built from
  CryptoPro's sources) and the gost engine, and a smoke test that signs and
  validates a PDF in both modes. Verified: CSP 5.0 R4 (5.0.13820), phpcades
  8fe27f0, PHP 8.3, GOST R 34.10-2012 256-bit.

## [0.1.0] — 2026-10-16

### Added
- `CryptoProCadesSigner` — a php-pdf `CmsSigner` for a key held by
  CryptoPro CSP on the server, through CryptoPro's PHP extension:
  certificate by thumbprint or subject, current user or machine store, PIN,
  signing the hash (`SignHash`) or the content (`SignCades`), the chain
  embedded by the CSP.
- `CryptoProException` with the CryptoAPI code and plain explanations of
  the common failures.
- `Cades` — the interface to the CSP, with `PhpCades` on top of the
  extension, so the signer can run against a fake.
