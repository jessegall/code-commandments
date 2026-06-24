<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Results\Tier;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Flag `transform(cb)->getOr(Option::none())` where the callback returns an
 * Option — an `Option<Option>` flattened by hand, which is exactly `andThen(cb)`.
 * See {@see detailedDescription()} for the full rationale.
 *
 *
 *
 *
 * @method-generated-start
 * @method static getOrMethod(string $value)
 * @method static mapMethods(array $value)
 * @method static optionClass(string $value)
 * @method-generated-end
 */
#[IntroducedIn('1.123.0')]
class PreferAndThenProphet extends PhpCommandment implements SinRepenter
{
    /** Value -> value mappers that should be andThen when the callback returns an Option. */
    private const DEFAULT_MAP_METHODS = ['transform', 'map'];

    public function description(): string
    {
        return 'Use Option::andThen() instead of transform()->getOr(Option::none()) — do not flatten an Option<Option> by hand';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Correctness;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A `->transform(...)` / `->map(...)` whose callback returns an Option is immediately unwrapped with `->getOr(Option::none())` — that is an Option<Option> flattened by hand, which is exactly `->andThen(...)`.')
            ->leaveWhen('the `getOr()` default is a real value (not `none()`), or the callback genuinely returns a plain value (then the transform is correct and the default is the fallback).')
            ->whenUnsure('if the `getOr()` default is `Option::none()`/`self::none()`, the chain is producing an Option<Option> — collapse the `transform()->getOr(none)` to a single `andThen()`.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
`transform()` (value -> value) is the wrong combinator when the callback returns
an Option: it nests, producing `Option<Option>`, and the only reason to follow it
with `->getOr(Option::none())` is to unwrap that extra layer. `andThen()` (value
-> Option) flattens in one step.

Bad — nest, then unwrap by hand:
    return $graph->nodeById($id)
        ->transform(fn ($n) => $this->descriptors->descriptorForNode($n))
        ->getOr(Option::none());

Good — andThen flattens:
    return $graph->nodeById($id)
        ->andThen(fn ($n) => $this->descriptors->descriptorForNode($n));

WHAT FIRES — a `->transform(...)`/`->map(...)` call chained directly into
`->getOr(X)` where X is a `none()` static call (`Option::none()`, `self::none()`,
`static::none()`). The `none()` default is the tell that the value is itself an
Option.

This is [AUTO-FIXABLE]: `repent` drops the `->getOr(none())` and renames the
`transform`/`map` to `andThen`.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $warnings = [];

        foreach ($this->matches($ast) as $match) {
            $line = $match['getOr']->getStartLine();
            $warnings[] = $this->warningAt(
                $line,
                sprintf('`%s(...)->getOr(%s::none())` flattens an Option<Option> by hand — use `->andThen(...)`.', $match['mapMethod'], $match['noneClass']),
                $this->lineSnippet($content, $line),
                'prefer-andthen',
                true,
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    public function canRepent(string $filePath): bool
    {
        return pathinfo($filePath, PATHINFO_EXTENSION) === 'php';
    }

    public function repent(string $filePath, string $content): RepentanceResult
    {
        if (! $this->canRepent($filePath)) {
            return RepentanceResult::unchanged();
        }

        $ast = $this->parse($content);

        if ($ast === null) {
            return RepentanceResult::unrepentant('Unable to parse PHP file');
        }

        $edits = [];
        $penance = [];

        foreach ($this->matches($ast) as $match) {
            $getOr = $match['getOr'];
            $map = $match['map'];
            $recv = $map->var;

            $recvSrc = substr($content, (int) $recv->getStartFilePos(), (int) $recv->getEndFilePos() - (int) $recv->getStartFilePos() + 1);
            $argsSrc = $this->argsSource($content, $map);

            $edits[] = [
                'start' => (int) $getOr->getStartFilePos(),
                'end' => (int) $getOr->getEndFilePos(),
                'text' => $recvSrc . '->andThen(' . $argsSrc . ')',
            ];
            $penance[] = sprintf('Collapsed %s()->getOr(none()) to andThen()', $match['mapMethod']);
        }

        if ($edits === []) {
            return RepentanceResult::unchanged();
        }

        usort($edits, static fn (array $a, array $b): int => $b['start'] <=> $a['start']);

        foreach ($edits as $edit) {
            $content = substr($content, 0, $edit['start']) . $edit['text'] . substr($content, $edit['end'] + 1);
        }

        return RepentanceResult::absolved($content, $penance);
    }

    /**
     * @param  array<Node>  $ast
     * @return list<array{getOr: Node\Expr\MethodCall, map: Node\Expr\MethodCall, mapMethod: string, noneClass: string}>
     */
    private function matches(array $ast): array
    {
        $mapMethods = $this->mapMethods();
        $getOr = (string) $this->config('get_or_method', 'getOr');
        $out = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Expr\MethodCall::class) as $call) {
            if (! $call->name instanceof Node\Identifier || $call->name->toString() !== $getOr
                || count($call->args) !== 1 || ! $call->args[0] instanceof Node\Arg
            ) {
                continue;
            }

            $noneClass = $this->noneClassOf($call->args[0]->value);

            if ($noneClass === null) {
                continue;
            }

            $map = $call->var;

            if (! $map instanceof Node\Expr\MethodCall
                || ! $map->name instanceof Node\Identifier
                || ! in_array($map->name->toString(), $mapMethods, true)
                || $map->args === []
            ) {
                continue;
            }

            $out[] = ['getOr' => $call, 'map' => $map, 'mapMethod' => $map->name->toString(), 'noneClass' => $noneClass];
        }

        return $out;
    }

    /**
     * The class short name when $node is a no-arg `X::none()` whose receiver is
     * the Option type (self/static or a class short-named like the option class),
     * else null.
     */
    private function noneClassOf(Node $node): ?string
    {
        if (! $node instanceof Node\Expr\StaticCall
            || ! $node->name instanceof Node\Identifier
            || $node->name->toString() !== 'none'
            || $node->args !== []
            || ! $node->class instanceof Node\Name
        ) {
            return null;
        }

        $short = $node->class->getLast();
        $optionClass = (string) $this->config('option_class', 'Option');

        if (in_array($short, ['self', 'static'], true) || $short === $optionClass || str_ends_with($short, 'Option')) {
            return $short;
        }

        return null;
    }


    private function argsSource(string $content, Node\Expr\MethodCall $call): string
    {
        $first = $call->args[array_key_first($call->args)];
        $last = $call->args[array_key_last($call->args)];

        return substr($content, (int) $first->getStartFilePos(), (int) $last->getEndFilePos() - (int) $first->getStartFilePos() + 1);
    }

    /**
     * @return list<string>
     */
    private function mapMethods(): array
    {
        $methods = $this->config('map_methods', self::DEFAULT_MAP_METHODS);

        return is_array($methods) && $methods !== [] ? array_values(array_map('strval', $methods)) : self::DEFAULT_MAP_METHODS;
    }
}
