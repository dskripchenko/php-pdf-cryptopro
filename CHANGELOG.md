# Changelog

All notable changes to `dskripchenko/php-pdf-cryptopro` are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/);
versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
