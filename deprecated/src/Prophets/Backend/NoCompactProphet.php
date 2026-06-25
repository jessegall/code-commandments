<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Flag `compact()` and `extract()` — the magic bridges between variable names
 * and array keys. `compact('a', 'b')` assembles an array from variable names
 * passed as STRINGS (rename-unsafe, typo-prone, invisible to static analysis);
 * `extract($arr)` does the reverse, spraying unpredictable locals into scope.
 * Pass data explicitly instead.
 */
#[IntroducedIn('1.79.0')]
class NoCompactProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Do not bridge variables and arrays by name with compact()/extract()';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A call to `compact(...)` assembles an array from variable names '
                . 'given as strings, or `extract(...)` sprays array keys into local '
                . 'variables — magic that is rename-unsafe and invisible to static '
                . 'analysis.'
            )
            ->leaveWhen(
                'Never, really. There is always an explicit form. (If a legacy '
                . 'view helper forces compact(), isolate it at that one boundary.)'
            )
            ->whenUnsure(
                'Write the array literal `[\'name\' => $name, ...]`, pass named '
                . 'arguments, or hand over the typed object. Explicit beats magic.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
`compact()` and `extract()` couple variable names to array keys through STRINGS,
so a rename silently breaks them, a typo fails at runtime, and no tool can see
the data flow. They are a frequent dodge, too: `Data::from(compact(...))` packs
typed parameters into an array purely so `from()` receives an array instead of
an object — hiding a hand-rolled hydration behind a magic function.

Bad — assemble an array from variable names:
    return static::from(compact('name', 'type', 'required', 'nullable'));

    return view('profile', compact('user', 'posts'));

Bad — unpack array keys into locals:
    extract($payload);   // where did $name, $type, … come from?

Good — be explicit:
    return static::from([
        'name'     => $name,
        'type'     => $type,
        'required' => $required,
        'nullable' => $nullable,
    ]);

    // or pass named arguments / the typed object directly:
    return new static(name: $name, type: $type, required: $required, nullable: $nullable);

WHAT FIRES — a call to the `compact()` or `extract()` function.

WHAT DOES NOT — a method named compact()/extract() on an object
(`$collection->compact()`), which is unrelated.

Configure via:

    Backend\NoCompactProphet::class => [
        'functions' => ['compact', 'extract'],
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $functions = $this->functions();
        $warnings = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Expr\FuncCall::class) as $call) {
            if (! $call->name instanceof Node\Name) {
                continue;
            }

            $fn = strtolower($call->name->getLast());

            if (! in_array($fn, $functions, true)) {
                continue;
            }

            $warnings[] = $this->warningAt(
                $call->getStartLine(),
                $fn === 'extract'
                    ? '`extract()` sprays array keys into local variables — hidden, unpredictable locals invisible to static analysis. Access the array values explicitly instead.'
                    : '`compact()` assembles an array from variable names passed as strings — rename-unsafe, typo-prone, and a common dodge for hiding hand-rolled hydration behind `from(compact(...))`. Write the array literal `[\'name\' => $name, ...]`, pass named arguments, or hand over the typed object.',
                $this->lineSnippet($content, $call->getStartLine()),
                'compact:' . $fn,
            );
        }

        if ($warnings === []) {
            return $this->righteous();
        }

        return Judgment::withWarnings($warnings);
    }

    /**
     * @return list<string>
     */
    private function functions(): array
    {
        $configured = $this->config('functions', ['compact', 'extract']);

        return is_array($configured) && $configured !== []
            ? array_values(array_map('strtolower', array_filter($configured, 'is_string')))
            : ['compact', 'extract'];
    }

}
