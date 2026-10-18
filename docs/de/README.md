# dskripchenko/php-pdf-cryptopro

PDFs mit [`dskripchenko/php-pdf`](https://github.com/dskripchenko/php-pdf)
signieren — mit einem Schlüssel, den **CryptoPro CSP** auf dem Server hält
(Token, Registry, HDIMAGE), über die PHP-Erweiterung von CryptoPro. Der
Schlüssel verlässt den CSP nie; die Signatur ist PAdES nach GOST R
34.10-2012 (oder RSA/ECDSA, wenn der CSP einen solchen Schlüssel hält).

> 🌐 [English](../../README.md) · **Deutsch** · [Русский](../ru/README.md) · [中文](../zh/README.md)

## Verwendung

```php
use Dskripchenko\PhpPdf\Pdf\Signature\PdfSigner;
use Dskripchenko\PhpPdf\Pdf\SignatureConfig;
use Dskripchenko\PhpPdfCryptoPro\CryptoProCadesSigner;

$signer = CryptoProCadesSigner::fromThumbprint('3A 5F … 9C', pin: $pin);

$signed = PdfSigner::fromBytes($pdf)
    ->addField('signature', page: 0, x: 60, y: 450, width: 250, height: 75, appearance: $stamp)
    ->sign(new SignatureConfig(signer: $signer, timestampAuthority: $tsa));
```

Der Signierer funktioniert überall, wo php-pdf einen annimmt:
`PdfSigner::sign()` für eine bestehende PDF, `Pdf\Document::sign()` und
`signature:` eines gesetzten Dokuments. Das Zertifikat stammt aus dem
CSP-Speicher, daher zeigt der Stempel Inhaber, Seriennummer und
Gültigkeitszeitraum; der Hash richtet sich nach dem Schlüssel (Streebog-256
oder -512 für GOST).

php-pdf prüft die vom CSP gelieferte Signatur vor dem Einbetten: sie deckt
genau dieses Dokument ab, wurde mit diesem Zertifikat erstellt und ist
gültig.

## Optionen

| Argument | Standard | Bedeutung |
|---|---|---|
| `pin` | `null` | PIN des Schlüsselcontainers. Ein Serverprozess kann keinen PIN-Dialog anzeigen; übergeben Sie ihn, sofern der Container einen hat |
| `store` | `Store::CurrentUser` | `Store::LocalMachine` für den Computerspeicher |
| `mode` | `SignMode::Hash` | `Hash` schickt nur den Hash des Dokuments an den CSP (`SignHash`); `Content` die Bytes (`SignCades`) — für Builds der Erweiterung ohne `SignHash` |
| `wholeChain` | `true` | Der CSP bettet die Kette bis zur Wurzel ein; `false` — nur das Signiererzertifikat |
| `chain` | `[]` | Zwischenzertifikate (`Certificate`), falls der CSP sie nicht einbettet |
| `digest` | nach Schlüssel | Hash-Algorithmus überschreiben |

`fromSubject('ООО Ромашка')` sucht ein Zertifikat nach einem Teil des
Subjekts — praktisch auf einer Testumgebung; in Produktion den exakten
Fingerabdruck verwenden (`certmgr -list` zeigt ihn als „SHA1 Thumbprint").

Fehler sind `CryptoProException` mit dem CryptoAPI-Code
(`$e->cryptoApiCode`) und einer verständlichen Erklärung der häufigen
Fälle: falsche oder gesperrte PIN, fehlender Container oder fehlende
Rechte, Zertifikat ohne privaten Schlüssel, nicht vertrauenswürdige Kette,
nicht unterstützter Algorithmus.

## Voraussetzungen

Auf dem Server, auf dem PHP signiert:

- **PHP 8.2+** mit `dskripchenko/php-pdf` 1.18 oder neuer (Composer installiert es).
- **CryptoPro CSP 5** mit einer Lizenz für serverseitiges Signieren und
  dem Schlüsselcontainer für den Benutzer, unter dem PHP läuft (oder im
  Computerspeicher mit Zugriffsrechten).
- **Die PHP-Erweiterung von CryptoPro** (`phpcades`), für Ihre PHP-Version
  gebaut und geladen: Quellen und Build-Schritte unter
  [CryptoPro/phpcades](https://github.com/CryptoPro/phpcades); `docker/Dockerfile`
  dieses Pakets erledigt das unter Ubuntu 24.04.
- **Die OpenSSL-gost-Engine** für GOST-Schlüssel: php-pdf berechnet den
  Streebog-Hash des Dokuments und prüft die gelieferte Signatur selbst. Ein
  funktionierendes Rezept liegt in `docker/gost/` des php-pdf-Repositorys.

## Ausprobieren mit einem echten CSP

`docker/` baut einen Container mit CryptoPro CSP, phpcades und der
gost-Engine und signiert eine PDF mit einem GOST-Testzertifikat in beiden
Modi — siehe [docker/README.md](../../docker/README.md).

## Tests ohne CryptoPro

`CryptoProCadesSigner` spricht über die kleine Schnittstelle `Cades` mit
dem CSP; `PhpCades` ist ihre Implementierung auf Basis der Erweiterung.
Übergeben Sie ein eigenes `Cades` als `cades:`, um den Code ohne CryptoPro
auszuführen — so machen es die Tests dieses Pakets mit einer
OpenSSL-basierten Attrappe der Erweiterung.

## Die php-pdf-Familie

| Paket | Was es hinzufügt |
|---|---|
| [php-pdf](https://github.com/dskripchenko/php-pdf) | Der Kern: HTML mit CSS oder ein Builder zu PDF, lesen, zusammenführen, Formulare ausfüllen, signieren und validieren. |
| [laravel-php-pdf](https://github.com/dskripchenko/laravel-php-pdf) | Laravel-Brücke: eine Blade-View direkt als PDF, `response()->pdf()`, Signieren aus der Konfiguration. |
| [php-pdf-fonts-liberation](https://github.com/dskripchenko/php-pdf-fonts-liberation) | Liberation Sans, Serif und Mono, metrisch kompatibel zu Arial, Times New Roman und Courier New (OFL, aus dem MIT-Kern herausgehalten). |
| **php-pdf-cryptopro** (dieses Paket) | Signiert mit einem Schlüssel in CryptoPro CSP auf dem Server: GOST R 34.10-2012, russische qualifizierte Signaturen. |
| [php-pdf-pkcs11](https://github.com/dskripchenko/php-pdf-pkcs11) | Signiert mit einem Schlüssel auf einem HSM, einer Smartcard oder einem USB-Token über PKCS#11. |
| [php-pdf-cloud-kms](https://github.com/dskripchenko/php-pdf-cloud-kms) | Signiert mit einem Schlüssel in AWS KMS, Google Cloud KMS oder Azure Key Vault. |

## Lizenz

MIT — siehe [LICENSE](../../LICENSE). CryptoPro CSP und seine
PHP-Erweiterung sind proprietäre Produkte der CryptoPro LLC und werden
separat lizenziert.
