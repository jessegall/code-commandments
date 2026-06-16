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
use JesseGall\CodeCommandments\Support\PackageDetector;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Every class that extends Spatie Data directly must `use` the FromArrayOnly
 * trait, so `::from()` is guarded against the magic object dispatch at
 * runtime. Mechanical, so it is auto-fixable.
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
            if (! $this->lacksTraitAccess($class, $uses, $namespace)) {
                continue;
            }

            $name = $class->name?->toString() ?? 'class';
            $message = "{$name} is a Data class without access to the {$traitShort} trait (not on it or any ancestor).";
            $suggestion = "Add `use {$traitShort};` to the class (and import {$this->traitFqcn()}). Run `commandments repent` to fix automatically.";
            $line = $class->getStartLine();
            $snippet = $this->lineAt($content, $line);

            if ($isSin) {
                $sins[] = $this->sinAt($line, $message, $snippet, $suggestion, $name);
            } else {
                $warnings[] = $this->warningAt($line, $message . ' ' . $suggestion, $snippet, $name);
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
            if (! $this->extendsDataBase($class, $uses, $namespace) || $this->usesTrait($class, $traitShort)) {
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

    private function lineAt(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : '';
    }
}
