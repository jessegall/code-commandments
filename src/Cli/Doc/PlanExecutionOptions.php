<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Doc;

use JesseGall\CodeCommandments\PlanExecution;
use ReflectionClass;
use ReflectionMethod;

/**
 * The self-describing catalogue of {@see PlanExecution} builder OPTIONS — every public fluent setter (a
 * method returning the builder), paired with the one-line summary from its own docblock. Reflected, so a
 * new `->option()` on the builder appears here automatically; the docs generator emits a table from it and
 * a test fails when any option lacks a docblock summary, so the config surface can never ship undocumented.
 */
final class PlanExecutionOptions
{
    /**
     * The options as a markdown table — the single source for both the README generator and its currency
     * test, so the doc can only be the one the builder describes.
     */
    public static function table(): string
    {
        $rows = "| Option | What it does |\n|---|---|\n";

        foreach (self::all() as $option) {
            $summary = str_replace('|', '\|', $option['summary']);
            $rows .= "| `->{$option['name']}(…)` | {$summary} |\n";
        }

        return $rows;
    }

    /**
     * @return list<array{name: string, summary: string}>  in source order
     */
    public static function all(): array
    {
        $reflection = new ReflectionClass(PlanExecution::class);

        $options = array_filter(
            $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
            static fn (ReflectionMethod $method) => self::isOption($method),
        );

        usort($options, static fn (ReflectionMethod $a, ReflectionMethod $b): int => $a->getStartLine() <=> $b->getStartLine());

        return array_map(
            static fn (ReflectionMethod $method) => [
                'name' => $method->getName(),
                'summary' => Summary::first($method->getDocComment()),
            ],
            array_values($options),
        );
    }

    /**
     * A fluent OPTION is a public method that returns the builder itself (`self`/`static`/`PlanExecution`)
     * — a chainable setter. The terminal `build()` (which returns the frozen profile) and any constructor
     * are excluded.
     */
    private static function isOption(ReflectionMethod $method): bool
    {
        if ($method->isStatic() || $method->isConstructor()) {
            return false;
        }

        $return = $method->getReturnType();

        return $return instanceof \ReflectionNamedType
            && in_array($return->getName(), ['self', 'static', PlanExecution::class], true);
    }
}
