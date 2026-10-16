# Security policy

Report vulnerabilities privately through
[GitHub security advisories](https://github.com/dskripchenko/php-pdf-cryptopro/security/advisories/new)
or by e-mail to denskrp90@gmail.com — not in public issues.

Supported: the latest minor release.

Handling the key container's PIN is up to the application: keep it out of
logs and process arguments. The package passes it only to the CSP and marks
it `#[\SensitiveParameter]`.
