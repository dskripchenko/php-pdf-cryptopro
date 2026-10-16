# A container with a real CryptoPro CSP

Builds PHP 8.3 with CryptoPro CSP 5, CAdES, CryptoPro's PHP extension
(built from [CryptoPro/phpcades](https://github.com/CryptoPro/phpcades))
and OpenSSL's gost engine, and signs a PDF with `CryptoProCadesSigner`
using phpcades' test GOST certificate.

CryptoPro CSP cannot be redistributed, so it is not in this repository:

1. Download CryptoPro CSP 5.0 for Linux, **deb**, for your architecture
   (`linux-amd64_deb.tgz` or `linux-arm64_deb.tgz`) from
   <https://cryptopro.ru/products/csp/downloads> into `docker/dist/`.
   A fresh install carries a 90-day trial licence.
2. Build:

   ```bash
   docker build -t php-pdf-cryptopro --build-arg CSP_ARCHIVE=linux-arm64_deb.tgz docker
   ```

3. Sign and validate in both modes (`SignHash` and `SignCades`):

   ```bash
   docker run --rm -v "$PWD:/pkg:ro" -v "$PWD/docker/dist:/out" php-pdf-cryptopro bash /pkg/docker/smoke/run.sh
   ```

   ```
   Hash    phpcades-test | md_gost12_256 | valid=true errors=[] warnings=["No revocation data for \"phpcades-test\""]
   Content phpcades-test | md_gost12_256 | valid=true errors=[] warnings=["No revocation data for \"phpcades-test\""]
   ```

   The signed PDFs land in `docker/dist/`. The test CA publishes no
   revocation data, hence the warning.

`docker/smoke/smoke.php` also works on a server of your own:
`THUMB=<sha1 thumbprint> PIN=<pin> ROOT=<root.cer> php smoke.php`.
