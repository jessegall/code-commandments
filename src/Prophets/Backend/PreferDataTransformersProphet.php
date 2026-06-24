<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Flag a method that hand-maps a Spatie Data object's properties into an array
 * — that conversion belongs to the Data object itself (`->toArray()` plus
 * `#[WithTransformer]` / casts), not a bespoke serialiser at the call site.
 *
 *
 *
 *
 *
 *
 *
 * @method-generated-start
 * @method static dataBase(string $value)
 * @method static minReads(int $value)
 * @method-generated-end
 */
#[IntroducedIn('1.65.0')]
class PreferDataTransformersProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    private const DEFAULT_BASE = 'Spatie\\LaravelData\\Data';

    private const DEFAULT_MIN_READS = 3;

    private ?CodebaseIndex $index = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    public function description(): string
    {
        return 'Serialize Data objects through ->toArray()/transformers, not a hand-rolled mapping';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A method reads several properties of a Spatie Data object '
                . 'parameter and assembles them into an array — a hand-rolled '
                . 'serialiser. The Data object already knows how to become an '
                . 'array (`->toArray()`), and per-field shaping belongs on the '
                . 'Data class via `#[WithTransformer]` / casts.'
            )
            ->leaveWhen(
                'The output array is a genuinely different shape than the Data '
                . 'object — a projection that drops/renames/derives most fields '
                . 'for a specific wire contract — where a transformer on the Data '
                . 'class would be the wrong home.'
            )
            ->whenUnsure(
                'If the array mirrors the Data object\'s own fields with at most a '
                . 'few tweaks, move it to `->toArray()` + `#[WithTransformer]`. If '
                . 'it is a bespoke projection for one endpoint, leave it.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A Spatie Data object already knows how to serialise itself — `->toArray()`
runs its property-name mapping, casts, and transformers. Re-deriving that by
hand in a separate method duplicates the Data class's job and drifts from it.

Bad — a bespoke serialiser maps the Data object field-by-field:

    private function serialiseInput(InputSocket $port): array
    {
        return [
            'name'     => $port->name,
            'type'     => WireType::label($port->type),       // custom shaping
            'required' => $port->required,
            'nullable' => $port->nullable,
            'options'  => $port->options,
        ];
    }

Good — let the Data object transform itself; put the per-field shaping ON the
Data class:

    class InputSocket extends Data
    {
        public function __construct(
            public string $name,
            #[WithTransformer(WireTypeLabelTransformer::class)]
            public string $type,
            // …
        ) {}
    }

    $port->toArray();

Per-field rules live in `#[WithTransformer]` (one property), a global
transformer in `config/data.php` (a whole type), or a cast — see the Spatie
Laravel Data "transformers" docs. The fix is a design move (where each rule
lives), so it is NOT auto-fixed.

WHAT FIRES — a method where >= `min_reads` (default 3) distinct properties of
ONE parameter whose type is a `Spatie\LaravelData\Data` subclass (resolved
through the codebase index) flow into the VALUES of an array the method
returns — a property->value serialisation map.

WHAT DOES NOT — fewer mapped properties, a parameter that is not a Data class,
property reads that only drive logic (a validator reading `$field->type` in a
condition and returning error strings is NOT a serialiser), or (a cross-file
rule) anything when no codebase index could be built.

Configuration:

    Backend\PreferDataTransformersProphet::class => [
        'data_base' => 'Spatie\\LaravelData\\Data',
        'min_reads' => 3,
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->index === null) {
            return $this->righteous();
        }

        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $minReads = $this->minReads();
        $finder = new NodeFinder;
        $warnings = [];

        foreach ($this->namespaceScopes($ast) as [$namespace, $uses, $scope]) {
            /** @var array<Node\Stmt\ClassMethod> $methods */
            $methods = $finder->findInstanceOf($scope, Node\Stmt\ClassMethod::class);

            foreach ($methods as $method) {
                if ($method->stmts === null || ! $this->returnsArray($method)) {
                    continue;
                }

                foreach ($method->params as $param) {
                    $candidate = $this->dataParam($param, $uses, $namespace);

                    if ($candidate === null) {
                        continue;
                    }

                    [$varName, $short] = $candidate;
                    // Only a genuine SERIALISER counts: the Data object's
                    // properties must flow into the VALUES of the output array
                    // (a property->value map). A validator/domain method that
                    // merely reads props to drive logic and returns error
                    // strings or computed values is not hand-mapping (#16).
                    $reads = $this->propertiesFeedingArrayOutput($finder, $method, $varName);

                    if ($reads >= $minReads) {
                        $warnings[] = $this->warningAt(
                            $method->getStartLine(),
                            sprintf(
                                '%s() hand-maps %d properties of the Data object $%s into an array — use `$%s->toArray()` and move per-field shaping onto %s via `#[WithTransformer]` / casts, instead of a bespoke serialiser.',
                                $method->name->toString(),
                                $reads,
                                $varName,
                                $varName,
                                $short,
                            ),
                            null,
                            'data-handmap:' . $short,
                        );

                        break; // one finding per method
                    }
                }
            }
        }

        if ($warnings === []) {
            return $this->righteous();
        }

        return Judgment::withWarnings($warnings);
    }

    /**
     * The [varName, shortType] of a parameter typed as a Data subclass, or null.
     *
     * @param  array<string, string>  $uses
     * @return array{0: string, 1: string}|null
     */
    private function dataParam(Node\Param $param, array $uses, ?string $namespace): ?array
    {
        if (! $param->var instanceof Expr\Variable || ! is_string($param->var->name)) {
            return null;
        }

        $type = $param->type;

        if ($type instanceof Node\NullableType) {
            $type = $type->type;
        }

        if (! $type instanceof Node\Name) {
            return null;
        }

        $fqcn = $this->resolveFqcn($type, $uses, $namespace);

        if (! $this->isDataClass($fqcn)) {
            return null;
        }

        return [$param->var->name, $type->getLast()];
    }

    private function isDataClass(string $fqcn): bool
    {
        $base = ltrim((string) $this->config('data_base', self::DEFAULT_BASE), '\\');
        $baseShort = $this->shortName($base);
        $fqcn = ltrim($fqcn, '\\');
        $seen = [];

        while ($fqcn !== '' && ! isset($seen[$fqcn])) {
            $seen[$fqcn] = true;
            $summary = $this->index?->classByFqcn($fqcn);

            if ($summary === null) {
                return false;
            }

            $parent = $summary->parent !== null ? ltrim($summary->parent, '\\') : null;

            if ($parent === null) {
                return false;
            }

            if ($parent === $base || $this->shortName($parent) === $baseShort) {
                return true;
            }

            $fqcn = $parent;
        }

        return false;
    }

    /**
     * Count the DISTINCT properties of $varName whose reads flow into the VALUES
     * of an array the method returns — i.e. a property->value serialisation map.
     * Reads that only appear in conditions, or feed error/computed values, do
     * not count, so validators and domain methods are not misclassified (#16).
     */
    private function propertiesFeedingArrayOutput(NodeFinder $finder, Node\Stmt\ClassMethod $method, string $varName): int
    {
        $stmts = $method->stmts ?? [];

        // Local variables that are returned (`return $out;`) — array literals or
        // element assignments into these also count as output.
        $returnedVars = [];

        foreach ($finder->findInstanceOf($stmts, Node\Stmt\Return_::class) as $return) {
            if ($return->expr instanceof Expr\Variable && is_string($return->expr->name)) {
                $returnedVars[$return->expr->name] = true;
            }
        }

        /** @var array<Node> $containers Output value-carrying nodes. */
        $containers = [];

        // Directly returned array literals: `return [ ... ];`
        foreach ($finder->findInstanceOf($stmts, Node\Stmt\Return_::class) as $return) {
            if ($return->expr instanceof Expr\Array_) {
                $containers[] = $return->expr;
            }
        }

        foreach ($finder->findInstanceOf($stmts, Expr\Assign::class) as $assign) {
            // `$out = [ ... ];` where $out is returned.
            if ($assign->var instanceof Expr\Variable && is_string($assign->var->name)
                && isset($returnedVars[$assign->var->name]) && $assign->expr instanceof Expr\Array_
            ) {
                $containers[] = $assign->expr;
            }

            // `$out[...] = <expr>;` / `$out[] = <expr>;` where $out is returned.
            if ($assign->var instanceof Expr\ArrayDimFetch
                && $assign->var->var instanceof Expr\Variable
                && is_string($assign->var->var->name)
                && isset($returnedVars[$assign->var->var->name])
            ) {
                $containers[] = $assign->expr;
            }
        }

        $props = [];

        foreach ($containers as $container) {
            /** @var array<Expr\PropertyFetch> $fetches */
            $fetches = $finder->findInstanceOf([$container], Expr\PropertyFetch::class);

            foreach ($fetches as $fetch) {
                if ($fetch->var instanceof Expr\Variable && $fetch->var->name === $varName
                    && $fetch->name instanceof Node\Identifier
                ) {
                    $props[$fetch->name->toString()] = true;
                }
            }
        }

        return count($props);
    }

    private function returnsArray(Node\Stmt\ClassMethod $method): bool
    {
        $type = $method->returnType;

        if ($type instanceof Node\NullableType) {
            $type = $type->type;
        }

        if ($type instanceof Node\Identifier && strtolower($type->toString()) === 'array') {
            return true;
        }

        // No (or untyped) return type: accept when the method returns an array literal.
        $finder = new NodeFinder;

        foreach ($finder->findInstanceOf($method->stmts ?? [], Node\Stmt\Return_::class) as $return) {
            if ($return->expr instanceof Expr\Array_) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<Node>  $ast
     * @return list<array{0: ?string, 1: array<string, string>, 2: array<Node>}>
     */
    private function namespaceScopes(array $ast): array
    {
        $out = [];

        foreach ($ast as $node) {
            $namespace = null;
            $scope = [$node];

            if ($node instanceof Node\Stmt\Namespace_) {
                $namespace = $node->name?->toString();
                $scope = $node->stmts;
            }

            $out[] = [$namespace, $this->collectUses($scope), $scope];
        }

        return $out;
    }

    /**
     * @param  array<Node>  $stmts
     * @return array<string, string>
     */
    private function collectUses(array $stmts): array
    {
        $uses = [];

        foreach ($stmts as $stmt) {
            if (! $stmt instanceof Node\Stmt\Use_) {
                continue;
            }

            foreach ($stmt->uses as $useUse) {
                $alias = $useUse->alias?->toString() ?? $useUse->name->getLast();
                $uses[$alias] = $useUse->name->toString();
            }
        }

        return $uses;
    }

    /**
     * @param  array<string, string>  $uses
     */
    private function resolveFqcn(Node\Name $name, array $uses, ?string $namespace): string
    {
        if ($name->isFullyQualified()) {
            return ltrim($name->toString(), '\\');
        }

        $parts = explode('\\', $name->toString());
        $first = $parts[0];

        if (isset($uses[$first])) {
            $parts[0] = $uses[$first];

            return implode('\\', $parts);
        }

        if ($namespace !== null && $namespace !== '') {
            return $namespace . '\\' . $name->toString();
        }

        return $name->toString();
    }

    private function minReads(): int
    {
        $value = $this->config('min_reads', self::DEFAULT_MIN_READS);

        return is_numeric($value) ? max(2, (int) $value) : self::DEFAULT_MIN_READS;
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }
}
