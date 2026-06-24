<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\Resolvers\Ast\FileImports;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * DRAFT — Totality doctrine, band 0 (boundary). The ROOT a coalescing/guard symptom
 * traces back to: a `mixed` field on a DESERIALIZATION BOUNDARY (a Spatie Data DTO or
 * a FormRequest) that is re-coerced downstream. The type decision was punted at the
 * boundary, so every consumer re-coerces the same `mixed` by hand. Prescribe typing it
 * once (one payload per aspect, holding typed value objects); the downstream guards
 * then collapse and the cascade hushes them as symptoms.
 *
 * Gated on real downstream coercion (Layer B): a `mixed` boundary field with NO
 * coercing consumer is genuinely heterogeneous and stays silent. Not yet registered —
 * pending the doctrine-engine wiring; exercised directly by its unit test.
 */
class PreferTypedBoundaryProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    /** Deserialization boundaries — a `mixed` here leaks an untyped value into the app. */
    private const BOUNDARY_BASES = [
        'Spatie\\LaravelData\\Data',
        'Illuminate\\Foundation\\Http\\FormRequest',
    ];

    /** Textual evidence that a consumer is re-coercing the leaked value. */
    private const COERCION_TOKENS = [
        'is_string(', 'is_array(', 'is_int(', 'is_bool(', 'is_numeric(',
        'match (', 'match(', '(string)', '(array)', '(int)', '(bool)', ' ?? ', '->coalesce(',
    ];

    private ?CodebaseIndex $index = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    public function description(): string
    {
        return 'Type values at the deserialization boundary instead of leaking `mixed` for consumers to re-coerce.';
    }

    public function detailedDescription(): string
    {
        return <<<'TXT'
        A `mixed` field on a Spatie Data DTO or a FormRequest punts the type decision at
        the boundary, so every downstream consumer re-coerces the same value by hand
        (is_string / is_array / match / ?? ). That coercion is a SYMPTOM; the root is the
        untyped boundary field. Give each aspect its own typed payload holding a typed list
        of value objects, and the downstream guards collapse into trivial typed reads.
        TXT;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $imports = FileImports::of($ast);
        $namespace = FileImports::namespace($ast);
        $warnings = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if (! $this->isBoundary($class, $imports)) {
                continue;
            }

            $fqcn = ($namespace !== null ? $namespace . '\\' : '') . (string) $class->name;

            foreach ($this->mixedFields($class) as [$field, $line]) {
                $sites = $this->coercionSites($fqcn, $field);

                // Layer-B gate: an index with zero coercing consumers means the
                // `mixed` is genuinely heterogeneous — not a punted boundary.
                if ($this->index !== null && $sites === []) {
                    continue;
                }

                $warnings[] = $this->warningAt($line, $this->message((string) $class->name, $field, $sites), null, $field);
            }
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    private function isBoundary(Node\Stmt\Class_ $class, array $imports): bool
    {
        if ($class->extends === null) {
            return false;
        }

        $parent = ltrim($imports[$class->extends->toString()] ?? $class->extends->toString(), '\\');

        return in_array($parent, self::BOUNDARY_BASES, true);
    }

    /**
     * Constructor-promoted params and declared properties typed exactly `mixed`.
     *
     * @return list<array{string, int}>  [fieldName, line]
     */
    private function mixedFields(Node\Stmt\Class_ $class): array
    {
        $fields = [];

        $ctor = $class->getMethod('__construct');
        if ($ctor !== null) {
            foreach ($ctor->params as $param) {
                if ($param->flags !== 0 && $this->isMixed($param->type) && $param->var instanceof Node\Expr\Variable) {
                    $fields[] = [(string) $param->var->name, $param->getStartLine()];
                }
            }
        }

        foreach ($class->getProperties() as $property) {
            if ($this->isMixed($property->type)) {
                foreach ($property->props as $prop) {
                    $fields[] = [(string) $prop->name, $property->getStartLine()];
                }
            }
        }

        return $fields;
    }

    private function isMixed(?Node $type): bool
    {
        return $type instanceof Node\Identifier && $type->toLowerString() === 'mixed';
    }

    /**
     * Downstream consumers that re-coerce this boundary field — the origin trace
     * and the Layer-B gate. Re-reads each indexed consumer file (draft heuristic:
     * mentions the boundary type, reads `->field`, and carries a coercion token).
     *
     * @return list<string>  short class names of the coercing consumers
     */
    private function coercionSites(string $fqcn, string $field): array
    {
        if ($this->index === null) {
            return [];
        }

        $short = $this->shortName($fqcn);
        $sites = [];

        foreach ($this->index->classes() as $summary) {
            if ($summary->fqcn === ltrim($fqcn, '\\')) {
                continue;
            }

            $source = @file_get_contents($summary->filePath);

            if ($source === false
                || ! str_contains($source, $short)
                || ! str_contains($source, '->' . $field)) {
                continue;
            }

            foreach (self::COERCION_TOKENS as $token) {
                if (str_contains($source, $token)) {
                    $sites[] = $this->shortName($summary->fqcn);
                    break;
                }
            }
        }

        return array_values(array_unique($sites));
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', ltrim($fqcn, '\\'));

        return end($parts);
    }

    private function message(string $class, string $field, array $sites): string
    {
        $trace = $sites === []
            ? 'consumers must re-coerce it by hand'
            : 're-coerced downstream in ' . implode(', ', $sites);

        return sprintf(
            '%s::$%s is `mixed` at a deserialization boundary — %s. Type it at the source '
            . '(one payload per aspect, each holding a typed list of value objects); the downstream guards collapse.',
            $class,
            $field,
            $trace,
        );
    }

    public function advisory(): ?Advisory
    {
        return Advisory::make()
            ->applyWhen('a `mixed`/untyped field on a Data DTO or FormRequest is coerced (is_string/is_array/match/??) by one or more consumers')
            ->leaveWhen('the value is genuinely heterogeneous and never narrowed — no consumer coerces it')
            ->whenUnsure('trace one coercion site upward; if it lands on this field, this is the root — fix here, not at the leaf');
    }

    public function defaultTier(): Tier
    {
        return Tier::Structural;
    }
}
