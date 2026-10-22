<?php

declare(strict_types=1);

namespace Dskripchenko\PhpPdfCryptoPro\Tests;

use Dskripchenko\PhpPdfCryptoPro\Tests\Support\ApiSurface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The 1.x API contract: nothing a caller of 1.0.0 relies on may break
 * within 1.x — no removed or renamed class, method, parameter or case, no
 * new required parameter, no changed type or default. Additions are fine
 * and are recorded into the baseline with `composer api:record`, which
 * refuses to record a break.
 */
final class ApiContractTest extends TestCase
{
    #[Test]
    public function nothing_in_the_1x_baseline_is_broken(): void
    {
        $breaks = ApiSurface::breaks(ApiSurface::baseline(), ApiSurface::current());

        self::assertSame([], $breaks, "The 1.x API contract is broken — this needs a major version:\n".implode("\n", $breaks));
    }

    #[Test]
    public function additions_are_recorded_in_the_baseline(): void
    {
        $unrecorded = ApiSurface::unrecorded(ApiSurface::baseline(), ApiSurface::current());

        self::assertSame([], $unrecorded, "The public API changed without being recorded — run `composer api:record`:\n".implode("\n", $unrecorded));
    }

    #[Test]
    public function the_checker_catches_the_breaks_that_matter(): void
    {
        $baseline = ApiSurface::baseline();
        $signer = 'Dskripchenko\\PhpPdfCryptoPro\\CryptoProCadesSigner';

        $cases = [
            'renamed parameter' => static function (array &$api) use ($signer): void {
                $api[$signer]['methods']['fromThumbprint']['params'][1]['name'] = 'password';
            },
            'changed default' => static function (array &$api) use ($signer): void {
                $api[$signer]['methods']['fromThumbprint']['params'][3]['default'] = '\\Dskripchenko\\PhpPdfCryptoPro\\SignMode::Content';
            },
            'new required parameter' => static function (array &$api) use ($signer): void {
                $api[$signer]['methods']['fromThumbprint']['params'][] = ['name' => 'extra', 'type' => 'string', 'optional' => false, 'default' => null, 'variadic' => false, 'byRef' => false];
            },
            'removed method' => static function (array &$api) use ($signer): void {
                unset($api[$signer]['methods']['digestAlgorithm']);
            },
            'method added to an interface' => static function (array &$api): void {
                $api['Dskripchenko\\PhpPdfCryptoPro\\Cades']['methods']['another'] = $api['Dskripchenko\\PhpPdfCryptoPro\\Cades']['methods']['export'];
            },
            'enum case added' => static function (array &$api): void {
                $api['Dskripchenko\\PhpPdfCryptoPro\\SignMode']['cases']['Raw'] = null;
            },
            'removed class' => static function (array &$api): void {
                unset($api['Dskripchenko\\PhpPdfCryptoPro\\Store']);
            },
        ];
        foreach ($cases as $name => $mutate) {
            $current = $baseline;
            $mutate($current);
            self::assertNotSame([], ApiSurface::breaks($baseline, $current), "not caught: $name");
        }

        $added = $baseline;
        $added[$signer]['methods']['fromThumbprint']['params'][] = ['name' => 'extra', 'type' => '?string', 'optional' => true, 'default' => 'NULL', 'variadic' => false, 'byRef' => false];
        $added['Dskripchenko\\PhpPdfCryptoPro\\NewThing'] = $baseline['Dskripchenko\\PhpPdfCryptoPro\\Store'];
        self::assertSame([], ApiSurface::breaks($baseline, $added), 'an optional trailing parameter and a new class are not breaks');
    }
}
