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
use JesseGall\CodeCommandments\Support\DataFromSiteCensus;
use JesseGall\CodeCommandments\Support\FromArrayOnlyPolicy;
use JesseGall\CodeCommandments\Support\PackageDetector;
use JesseGall\CodeCommandments\Support\SpatieDataMagic;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Every class that extends Spatie Data directly must `use` the FromArrayOnly
 * trait, so `::from()` is guarded against the magic object dispatch at
 * runtime. Mechanical, so it is auto-fixable.
 *
 *
 *
 *
 *
 *
 * @method-generated-start
 * @method static baseClass(string $value)
 * @method static dataSuffixes(array $value)
 * @method static traitClass(string $value)
 * @method-generated-end
 */
#[IntroducedIn('1.41.0')]
class DataClassFromArrayOnlyProphet extends PhpCommandment implements SinRepenter, NeedsCodebaseIndex
{
    private const DEFAULT_BASE = 'Spatie\\LaravelData\\Data';
    private const DEFAULT_TRAIT = 'App\\Support\\FromArrayOnly';

    private ?CodebaseIndex $index = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    /**
     * #64: whether class $shortName has an object `::from()` call site in ANY
     * file (not just its own) — in which case the array-only trait must be
     * withheld, since the assert would fatal on that object call.
     */
    private function hasCrossFileObjectFrom(string $shortName): bool
    {
        if ($this->index === null || $shortName === '') {
            return false;
        }

        return isset(DataFromSiteCensus::objectFromShortNames($this->index, $this->dataSuffixes())[$shortName]);
    }

    /**
     * #80: whether class $shortName has POSITIVE proof — a provable-array `::from()`
     * site somewhere. Only then is it safe to add the trait. Without an index
     * (a bare unit test) the gate is skipped so existing behaviour is preserved.
     */
    private function hasArrayProof(string $shortName): bool
    {
        if ($this->index === null) {
            return true;
        }

        return $shortName !== '' && isset(FromArrayOnlyPolicy::arrayProvenShortNames($this->index, $this->dataSuffixes())[$shortName]);
    }

    /**
     * @return list<string>
     */
    private function dataSuffixes(): array
    {
        $suffixes = $this->config('data_suffixes', ['Data']);

        return is_array($suffixes) && $suffixes !== [] ? array_values($suffixes) : ['Data'];
    }

    public function supported(): bool
    {
        return PackageDetector::hasSpatieData();
    }

    public function description(): string
    {
        return 'Every Data class must use the FromArrayOnly trait';
    }

    public function advisory(): ?Advisory
    {
        if ($this->config('severity', 'sin') === 'sin') {
            return null;
        }

        return Advisory::make()
            ->applyWhen('The class extends Spatie Data directly and does not use the FromArrayOnly trait.')
            ->leaveWhen('A base Data class already carries the trait (then put it there, not on every subclass), or you are mid-migration and still have ::from(object) call sites that the trait\'s assert would break.')
            ->whenUnsure('Add the trait — it is mechanical. Run `commandments repent` once the ::from() call sites are array-only.');
    }

    protected function defaultTier(): Tier
    {
        return Tier::Structural;
    }

    public function detailedDescription(): string
    {
        $trait = $this->traitFqcn();

        return <<<SCRIPTURE
A class that extends Spatie Data directly must `use` the FromArrayOnly trait
so that ::from() is guarded against the magic object dispatch — from() must
be handed an array; object→Data mapping lives in explicit fromX() factories.
The trait makes that a runtime guarantee (an assert that compiles out in
production) to back the static ExplicitDataFactoryProphet.

Bad:
    final class SongData extends Data
    {
        // ...
    }

Good:
    use {$trait};

    final class SongData extends Data
    {
        use FromArrayOnly;

        // ...
    }

Put it on a shared base Data class and every subclass inherits it — then only
the base (which extends Data directly) is flagged. This is auto-fixable:
`commandments repent` adds the `use` and the import.

Generate the trait with `commandments:scaffold`. Configure via:

    Backend\DataClassFromArrayOnlyProphet::class => [
        'trait_class' => App\\Support\\FromArrayOnly::class,
        'base_class'  => Spatie\\LaravelData\\Data::class,
        'severity'    => 'sin',   // 'warning' while migrating ::from() call sites
    ],

Note: the trait asserts from()-takes-an-array at runtime. If your code still
has ::from(object) call sites (see ExplicitDataFactoryProphet), set severity
to 'warning' and fix those first, or the assert will fire in dev/test.

EXEMPT: a class whose hierarchy depends on Spatie's magic from(Model) — it OR
any subclass that would inherit the trait carries #[LoadRelation], #[MapInputName],
#[MapName] or #[Computed] — is skipped entirely. Such a class legitimately needs
from(Model), so ExplicitDataFactory won't convert its ::from() sites and this rule
must not add the array-only assert (it would fatal). The two prophets stay coupled:
a Data class gets EITHER (trait + every object ::from() converted) OR neither.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parseAst($content);

        if ($ast === null) {
            return $this->righteous();
        }

        [$namespace, $uses] = $this->context($ast);
        $traitShort = $this->traitShort();
        $isSin = $this->config('severity', 'sin') === 'sin';
        $sins = [];
        $warnings = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if ($this->isUninstantiableData($class)) {
                continue;
            }

            if (! $this->lacksTraitAccess($class, $uses, $namespace)) {
                continue;
            }

            // A class whose hierarchy depends on Spatie's magic from(Model)
            // (#[LoadRelation]/#[MapInputName]/#[MapName]/#[Computed] on itself
            // OR any subclass that would inherit the trait) CANNOT be array-only —
            // ExplicitDataFactory rightly won't convert its object ::from() sites
            // (#47), so adding the runtime assert would fatal (#49). Exempt it.
            if ($this->dependsOnMagic($class, $namespace)) {
                continue;
            }

            $name = $class->name?->toString() ?? 'class';
            $line = $class->getStartLine();
            $snippet = $this->lineSnippet($content, $line);

            // A class whose ::from() is handed a non-array — its OWN factories
            // (issue #14) OR a call site in ANY other file (#64, via the
            // cross-file census) — is NOT safely auto-fixable: adding the trait
            // makes its array-assert throw at runtime. Flag it, but route the
            // agent to migrate those call sites first.
            if ($this->hasUnsafeSelfFrom($class) || $this->hasCrossFileObjectFrom($name)) {
                $message = "{$name} is a Data class without the {$traitShort} trait, but its own static factories call ::from() on a non-array — adding the trait now would make those throw at runtime.";
                $suggestion = "First migrate the `self::from(\$object)` call sites to `self::from(\$object->toArray())` (see ExplicitDataFactoryProphet); once ::from() is array-only, `commandments repent` can add the trait. NOT auto-fixable yet.";

                if ($isSin) {
                    $sins[] = $this->sinAt($line, $message, $snippet, $suggestion, $name, false);
                } else {
                    $warnings[] = $this->warningAt($line, $message . ' ' . $suggestion, $snippet, $name, false);
                }

                continue;
            }

            // #80: FAIL-SAFE — only add the trait with POSITIVE proof that a
            // `::from()` site passes an array. A class with no visible `::from()`
            // (a Blade / Inertia / view-hydrated class) cannot be proven safe, so
            // the trait is withheld rather than added optimistically.
            if (! $this->hasArrayProof($name)) {
                continue;
            }

            $message = "{$name} is a Data class without access to the {$traitShort} trait (not on it or any ancestor).";
            $suggestion = "Add `use {$traitShort};` to the class (and import {$this->traitFqcn()}). Run `commandments repent` to fix automatically.";

            if ($isSin) {
                $sins[] = $this->sinAt($line, $message, $snippet, $suggestion, $name, true);
            } else {
                $warnings[] = $this->warningAt($line, $message . ' ' . $suggestion, $snippet, $name, true);
            }
        }

        if ($sins !== []) {
            return $this->fallen($sins);
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

        $ast = $this->parseAst($content);

        if ($ast === null) {
            return RepentanceResult::unrepentant('Unable to parse PHP file');
        }

        [$namespace, $uses] = $this->context($ast);
        $traitShort = $this->traitShort();
        $edits = [];
        $penance = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if ($this->isUninstantiableData($class)) {
                continue;
            }

            if (! $this->extendsDataBase($class, $uses, $namespace) || $this->usesTrait($class, $traitShort)) {
                continue;
            }

            // Never add the array-only trait to a magic-dependent hierarchy (#49)
            // — it (or a subclass inheriting the trait) needs the magic from(Model)
            // path, so the assert would fatal at runtime.
            if ($this->dependsOnMagic($class, $namespace)) {
                continue;
            }

            // Do NOT add the trait to a class whose ::from() is handed a
            // non-array — its own factories (issue #14) OR a call site in any
            // other file (#64) — the trait's runtime assert would throw. Those
            // call sites must be migrated first.
            if ($this->hasUnsafeSelfFrom($class) || $this->hasCrossFileObjectFrom($class->name?->toString() ?? '')) {
                continue;
            }

            // #80: positive proof required — never add the trait to a class with
            // no provable-array ::from() site (a framework/view-hydrated class).
            if (! $this->hasArrayProof($class->name?->toString() ?? '')) {
                continue;
            }

            $brace = strpos($content, '{', (int) $class->getStartFilePos());

            if ($brace === false) {
                continue;
            }

            $edits[] = ['start' => $brace + 1, 'end' => $brace, 'text' => "\n    use {$traitShort};\n"];
            $penance[] = "Added `use {$traitShort};` to {$class->name?->toString()}";
        }

        if ($edits === []) {
            return RepentanceResult::unchanged();
        }

        if (! isset($uses[$this->traitFqcn()])) {
            $insert = $this->importInsertion($ast, $content);

            if ($insert !== null) {
                $edits[] = $insert;
            }
        }

        usort($edits, fn ($a, $b) => $b['start'] <=> $a['start']);

        foreach ($edits as $edit) {
            $content = substr($content, 0, $edit['start']) . $edit['text'] . substr($content, $edit['end'] + 1);
        }

        return RepentanceResult::absolved($content, $penance);
    }

    /**
     * Whether this class is a Data class (extends the Data base, possibly
     * transitively) that has NO access to the trait — neither on itself nor
     * on any ancestor. With the cross-file index this checks the whole chain
     * ("does the base already provide it?"); without it, it falls back to
     * flagging only classes that extend the Data base directly.
     *
     * @param  array<string, string>  $uses
     */
    /**
     * #87: classes that must NEVER receive the trait because the override would
     * break instantiation/morphing:
     *  - an ABSTRACT class is never `new`-ed directly; FromArrayOnly::from() calls
     *    parent::from(), defeating the polymorphic morph and making Spatie try to
     *    instantiate the abstract base; and
     *  - a `PropertyMorphableData` class whose `::from()` is INTENTIONALLY the
     *    morphing one (it resolves to a concrete subclass) — the override would
     *    short-circuit the morph.
     */
    private function isUninstantiableData(Node\Stmt\Class_ $class): bool
    {
        if ($class->isAbstract()) {
            return true;
        }

        foreach ($class->implements as $interface) {
            if ($interface->getLast() === 'PropertyMorphableData') {
                return true;
            }
        }

        return false;
    }

    private function lacksTraitAccess(Node\Stmt\Class_ $class, array $uses, ?string $namespace): bool
    {
        if (! $class->extends instanceof Node\Name) {
            return false;
        }

        if ($this->usesTrait($class, $this->traitShort())) {
            return false;
        }

        $parentFqcn = $this->resolve($class->extends, $uses, $namespace);

        if ($this->index !== null) {
            [$isData, $hasTrait] = $this->walkChain($parentFqcn);

            return $isData && ! $hasTrait;
        }

        return $parentFqcn === $this->baseFqcn();
    }

    /**
     * Walk the ancestor chain via the index, reporting whether it reaches the
     * Data base and whether any ancestor uses the trait.
     *
     * @return array{0: bool, 1: bool}  [isData, hasTrait]
     */
    private function walkChain(?string $parentFqcn): array
    {
        $isData = false;
        $hasTrait = false;
        $seen = [];
        $cur = $parentFqcn;
        $traitShort = $this->traitShort();
        $baseShort = $this->shortName($this->baseFqcn());

        while ($cur !== null && ! isset($seen[$cur])) {
            $seen[$cur] = true;

            if ($cur === $this->baseFqcn() || $this->shortName($cur) === $baseShort) {
                $isData = true;
                break;
            }

            $summary = $this->index?->classByFqcn($cur);

            if ($summary === null) {
                break;
            }

            foreach ($summary->traits as $trait) {
                if ($this->shortName($trait) === $traitShort) {
                    $hasTrait = true;
                    break;
                }
            }

            $cur = $summary->parent;
        }

        return [$isData, $hasTrait];
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }

    /**
     * @param  array<string, string>  $uses
     */
    private function extendsDataBase(Node\Stmt\Class_ $class, array $uses, ?string $namespace): bool
    {
        if (! $class->extends instanceof Node\Name) {
            return false;
        }

        return $this->resolve($class->extends, $uses, $namespace) === $this->baseFqcn();
    }

    /**
     * Whether the class has a same-class `self::from()/static::from()/Own::from()`
     * call whose argument is NOT provably an array — the case the FromArrayOnly
     * trait's runtime assert would reject. Array literals, `->toArray()/->all()`,
     * and `array`-typed parameters are treated as safe; objects, property
     * fetches and untyped variables are treated as unsafe (issue #14).
     */
    /**
     * Whether the class — or any subclass that would inherit the array-only
     * trait — depends on Spatie's magic from(Model) path
     * (#[LoadRelation]/#[MapInputName]/#[MapName]/#[Computed]). Such a class
     * cannot be array-only; adding the trait would fatal at runtime (#49).
     */
    private function dependsOnMagic(Node\Stmt\Class_ $class, ?string $namespace): bool
    {
        if (SpatieDataMagic::classHasMagicAttribute($class)) {
            return true;
        }

        if ($this->index === null || $class->name === null) {
            return false;
        }

        $fqcn = $namespace !== null && $namespace !== ''
            ? $namespace . '\\' . $class->name->toString()
            : $class->name->toString();

        foreach ($this->index->subclassesOf($fqcn) as $subFqcn) {
            $summary = $this->index->classByFqcn(ltrim($subFqcn, '\\'));

            if ($summary === null) {
                continue;
            }

            $content = @file_get_contents($summary->filePath);

            if (! is_string($content)) {
                continue;
            }

            $node = $this->findClass($this->parseAst($content) ?? [], $this->shortName($subFqcn));

            if ($node !== null && SpatieDataMagic::classHasMagicAttribute($node)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<Node>  $ast
     */
    private function findClass(array $ast, string $short): ?Node\Stmt\Class_
    {
        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if ($class->name?->toString() === $short) {
                return $class;
            }
        }

        return null;
    }

    private function hasUnsafeSelfFrom(Node\Stmt\Class_ $class): bool
    {
        $ownName = $class->name?->toString();
        $nodeFinder = new NodeFinder;

        foreach ($class->getMethods() as $method) {
            if ($method->stmts === null) {
                continue;
            }

            $arrayParams = $this->arrayTypedParams($method);

            foreach ($nodeFinder->findInstanceOf($method->stmts, Node\Expr\StaticCall::class) as $call) {
                if (! $this->isSelfFromCall($call, $ownName)) {
                    continue;
                }

                $arg = ($call->args[0] ?? null) instanceof Node\Arg ? $call->args[0]->value : null;

                if ($arg === null || $this->isArrayish($arg, $arrayParams)) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }

    private function isSelfFromCall(Node\Expr\StaticCall $call, ?string $ownName): bool
    {
        if (! $call->name instanceof Node\Identifier || $call->name->toString() !== 'from'
            || ! $call->class instanceof Node\Name
        ) {
            return false;
        }

        $target = $call->class->getLast();

        return in_array($target, ['self', 'static'], true) || ($ownName !== null && $target === $ownName);
    }

    /**
     * Names of the method's parameters that are typed `array` (so `from($param)`
     * is array-safe).
     *
     * @return array<string, true>
     */
    private function arrayTypedParams(Node\Stmt\ClassMethod $method): array
    {
        $names = [];

        foreach ($method->params as $param) {
            $type = $param->type;

            if ($type instanceof Node\NullableType) {
                $type = $type->type;
            }

            if ($type instanceof Node\Identifier && $type->toString() === 'array'
                && $param->var instanceof Node\Expr\Variable && is_string($param->var->name)
            ) {
                $names[$param->var->name] = true;
            }
        }

        return $names;
    }

    /**
     * @param  array<string, true>  $arrayParams
     */
    private function isArrayish(Node $arg, array $arrayParams): bool
    {
        if ($arg instanceof Node\Expr\Array_) {
            return true;
        }

        if ($arg instanceof Node\Expr\MethodCall && $arg->name instanceof Node\Identifier) {
            return in_array($arg->name->toString(), ['toArray', 'all'], true);
        }

        if ($arg instanceof Node\Expr\Variable && is_string($arg->name)) {
            return isset($arrayParams[$arg->name]);
        }

        return false;
    }

    private function usesTrait(Node\Stmt\Class_ $class, string $traitShort): bool
    {
        foreach ($class->stmts as $stmt) {
            if (! $stmt instanceof Node\Stmt\TraitUse) {
                continue;
            }

            foreach ($stmt->traits as $trait) {
                if ($trait->getLast() === $traitShort) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<Node>  $ast
     * @return array{0: ?string, 1: array<string, string>}
     */
    private function context(array $ast): array
    {
        $nodeFinder = new NodeFinder;
        $namespace = null;

        foreach ($nodeFinder->findInstanceOf($ast, Node\Stmt\Namespace_::class) as $ns) {
            $namespace = $ns->name?->toString();
            break;
        }

        $uses = [];

        foreach ($nodeFinder->findInstanceOf($ast, Node\Stmt\Use_::class) as $use) {
            foreach ($use->uses as $useUse) {
                $alias = $useUse->alias?->toString() ?? $useUse->name->getLast();
                $uses[$useUse->name->toString()] = $alias;
            }
        }

        return [$namespace, $uses];
    }

    /**
     * @param  array<string, string>  $uses  fqcn => alias
     */
    private function resolve(Node\Name $name, array $uses, ?string $namespace): string
    {
        if ($name->isFullyQualified()) {
            return ltrim($name->toString(), '\\');
        }

        $first = $name->getFirst();

        foreach ($uses as $fqcn => $alias) {
            if ($alias === $first) {
                $rest = array_slice($name->getParts(), 1);

                return $rest === [] ? $fqcn : $fqcn . '\\' . implode('\\', $rest);
            }
        }

        return ($namespace !== null && $namespace !== '' ? $namespace . '\\' : '') . $name->toString();
    }

    /**
     * @param  array<Node>  $ast
     * @return array{start: int, end: int, text: string}|null
     */
    private function importInsertion(array $ast, string $content): ?array
    {
        $line = "\nuse {$this->traitFqcn()};";
        $nodeFinder = new NodeFinder;

        $uses = $nodeFinder->findInstanceOf($ast, Node\Stmt\Use_::class);

        if ($uses !== []) {
            $pos = max(array_map(static fn (Node $u) => (int) $u->getEndFilePos(), $uses)) + 1;

            return ['start' => $pos, 'end' => $pos - 1, 'text' => $line];
        }

        $namespaces = $nodeFinder->findInstanceOf($ast, Node\Stmt\Namespace_::class);

        if ($namespaces !== [] && $namespaces[0]->name !== null) {
            $semicolon = strpos($content, ';', (int) $namespaces[0]->getStartFilePos());

            if ($semicolon !== false) {
                return ['start' => $semicolon + 1, 'end' => $semicolon, 'text' => "\n{$line}"];
            }
        }

        return null;
    }

    /**
     * @return array<Node>|null
     */
    private function parseAst(string $content): ?array
    {
        return (new ParserFactory)->createForNewestSupportedVersion()->parse($content);
    }

    private function baseFqcn(): string
    {
        $base = $this->config('base_class', self::DEFAULT_BASE);

        return is_string($base) && $base !== '' ? ltrim($base, '\\') : self::DEFAULT_BASE;
    }

    private function traitFqcn(): string
    {
        $trait = $this->config('trait_class', self::DEFAULT_TRAIT);

        return is_string($trait) && $trait !== '' ? ltrim($trait, '\\') : self::DEFAULT_TRAIT;
    }

    private function traitShort(): string
    {
        $parts = explode('\\', $this->traitFqcn());

        return end($parts) ?: 'FromArrayOnly';
    }

}
