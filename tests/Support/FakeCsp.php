<?php

declare(strict_types=1);

namespace Dskripchenko\PhpPdfCryptoPro\Tests\Support;

/** State of the fake phpcades extension (tests/Support/fake-phpcades.php). */
final class FakeCsp
{
    /** @var list<list<mixed>> */
    public static array $calls = [];

    /** The PIN the fake key container wants; null — none. */
    public static ?string $pin = null;

    /** The PIN the signer was given. */
    public static ?string $givenPin = null;

    /** Sign with a key that is not the certificate's. */
    public static bool $wrongKey = false;

    public static function load(): void
    {
        require_once __DIR__.'/fake-phpcades.php';
        self::$calls = [];
        self::$pin = null;
        self::$givenPin = null;
        self::$wrongKey = false;
    }

    public static function checkPin(): void
    {
        if (self::$pin !== null && self::$givenPin !== self::$pin) {
            throw new \Exception('Ошибка при подписании. (0x8010006B)');
        }
    }
}
