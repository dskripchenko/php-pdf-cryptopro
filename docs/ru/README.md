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

## Требования

На сервере, где PHP подписывает:

- **КриптоПро CSP 5** с лицензией на серверную подпись и контейнер ключа,
  установленный для пользователя, от которого работает PHP (или в
  хранилище компьютера с правами доступа).
- **PHP-расширение КриптоПро** (`phpcades` из КриптоПро ЭЦП SDK), собранное
  под вашу версию PHP и подключённое.
- **gost engine для OpenSSL** для ГОСТ-ключей: php-pdf сам считает хэш
  Стрибог документа и проверяет возвращённую подпись. Рабочий рецепт — в
  `docker/gost/` репозитория php-pdf.

## Тесты без КриптоПро

`CryptoProCadesSigner` обращается к CSP через небольшой интерфейс `Cades`;
`PhpCades` — его реализация поверх расширения. Передайте свой `Cades` в
`cades:`, чтобы запускать код без КриптоПро — так делают тесты этого пакета
с подделкой расширения на OpenSSL.

## Лицензия

MIT — см. [LICENSE](../../LICENSE). КриптоПро CSP и его PHP-расширение —
проприетарные продукты ООО «КРИПТО-ПРО» и лицензируются отдельно.
