<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\DataFactorySynthesizer;
use JesseGall\CodeCommandments\Support\FromArrayOnlyPolicy;
use JesseGall\CodeCommandments\Support\PackageDetector;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Php\FindImplicitDataFrom;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/**
 * Keep Spatie Data construction explicit: `from()` takes an array, never the
 * magic object dispatch; object→Data mapping lives in named fromX() factories.
 * The mechanical cases — from(empty array) and bare new self() — are
 * auto-fixable to ::make().
 */
#[IntroducedIn('1.40.0')]
class ExplicitDataFactoryProphet extends PhpCommandment implements SinRepenter, NeedsCodebaseIndex
{
    private const EMPTY_ARRAY_CALLS = ['T_Array::empty', 'Arr::empty'];

    private ?CodebaseIndex $index = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    public function supported(): bool
    {
        return PackageDetector::hasSpatieData();
    }

    public function description(): string
    {
        return 'Keep Data construction explicit — from() takes an array; map objects in named forX() factories';
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A Data object is built with from() on a non-array (a model/object), '
                . 'with a ->toArray() bypass at a call site, or with new self()/new static() '
                . 'in a factory — all of which hide the mapping in magic or boilerplate.'
            )
            ->leaveWhen(
                'The construction is genuinely array-in (from([...]) / from($arrayParam)), or '
                . 'the type cannot be resolved, in which case this prophet stays silent.'
            )
            ->whenUnsure(
                'Add an explicit `forX(Type $x): static` factory that does '
                . 'static::from($x->toArray()); call that from the outside. Use a '
                . '`for` prefix, never `from` (reserved for Spatie\'s magic ::from()).'
            );
    }

    protected function defaultTier(): Tier
    {
        return Tier::Structural;
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Spatie Data's `::from()` is magic: it dispatches by argument type to fromX()
methods, and falls back to calling toArray()/all() on models, requests and
arrayables. Convenient, but at a call site you cannot see which path it
takes. Keep it explicit: `from()` takes an ARRAY; everything else goes
through a named factory.

Bad — magic object dispatch:
    $data = SongData::from($song);          // model → magic / toArray fallback
    $data = SongData::from($request);       // request → all()
    $data = SongData::from($this->song);    // object property

Bad — toArray bypass at a call site (the conversion belongs in a factory):
    $data = SongData::from($song->toArray());

Bad — hand construction in a factory:
    public static function forSong(Song $song): self
    {
        return new self(title: $song->title, artist: $song->artist);
    }

Good — explicit factory, array hydration encapsulated inside the class:
    public static function forSong(Song $song): self
    {
        return static::from($song->toArray());
        // or: return static::from(['title' => $song->title, ...]);
    }

    // call sites stay explicit (never an external ::from()):
    $data = SongData::forSong($song);
    $data = SongData::forArray(['title' => 'x', 'artist' => 'y']);   // explicit array entry

Factories use a `for` prefix, NEVER `from`: the `from` prefix is reserved for
Spatie's magic ::from(), and a same-typed `from*` factory makes ::from() recurse
→ segfault (see NoExternalDataFrom). External call sites use the named `forX()`
factories or `forArray([...])` — never a bare `SomeData::from(...)`.

Enums are unaffected: `Status::from($row->status)` passes a scalar, never an
object, so the object check never touches it.

Argument types are resolved from the AST (parameter hints — including inside
closures/arrow functions — $this, property types, new, ->toArray()); when a
type cannot be resolved the prophet stays silent rather than guess.

The object dispatch is AUTO-FIXABLE: `repent` rewrites `XData::from($obj)` to
`XData::for{Type}($obj)` and synthesises the matching factory on XData —
`public static function for{Type}(\Fqcn\Type $x): static { return
static::from($x->toArray()); }` — wherever XData is defined, cross-referencing
the codebase index so the factory lands even when the Data class sits outside
the scoped/flagged files. An unreachable Data class is left for a human.

The auto-fix BAILS (leaves the call for a human, no lossy body) when a
`static::from($x->toArray())` factory would not be behavior-preserving: the
target Data class — or any ancestor — carries `#[LoadRelation]`, `#[MapInputName]`,
`#[MapName]` or `#[Computed]` (toArray() does not reproduce the magic
from(Model) path), or the argument is a Request (its magic reads ->all()). Those
need a human to write the right factory.

Generated factories always declare `: static` (correct for `return static::from`
and consistent across an inheritance chain). A factory that declares a CONCRETE
return type instead is an incompatible override of an inherited `: static` and
fatals at class-load — judge flags it and repent normalises it to `static`.

Pairs with the generated FromArrayOnly trait, which enforces the same rule at
runtime (assert) for the cases static analysis cannot see.

Configure via:

    Backend\ExplicitDataFactoryProphet::class => [
        'data_suffixes' => ['Data'],   // base-class suffixes that mark a Data class
        'severity' => 'warning',       // or 'sin' to block commits
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $pipe = (new FindImplicitDataFrom)->withDataSuffixes($this->resolveSuffixes());

        $judgment = PhpPipeline::make($filePath, $content)
            ->pipe(ParsePhpAst::class)
            ->pipe($pipe)
            ->partitionMatches($this->translate(...))
            ->judge();

        // #51: also flag a factory `fromX(...): Concrete { return static::from(...); }`
        // — a concrete return type is an incompatible override of an inherited
        // `: static` and fatals at class-load. Auto-fixable (repent normalises it).
        $extra = $this->concreteReturnFindings($content);

        if ($extra === []) {
            return $judgment;
        }

        $isSin = $this->config('severity', 'warning') === 'sin';

        return new Judgment(
            sins: $isSin ? [...$judgment->sins, ...$extra] : $judgment->sins,
            warnings: $isSin ? $judgment->warnings : [...$judgment->warnings, ...$extra],
        );
    }

    /**
     * Findings for generated-shape factories declaring a concrete return type
     * where the body is `return static::from(...)` — should be `: static`.
     *
     * @return list<Sin|Warning>
     */
    private function concreteReturnFindings(string $content): array
    {
        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($content);

        if ($ast === null) {
            return [];
        }

        $synth = new DataFactorySynthesizer;
        $findings = [];
        $isSin = $this->config('severity', 'warning') === 'sin';

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            foreach ($class->getMethods() as $method) {
                if (! $synth->isConcreteReturnStaticFromFactory($method)) {
                    continue;
                }

                $line = $method->getStartLine();
                $name = $method->name->toString();
                $message = sprintf(
                    '%s() declares a concrete return type but its body returns static::from() — a concrete return is an incompatible override of an inherited `: static` (fatal at class-load). Use `: static`.',
                    $name,
                );
                $suggestion = 'AUTO-FIXABLE: run repent to change the return type to `static`.';
                $symbol = 'concrete-return:' . $name;

                $findings[] = $isSin
                    ? $this->sinAt($line, $message, null, $suggestion, $symbol, true)
                    : $this->warningAt($line, $message . ' ' . $suggestion, null, $symbol, true);
            }
        }

        return $findings;
    }

    private function translate(MatchResult $match): Sin|Warning
    {
        $message = $this->messageFor($match);
        $suggestion = $this->suggestionFor($match);
        $symbol = $match->groups['target'] . ':' . $match->groups['kind'];

        // empty_from / new_default rewrite to ::make(); the object dispatch
        // (nonarray) is now auto-fixable too — repent synthesises the fromX()
        // factory (cross-file via the index) and rewrites the call. Field-by-
        // field new self() (new_mapping) and the toArray bypass still need a human.
        $autoFixable = in_array($match->groups['kind'], ['empty_from', 'new_default', 'nonarray'], true);

        // #65: empty_from -> ::make() only works when the class keeps the
        // FromArrayOnly trait; if it has an object ::from() site the trait (and
        // make()) is withheld, so the rewrite is not auto-fixable here either.
        if ($match->groups['kind'] === 'empty_from' && $this->index !== null
            && isset(FromArrayOnlyPolicy::traitUnsafeShortNames($this->index, $this->resolveSuffixes())[$match->groups['target']])
        ) {
            $autoFixable = false;
        }

        if ($this->config('severity', 'warning') === 'sin') {
            return $this->sinAt($match->line, $message, $match->content, $suggestion, $symbol, $autoFixable);
        }

        return $this->warningAt($match->line, $message . ' ' . $suggestion, $match->content, $symbol, $autoFixable);
    }

    private function messageFor(MatchResult $match): string
    {
        $target = $match->groups['target'];

        return match ($match->groups['kind']) {
            'empty_from' => sprintf(
                '%s::from() is handed an empty array — that reads as a default. Use %s::make() instead.',
                $target,
                $target,
            ),
            'toarray_outside' => sprintf(
                '%s::from($x->toArray()) converts an object to an array at the call site — that bypass belongs in a factory.',
                $target,
            ),
            'new_default' => sprintf(
                'new %s() builds a default instance by hand — use %s::make() instead.',
                $target,
                $target,
            ),
            'new_mapping' => sprintf(
                'new %s() constructs the Data object field-by-field in a factory — hydrate through static::from(array) instead.',
                $target,
            ),
            default => sprintf(
                '%s::from() is given a non-array (object) — that is the magic dispatch; from() should take an array.',
                $target,
            ),
        };
    }

    private function suggestionFor(MatchResult $match): string
    {
        $target = $match->groups['target'];

        if (in_array($match->groups['kind'], ['empty_from', 'new_default'], true)) {
            return sprintf('AUTO-FIXABLE: run repent to rewrite this to %s::make().', $target);
        }

        if ($match->groups['kind'] === 'nonarray') {
            return sprintf(
                'AUTO-FIXABLE: run repent to synthesise %s::forType(Type $x) and rewrite this call to it.',
                $target,
            );
        }

        return sprintf(
            'Add an explicit `%s::forX(Type $x): static` factory that does static::from($x->toArray()), and call that instead (use a `for` prefix, never `from`).',
            $target,
        );
    }

    public function canRepent(string $filePath): bool
    {
        return pathinfo($filePath, PATHINFO_EXTENSION) === 'php';
    }

    /**
     * Auto-fix the mechanical cases: `X::from(<empty array>)` and a bare
     * `new X()` default-construction in a Data factory both become
     * `X::make()`. Object-args and field-by-field construction need a human
     * factory and are left alone.
     */
    public function repent(string $filePath, string $content): RepentanceResult
    {
        if (! $this->canRepent($filePath)) {
            return RepentanceResult::unchanged();
        }

        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($content);

        if ($ast === null) {
            return RepentanceResult::unrepentant('Unable to parse PHP file');
        }

        // Resolve names (FQCNs + class namespacedName) without replacing the
        // original nodes, so byte positions stay intact for the edits below
        // while the synthesizer can read `resolvedName` to qualify types.
        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NameResolver(null, ['replaceNodes' => false]));
        $traverser->traverse($ast);

        $nodeFinder = new NodeFinder;
        $edits = [];
        $penance = [];
        $createdFiles = [];

        // #44: object-typed X::from($obj) -> X::from{Type}($obj) + synthesise the
        // matching factory on X (in this file or, via the index, wherever X lives).
        $synth = (new DataFactorySynthesizer)->synthesize($filePath, $content, $ast, $this->index, $this->resolveSuffixes());
        $edits = array_merge($edits, $synth['edits']);
        $penance = array_merge($penance, $synth['penance']);
        $createdFiles = $synth['createdFiles'];

        // X::from(<empty array>) -> X::make(). #65: make() comes from the
        // FromArrayOnly trait, which DataClassFromArrayOnly withholds from any
        // class that still has an object ::from() site. Rewriting to ::make()
        // there would call an undefined method, so gate on the SAME census.
        $traitUnsafe = $this->index !== null
            ? FromArrayOnlyPolicy::traitUnsafeShortNames($this->index, $this->resolveSuffixes())
            : [];

        foreach ($nodeFinder->findInstanceOf($ast, Node\Expr\StaticCall::class) as $call) {
            if (! $call->name instanceof Node\Identifier || $call->name->toString() !== 'from'
                || ! $call->class instanceof Node\Name || count($call->args) !== 1
                || ! $call->args[0] instanceof Node\Arg || ! $this->isEmptyArray($call->args[0]->value)
            ) {
                continue;
            }

            if (isset($traitUnsafe[$call->class->getLast()])) {
                continue; // the trait (and make()) is withheld from this class
            }

            $edits[] = $this->replace($call, $call->class->getLast() . '::make()');
            $penance[] = 'Rewrote from(empty array) to ::make()';
        }

        // bare new self()/static() in a static method of a Data class -> X::make()
        foreach ($nodeFinder->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if (! $this->classIsData($class)) {
                continue;
            }

            $ownName = $class->name?->toString();

            foreach ($class->getMethods() as $method) {
                if (! $method->isStatic() || $method->stmts === null) {
                    continue;
                }

                foreach ($nodeFinder->findInstanceOf($method->stmts, Node\Expr\New_::class) as $new) {
                    if ($new->args !== [] || ! $new->class instanceof Node\Name) {
                        continue;
                    }

                    $short = $new->class->getLast();

                    if (! in_array($short, ['self', 'static'], true) && $short !== $ownName) {
                        continue;
                    }

                    $edits[] = $this->replace($new, $short . '::make()');
                    $penance[] = 'Rewrote new ' . $short . '() to ::make()';
                }
            }
        }

        if ($edits === [] && $createdFiles === []) {
            return RepentanceResult::unchanged();
        }

        usort($edits, fn ($a, $b) => $b['start'] <=> $a['start']);

        foreach ($edits as $edit) {
            $content = substr($content, 0, $edit['start']) . $edit['text'] . substr($content, $edit['end'] + 1);
        }

        return RepentanceResult::absolved($content, $penance, createdFiles: $createdFiles);
    }

    /**
     * @return array{start: int, end: int, text: string}
     */
    private function replace(Node $node, string $text): array
    {
        return ['start' => (int) $node->getStartFilePos(), 'end' => (int) $node->getEndFilePos(), 'text' => $text];
    }

    private function isEmptyArray(Node $arg): bool
    {
        if ($arg instanceof Node\Expr\Array_) {
            return $arg->items === [];
        }

        if ($arg instanceof Node\Expr\StaticCall
            && $arg->class instanceof Node\Name
            && $arg->name instanceof Node\Identifier
        ) {
            return in_array($arg->class->getLast() . '::' . $arg->name->toString(), self::EMPTY_ARRAY_CALLS, true);
        }

        return $arg instanceof Node\Expr\FuncCall
            && $arg->name instanceof Node\Name
            && $arg->name->toString() === 'array'
            && $arg->args === [];
    }

    private function classIsData(Node\Stmt\Class_ $class): bool
    {
        if (! $class->extends instanceof Node\Name) {
            return false;
        }

        $parent = $class->extends->getLast();

        foreach ($this->resolveSuffixes() as $suffix) {
            if (str_ends_with($parent, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function resolveSuffixes(): array
    {
        $suffixes = $this->config('data_suffixes', ['Data']);

        return is_array($suffixes) && $suffixes !== [] ? array_values($suffixes) : ['Data'];
    }
}
