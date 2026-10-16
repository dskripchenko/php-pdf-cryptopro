<?php

// Signatures of the CryptoPro phpcades extension classes used by PhpCades,
// for static analysis only; the extension itself is not available in CI.

class CPStore
{
    public function Open(int $location, string $name, int $mode): void {}

    public function get_Certificates(): CPCertificates
    {
        return new CPCertificates;
    }
}

class CPCertificates
{
    public function Find(int $type, mixed $criteria, int $validOnly): CPCertificates
    {
        return $this;
    }

    public function Count(): int
    {
        return 0;
    }

    public function Item(int $index): CPCertificate
    {
        return new CPCertificate;
    }
}

class CPCertificate
{
    public function Export(int $encoding): string
    {
        return '';
    }
}

class CPSigner
{
    public function set_Certificate(object $certificate): void {}

    public function set_Options(int $options): void {}

    public function set_KeyPin(string $pin): void {}
}

class CPHashedData
{
    public function set_Algorithm(int $algorithm): void {}

    public function SetHashValue(string $hex): void {}
}

class CPSignedData
{
    public function set_ContentEncoding(int $encoding): void {}

    public function set_Content(string $content): void {}

    public function SignHash(CPHashedData $hashed, CPSigner $signer, int $type, int $encoding): string
    {
        return '';
    }

    public function SignCades(CPSigner $signer, int $type, bool $detached, int $encoding): string
    {
        return '';
    }
}
