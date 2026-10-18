# dskripchenko/php-pdf-cryptopro

使用 [`dskripchenko/php-pdf`](https://github.com/dskripchenko/php-pdf)
签署 PDF，密钥由服务器上的 **CryptoPro CSP** 保管（令牌、注册表、HDIMAGE），
通过 CryptoPro 的 PHP 扩展调用。密钥从不离开 CSP；签名为 PAdES，算法为
GOST R 34.10-2012（若 CSP 中保存的是 RSA/ECDSA 密钥则使用相应算法）。

> 🌐 [English](../../README.md) · [Deutsch](../de/README.md) · [Русский](../ru/README.md) · **中文**

## 用法

```php
use Dskripchenko\PhpPdf\Pdf\Signature\PdfSigner;
use Dskripchenko\PhpPdf\Pdf\SignatureConfig;
use Dskripchenko\PhpPdfCryptoPro\CryptoProCadesSigner;

$signer = CryptoProCadesSigner::fromThumbprint('3A 5F … 9C', pin: $pin);

$signed = PdfSigner::fromBytes($pdf)
    ->addField('signature', page: 0, x: 60, y: 450, width: 250, height: 75, appearance: $stamp)
    ->sign(new SignatureConfig(signer: $signer, timestampAuthority: $tsa));
```

凡是 php-pdf 接受签名者的地方都可以使用它：已有 PDF 的
`PdfSigner::sign()`、`Pdf\Document::sign()` 以及排版文档的 `signature:`。
证书取自 CSP 证书存储，因此印章会显示持有人、序列号和有效期；哈希算法随
密钥而定（GOST 使用 Streebog-256 或 -512）。

php-pdf 在嵌入之前会检查 CSP 返回的签名：它恰好覆盖本文档、由该证书签发、
签名有效。

## 选项

| 参数 | 默认值 | 含义 |
|---|---|---|
| `pin` | `null` | 密钥容器的 PIN。服务器进程无法弹出 PIN 对话框，容器有 PIN 时请传入 |
| `store` | `Store::CurrentUser` | `Store::LocalMachine` 表示计算机存储 |
| `mode` | `SignMode::Hash` | `Hash` 只把文档哈希发给 CSP（`SignHash`）；`Content` 发送字节本身（`SignCades`），用于不支持 `SignHash` 的扩展版本 |
| `wholeChain` | `true` | CSP 嵌入直到根证书的证书链；`false` 只嵌入签名者证书 |
| `chain` | `[]` | CSP 不嵌入时需要补充的中间证书（`Certificate`） |
| `digest` | 随密钥 | 覆盖哈希算法 |

`fromSubject('ООО Ромашка')` 按主题的一部分查找证书，适合测试环境；生产
环境请使用精确的指纹（`certmgr -list` 显示为 "SHA1 Thumbprint"）。

错误以 `CryptoProException` 抛出，带有 CryptoAPI 代码（`$e->cryptoApiCode`），
并对常见情况给出说明：PIN 错误或被锁定、容器不存在或无权访问、证书没有
私钥、证书链不受信任、算法不受支持。

## 要求

在 PHP 执行签名的服务器上：

- **PHP 8.2+**，以及 `dskripchenko/php-pdf` 1.18 或更高版本（由 Composer 安装）。
- **CryptoPro CSP 5**，其许可证允许服务器端签名；密钥容器已为运行 PHP 的
  用户安装（或安装在计算机存储中并具有访问权限）。
- **CryptoPro 的 PHP 扩展**（`phpcades`），针对你的 PHP 版本编译并已加载：
  源码和构建步骤见 [CryptoPro/phpcades](https://github.com/CryptoPro/phpcades)；
  本包的 `docker/Dockerfile` 在 Ubuntu 24.04 上完成这些步骤。
- GOST 密钥需要 **OpenSSL gost engine**：php-pdf 会自行计算文档的
  Streebog 哈希并验证返回的签名。php-pdf 仓库的 `docker/gost/` 中有可用的
  配置示例。

## 在真实 CSP 上试用

`docker/` 会构建一个包含 CryptoPro CSP、phpcades 和 gost engine 的容器，
并用 GOST 测试证书以两种模式签署 PDF——见
[docker/README.md](../../docker/README.md)。

## 无 CryptoPro 时的测试

`CryptoProCadesSigner` 通过小接口 `Cades` 与 CSP 交互；`PhpCades` 是基于
该扩展的实现。通过 `cades:` 传入自己的 `Cades`，即可在没有 CryptoPro 的
情况下运行代码——本包的测试就是用基于 OpenSSL 的扩展替身这样做的。

## php-pdf 系列

| 包 | 提供的功能 |
|---|---|
| [php-pdf](https://github.com/dskripchenko/php-pdf) | 核心：用 HTML 加 CSS 或构建器生成 PDF，读取、合并、填写表单、签名和验证。 |
| [laravel-php-pdf](https://github.com/dskripchenko/laravel-php-pdf) | Laravel 桥接：Blade 视图直接生成 PDF、`response()->pdf()`、按配置签名。 |
| [php-pdf-fonts-liberation](https://github.com/dskripchenko/php-pdf-fonts-liberation) | Liberation Sans、Serif 和 Mono，与 Arial、Times New Roman 和 Courier New 度量兼容（OFL 许可，独立于 MIT 核心之外）。 |
| **php-pdf-cryptopro**（本包） | 使用服务器上 CryptoPro CSP 中的密钥签名：GOST R 34.10-2012，俄罗斯合格电子签名。 |
| [php-pdf-pkcs11](https://github.com/dskripchenko/php-pdf-pkcs11) | 通过 PKCS#11 使用 HSM、智能卡或 USB 令牌上的密钥签名。 |
| [php-pdf-cloud-kms](https://github.com/dskripchenko/php-pdf-cloud-kms) | 使用 AWS KMS、Google Cloud KMS 或 Azure Key Vault 中的密钥签名。 |

## 许可证

MIT——见 [LICENSE](../../LICENSE)。CryptoPro CSP 及其 PHP 扩展是
CryptoPro LLC 的专有产品，需另行授权。
