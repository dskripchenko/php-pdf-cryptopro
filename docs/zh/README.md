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

## 不含 `signing-time`：`CryptoProRawSigner`

CSP 会在它生成的每个 CMS 中加入 `signing-time` 属性。俄罗斯的验证工具接受这一点，
但 PAdES 基线配置（ETSI EN 319 142-1）不允许它——签名时间记录在签名字典的 `/M`
中——像 EU DSS 这样的严格验证器会将其标记出来。`CryptoProRawSigner` 只让 CSP
对哈希签名（`RawSignature`），由 php-pdf 自己构建 CMS，不含 `signing-time`：

```php
use Dskripchenko\PhpPdf\Pdf\Signature\Signer\Certificate;
use Dskripchenko\PhpPdfCryptoPro\CryptoProRawSigner;

$signer = CryptoProRawSigner::fromThumbprint('3A 5F … 9C', pin: $pin, chain: [
    Certificate::fromPem(file_get_contents('issuing-ca.cer')),
]);

$signed = PdfSigner::fromBytes($pdf)->sign(new SignatureConfig(signer: $signer));
```

它接受与上文相同的 `pin`、`store`、`chain` 和 `digest`。此时证书链不由 CSP
构建，请通过 `chain` 传入中间证书。支持 GOST R 34.10-2012 和 RSA 密钥；ECDSA
密钥请使用 `CryptoProCadesSigner` 签名。php-pdf 在嵌入之前会用证书校验 CSP
返回的签名值。

`CryptoProCadesSigner` 保持不变：切换只是选择创建哪个类。

**容器设有 PIN 时务必传入 PIN。** 否则 CSP 会在终端上询问密码：CLI 脚本会失败，
PHP-FPM 工作进程可能崩溃（SIGSEGV）——无论使用哪个签名器。

## 兼容性

在 1.x 范围内，1.0.0 发布的公共 API 不会被破坏：类、方法、**参数名**（可以放心按名称传参）、
默认值、常量和枚举项保持不变；`Cades` 不会新增方法，`SignMode` 不会新增枚举项。
新功能以新类或末尾的可选参数形式加入。任何会破坏上述内容的改动都属于 2.0。
`tests/api/baseline-1.x.json` 记录了这一契约，测试套件在任何破坏时都会失败。

已在 CryptoPro CSP 5.0.13820 和 CAdES SDK 2.0.15820（PHP 8.3 与 8.5）上测试。
Debian 13 上的 CSP 5.0.13000 搭配 CAdES 2.0.15000 无法签名（`0x8009001D`），
并导致 PHP-FPM 工作进程崩溃；更新 CSP 后问题解决。

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
`CryptoProRawSigner` 的 `cades:` 还需实现 `RawCades`。

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
