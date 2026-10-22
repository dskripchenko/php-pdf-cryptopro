# dskripchenko/php-pdf-cryptopro

Подпись PDF библиотекой [`dskripchenko/php-pdf`](https://github.com/dskripchenko/php-pdf)
ключом, который хранит **КриптоПро CSP** на сервере (токен, реестр,
HDIMAGE), через PHP-расширение КриптоПро. Ключ не покидает CSP; подпись —
PAdES по ГОСТ Р 34.10-2012 (или RSA/ECDSA, если в CSP такой ключ).

> 🌐 [English](../../README.md) · [Deutsch](../de/README.md) · **Русский** · [中文](../zh/README.md)

## Использование

```php
use Dskripchenko\PhpPdf\Pdf\Signature\PdfSigner;
use Dskripchenko\PhpPdf\Pdf\SignatureConfig;
use Dskripchenko\PhpPdfCryptoPro\CryptoProCadesSigner;

$signer = CryptoProCadesSigner::fromThumbprint('3A 5F … 9C', pin: $pin);

$signed = PdfSigner::fromBytes($pdf)
    ->addField('signature', page: 0, x: 60, y: 450, width: 250, height: 75, appearance: $stamp)
    ->sign(new SignatureConfig(signer: $signer, timestampAuthority: $tsa));
```

Подписант работает везде, где php-pdf его принимает: `PdfSigner::sign()`
для готового PDF, `Pdf\Document::sign()` и `signature:` свёрстанного
документа. Сертификат берётся из хранилища CSP, поэтому штамп показывает
владельца, серийный номер и срок действия; хэш выбирается по ключу
(Стрибог-256 или -512 для ГОСТ).

php-pdf проверяет подпись, которую вернул CSP, прежде чем встроить: она
покрывает именно этот документ, сделана этим сертификатом и верна.

## Параметры

| Аргумент | По умолчанию | Смысл |
|---|---|---|
| `pin` | `null` | PIN контейнера ключа. Серверный процесс не может показать окно ввода PIN, поэтому передавайте его, если контейнер под PIN |
| `store` | `Store::CurrentUser` | `Store::LocalMachine` — хранилище компьютера |
| `mode` | `SignMode::Hash` | `Hash` отправляет в CSP только хэш документа (`SignHash`); `Content` — сами байты (`SignCades`), для сборок расширения без `SignHash` |
| `wholeChain` | `true` | CSP встраивает цепочку до корня; `false` — только сертификат подписанта |
| `chain` | `[]` | Промежуточные сертификаты (`Certificate`), если CSP их не встраивает |
| `digest` | по ключу | Переопределить алгоритм хэша |

`fromSubject('ООО Ромашка')` ищет сертификат по части субъекта — удобно на
тестовом стенде; в продакшене используйте точный отпечаток (`certmgr -list`
показывает его как «SHA1 Thumbprint»).

Ошибки — `CryptoProException` с кодом CryptoAPI (`$e->cryptoApiCode`) и
понятным объяснением частых случаев: неверный или заблокированный PIN, нет
контейнера или прав на него, сертификат без закрытого ключа, недоверенная
цепочка, неподдерживаемый алгоритм.

## Без `signing-time`: `CryptoProRawSigner`

CSP добавляет атрибут `signing-time` в каждую CMS, которую собирает.
Российские средства проверки это принимают, но базовый профиль PAdES
(ETSI EN 319 142-1) его не допускает — время подписи хранится в `/M`
словаря подписи, — и строгие валидаторы вроде EU DSS отмечают
несоответствие. `CryptoProRawSigner` поручает CSP подписать только хэш
(`RawSignature`), а CMS собирает php-pdf — без `signing-time`:

```php
use Dskripchenko\PhpPdf\Pdf\Signature\Signer\Certificate;
use Dskripchenko\PhpPdfCryptoPro\CryptoProRawSigner;

$signer = CryptoProRawSigner::fromThumbprint('3A 5F … 9C', pin: $pin, chain: [
    Certificate::fromPem(file_get_contents('issuing-ca.cer')),
]);

$signed = PdfSigner::fromBytes($pdf)->sign(new SignatureConfig(signer: $signer));
```

Принимает `pin`, `store`, `chain` и `digest`, как описано выше. Цепочку
здесь CSP не строит, поэтому промежуточные сертификаты передайте в
`chain`. Поддерживаются ключи ГОСТ Р 34.10-2012 и RSA; ключ ECDSA
подписывает через `CryptoProCadesSigner`. Возвращённое CSP значение
проверяется по сертификату до того, как php-pdf его встроит.

`CryptoProCadesSigner` не изменился: переход — это выбор класса, который
вы создаёте.

**Всегда передавайте PIN контейнера, у которого он есть.** Без него CSP
спрашивает пароль в терминале: CLI-скрипт завершится ошибкой, а воркер
PHP-FPM может упасть (SIGSEGV) — с любым из подписантов.

## Совместимость

В пределах 1.x публичный API, выпущенный в 1.0.0, не ломается: классы,
методы, **имена параметров** (аргументы можно передавать по имени),
значения по умолчанию, константы и варианты перечислений остаются
прежними, в `Cades` не появляется новых методов, в `SignMode` — новых
вариантов. Новое добавляется отдельными классами или необязательными
параметрами в конце. Изменение, которое что-то из этого ломает, — это
2.0. Контракт записан в `tests/api/baseline-1.x.json`, и тесты падают на
любом его нарушении.

Проверено с КриптоПро CSP 5.0.13820 и CAdES SDK 2.0.15820 (PHP 8.3 и
8.5). CSP 5.0.13000 с CAdES 2.0.15000 на Debian 13 не подписывал
(`0x8009001D`) и ронял воркеры PHP-FPM; обновление CSP это устранило.

## Требования

На сервере, где PHP подписывает:

- **PHP 8.2+** и `dskripchenko/php-pdf` 1.18 или новее (Composer установит его сам).
- **КриптоПро CSP 5** с лицензией на серверную подпись и контейнер ключа,
  установленный для пользователя, от которого работает PHP (или в
  хранилище компьютера с правами доступа).
- **PHP-расширение КриптоПро** (`phpcades`), собранное под вашу версию PHP
  и подключённое: исходники и шаги сборки — в
  [CryptoPro/phpcades](https://github.com/CryptoPro/phpcades); `docker/Dockerfile`
  этого пакета делает это на Ubuntu 24.04.
- **gost engine для OpenSSL** для ГОСТ-ключей: php-pdf сам считает хэш
  Стрибог документа и проверяет возвращённую подпись. Рабочий рецепт — в
  `docker/gost/` репозитория php-pdf.

## Проверка на настоящем CSP

`docker/` собирает контейнер с КриптоПро CSP, phpcades и gost engine и
подписывает PDF тестовым ГОСТ-сертификатом в обоих режимах — см.
[docker/README.md](../../docker/README.md).

## Тесты без КриптоПро

`CryptoProCadesSigner` обращается к CSP через небольшой интерфейс `Cades`;
`PhpCades` — его реализация поверх расширения. Передайте свой `Cades` в
`cades:`, чтобы запускать код без КриптоПро — так делают тесты этого пакета
с подделкой расширения на OpenSSL.
`CryptoProRawSigner` принимает в `cades:` объект, который реализует ещё и
`RawCades`.

## Семейство php-pdf

| Пакет | Что добавляет |
|---|---|
| [php-pdf](https://github.com/dskripchenko/php-pdf) | Ядро: HTML с CSS или билдер в PDF, чтение, слияние, заполнение форм, подпись и проверка. |
| [laravel-php-pdf](https://github.com/dskripchenko/laravel-php-pdf) | Мост для Laravel: Blade-шаблон сразу в PDF, `response()->pdf()`, подпись из конфига. |
| [php-pdf-fonts-liberation](https://github.com/dskripchenko/php-pdf-fonts-liberation) | Liberation Sans, Serif и Mono, метрически совместимые с Arial, Times New Roman и Courier New (OFL, вынесены из MIT-ядра). |
| **php-pdf-cryptopro** (этот пакет) | Подпись ключом в КриптоПро CSP на сервере: ГОСТ Р 34.10-2012, российская квалифицированная подпись. |
| [php-pdf-pkcs11](https://github.com/dskripchenko/php-pdf-pkcs11) | Подпись ключом на HSM, смарт-карте или USB-токене через PKCS#11. |
| [php-pdf-cloud-kms](https://github.com/dskripchenko/php-pdf-cloud-kms) | Подпись ключом в AWS KMS, Google Cloud KMS или Azure Key Vault. |

## Лицензия

MIT — см. [LICENSE](../../LICENSE). КриптоПро CSP и его PHP-расширение —
проприетарные продукты ООО «КРИПТО-ПРО» и лицензируются отдельно.
