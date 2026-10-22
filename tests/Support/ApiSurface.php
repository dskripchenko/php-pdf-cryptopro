<?php

declare(strict_types=1);

namespace Dskripchenko\PhpPdfCryptoPro\Tests\Support;

/**
 * The public API of the package as data — what a caller can rely on:
 * classes, interfaces and enums with their public (and, for classes that
 * can be extended, protected) methods, parameters with names, types and
 * defaults, constants, enum cases and public properties.
 *
 * {@see self::breaks()} compares it with the recorded 1.x baseline
 * (tests/api/baseline-1.x.json). Parameter names belong to the contract:
 * callers pass arguments by name.
 */
final class ApiSurface
{
    public const BASELINE = __DIR__.'/../api/baseline-1.x.json';

    private const NAMESPACE = 'Dskripchenko\\PhpPdfCryptoPro\\';

    /** @return array<string, array<string, mixed>> */
    public static function current(): array
    {
        $out = [];
        foreach (glob(__DIR__.'/../../src/*.php') ?: [] as $file) {
            $name = self::NAMESPACE.basename($file, '.php');
            $out[$name] = self::describe(new \ReflectionClass($name));
        }
        ksort($out);

        return $out;
    }

    /** @return array<string, array<string, mixed>> */
    public static function baseline(): array
    {
        return json_decode((string) file_get_contents(self::BASELINE), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @param  array<string, mixed>  $surface */
    public static function encode(array $surface): string
    {
        return json_encode($surface, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * What `$current` breaks of `$baseline`, one line each; [] — nothing.
     *
     * @param  array<string, array<string, mixed>>  $baseline
     * @param  array<string, array<string, mixed>>  $current
     * @return list<string>
     */
    public static function breaks(array $baseline, array $current): array
    {
        $breaks = [];
        foreach ($baseline as $class => $was) {
            $now = $current[$class] ?? null;
            if ($now === null) {
                $breaks[] = "$class: removed";

                continue;
            }
            if ($now['kind'] !== $was['kind']) {
                $breaks[] = "$class: became {$now['kind']} (was {$was['kind']})";

                continue;
            }
            foreach (['final', 'abstract', 'readonly'] as $flag) {
                if ($now[$flag] && ! $was[$flag]) {
                    $breaks[] = "$class: became $flag";
                }
            }
            if ($was['parent'] !== null && $now['parent'] !== $was['parent']) {
                $breaks[] = "$class: parent changed from {$was['parent']} to ".($now['parent'] ?? 'none');
            }
            foreach (array_diff($was['implements'], $now['implements']) as $interface) {
                $breaks[] = "$class: no longer implements $interface";
            }
            foreach ($was['constants'] as $name => $value) {
                if (! array_key_exists($name, $now['constants'])) {
                    $breaks[] = "$class::$name: constant removed";
                } elseif ($now['constants'][$name] !== $value) {
                    $breaks[] = "$class::$name: constant value changed";
                }
            }
            foreach ($was['cases'] as $name => $value) {
                if (! array_key_exists($name, $now['cases'])) {
                    $breaks[] = "$class::$name: enum case removed";
                } elseif ($now['cases'][$name] !== $value) {
                    $breaks[] = "$class::$name: enum case value changed";
                }
            }
            // A new case breaks an exhaustive `match` over the enum.
            foreach (array_diff_key($now['cases'], $was['cases']) as $name => $_) {
                $breaks[] = "$class::$name: enum case added";
            }
            foreach ($was['properties'] as $name => $property) {
                $p = $now['properties'][$name] ?? null;
                if ($p === null) {
                    $breaks[] = "$class::\$$name: property removed";
                } elseif ($p['type'] !== $property['type']) {
                    $breaks[] = "$class::\$$name: type changed from {$property['type']} to {$p['type']}";
                } elseif ($p['readonly'] && ! $property['readonly']) {
                    $breaks[] = "$class::\$$name: became readonly";
                } elseif ($p['static'] !== $property['static']) {
                    $breaks[] = "$class::\$$name: static changed";
                }
            }
            foreach ($was['methods'] as $name => $method) {
                $m = $now['methods'][$name] ?? null;
                if ($m === null) {
                    $breaks[] = "$class::$name(): removed";

                    continue;
                }
                array_push($breaks, ...self::methodBreaks("$class::$name()", $method, $m));
            }
            // Every implementation of an interface would have to add it.
            if ($was['kind'] === 'interface') {
                foreach (array_diff_key($now['methods'], $was['methods']) as $name => $_) {
                    $breaks[] = "$class::$name(): method added to an interface";
                }
            }
            // Abstract methods added to an extendable class break its subclasses.
            if (! $was['final']) {
                foreach (array_diff_key($now['methods'], $was['methods']) as $name => $m) {
                    if ($m['abstract']) {
                        $breaks[] = "$class::$name(): abstract method added";
                    }
                }
            }
        }

        return $breaks;
    }

    /**
     * What `$current` adds to `$baseline` and the baseline does not record yet.
     *
     * @param  array<string, array<string, mixed>>  $baseline
     * @param  array<string, array<string, mixed>>  $current
     * @return list<string>
     */
    public static function unrecorded(array $baseline, array $current): array
    {
        $out = [];
        foreach ($current as $class => $now) {
            if (! isset($baseline[$class])) {
                $out[] = "$class: new";
            } elseif (self::encode($baseline[$class]) !== self::encode($now)) {
                $out[] = "$class: changed";
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $was
     * @param  array<string, mixed>  $now
     * @return list<string>
     */
    private static function methodBreaks(string $label, array $was, array $now): array
    {
        $breaks = [];
        if ($now['static'] !== $was['static']) {
            $breaks[] = "$label: static changed";
        }
        if ($now['visibility'] !== $was['visibility'] && $now['visibility'] !== 'public') {
            $breaks[] = "$label: visibility narrowed to {$now['visibility']}";
        }
        foreach (['final', 'abstract'] as $flag) {
            if ($now[$flag] && ! $was[$flag]) {
                $breaks[] = "$label: became $flag";
            }
        }
        if ($now['return'] !== $was['return']) {
            $breaks[] = "$label: return type changed from {$was['return']} to {$now['return']}";
        }
        foreach ($was['params'] as $i => $param) {
            $p = $now['params'][$i] ?? null;
            $at = "$label parameter #".($i + 1)." \${$param['name']}";
            if ($p === null) {
                $breaks[] = "$at: removed";

                continue;
            }
            if ($p['name'] !== $param['name']) {
                $breaks[] = "$at: renamed to \${$p['name']} (callers pass it by name)";
            }
            if ($p['type'] !== $param['type']) {
                $breaks[] = "$at: type changed from {$param['type']} to {$p['type']}";
            }
            if ($param['optional'] && ! $p['optional']) {
                $breaks[] = "$at: became required";
            }
            if ($param['optional'] && $p['optional'] && $p['default'] !== $param['default']) {
                $breaks[] = "$at: default changed from {$param['default']} to {$p['default']}";
            }
            if ($p['variadic'] !== $param['variadic'] || $p['byRef'] !== $param['byRef']) {
                $breaks[] = "$at: passing changed (variadic or by reference)";
            }
        }
        foreach (array_slice($now['params'], count($was['params'])) as $i => $p) {
            if (! $p['optional']) {
                $breaks[] = "$label: required parameter \${$p['name']} added";
            }
        }

        return $breaks;
    }

    /**
     * @param  \ReflectionClass<object>  $class
     * @return array<string, mixed>
     */
    private static function describe(\ReflectionClass $class): array
    {
        $kind = match (true) {
            $class->isEnum() => 'enum',
            $class->isInterface() => 'interface',
            $class->isTrait() => 'trait',
            default => 'class',
        };
        $extendable = $kind === 'class' && ! $class->isFinal();

        $methods = [];
        foreach ($class->getMethods() as $method) {
            // PHP's own methods (an exception's getMessage(), an enum's
            // cases()) are not the package's to break.
            if ($method->getDeclaringClass()->isInternal()) {
                continue;
            }
            if (! $method->isPublic() && ! ($extendable && $method->isProtected())) {
                continue;
            }
            $methods[$method->getName()] = [
                'visibility' => $method->isPublic() ? 'public' : 'protected',
                'static' => $method->isStatic(),
                'final' => $method->isFinal(),
                'abstract' => $method->isAbstract(),
                'return' => (string) $method->getReturnType(),
                'params' => array_map(self::param(...), $method->getParameters()),
            ];
        }
        ksort($methods);

        $constants = [];
        foreach ($class->getReflectionConstants(\ReflectionClassConstant::IS_PUBLIC) as $constant) {
            if ($constant->isEnumCase() || $constant->getDeclaringClass()->isInternal()) {
                continue;
            }
            $constants[$constant->getName()] = var_export($constant->getValue(), true);
        }
        ksort($constants);

        $cases = [];
        if ($class->isEnum()) {
            foreach ((new \ReflectionEnum($class->getName()))->getCases() as $case) {
                $cases[$case->getName()] = $case instanceof \ReflectionEnumBackedCase ? var_export($case->getBackingValue(), true) : null;
            }
        }

        $properties = [];
        foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($kind === 'enum' || $property->getDeclaringClass()->isInternal()) {
                continue;
            }
            $properties[$property->getName()] = [
                'type' => (string) $property->getType(),
                'readonly' => $property->isReadOnly(),
                'static' => $property->isStatic(),
            ];
        }
        ksort($properties);

        $implements = $class->getInterfaceNames();
        sort($implements);

        return [
            'kind' => $kind,
            'final' => $class->isFinal(),
            'abstract' => $kind === 'class' && $class->isAbstract(),
            'readonly' => $class->isReadOnly(),
            'parent' => $class->getParentClass() !== false ? $class->getParentClass()->getName() : null,
            'implements' => array_values(array_filter($implements, static fn (string $i): bool => ! in_array($i, ['UnitEnum', 'BackedEnum', 'Stringable', 'Throwable'], true))),
            'constants' => $constants,
            'cases' => $cases,
            'properties' => $properties,
            'methods' => $methods,
        ];
    }

    /** @return array<string, mixed> */
    private static function param(\ReflectionParameter $p): array
    {
        $default = null;
        if ($p->isDefaultValueAvailable()) {
            $default = $p->isDefaultValueConstant()
                ? (string) $p->getDefaultValueConstantName()
                : str_replace("\n", '', var_export($p->getDefaultValue(), true));
        }

        return [
            'name' => $p->getName(),
            'type' => (string) $p->getType(),
            'optional' => $p->isOptional(),
            'default' => $default,
            'variadic' => $p->isVariadic(),
            'byRef' => $p->isPassedByReference(),
        ];
    }
}
