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
use JesseGall\CodeCommandments\Support\RegistryShape;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Throwable;

/**
 * The structural sibling of {@see RegistryNamingHonestyProphet} (mirroring how
 * ResolverPattern complements ResolverNamingHonesty): when TWO OR MORE classes
 * hand-roll the registry shape (register + keyed store + lookup) without a shared
 * base, the register/find/get boilerplate is duplicated — extract one abstract
 * base and have them extend it. `commandments:scaffold` generates that base.
 *
 * Advisory, never a sin. Cross-file (NeedsCodebaseIndex): a single registry is
 * fine; the nudge is about the DUPLICATION across several.
 */
#[IntroducedIn('2.0.0')]
class RegistryPatternProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    private const MIN_REGISTRIES = 2;

    private const MAX_SCAN_FILES = 800;

    private ?CodebaseIndex $index = null;

    private ?int $handRolledCount = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
        $this->handRolledCount = null;
    }

    public function description(): string
    {
        return 'When several classes hand-roll the registry shape, extract a shared base (scaffold one)';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('Two or more classes in the codebase hand-roll the registry shape (a public keyed-store write + a lookup) WITHOUT extending a shared base — the register/find/get plumbing is copy-pasted across them.')
            ->leaveWhen('there is only one registry, the classes already extend a shared registry base, or their lookup semantics genuinely differ enough that a common base would be a false abstraction.')
            ->whenUnsure('if you would copy a register/find/get method from one to another, extract a base. `commandments:scaffold` generates a `Registry` base (register/all/find/get) into your support namespace; have each registry extend it (then RegistryReturnContract enforces the contract for free).');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
One registry that hand-rolls `register`/`find`/`get` is fine. Several is
duplicated plumbing — and each copy is a place the return contract can drift
(one throws, one returns null, one returns Option).

When 2+ classes share the registry shape (you `register`/store into a keyed
property, then look entries up) and none extend a shared base, extract one:

    abstract class Registry { /* register / all / find(): Option / get(): T */ }
    final class ChannelRegistry extends Registry { … }
    final class TemplateRegistry extends Registry { … }

`commandments:scaffold` generates that base (and a `RegistryEntryNotFoundException`
for its throwing `get()`) into your configured support namespace — the idiomatic
"one abstract base marked once, N concrete subclasses" shape. Once each registry
extends it, RegistryReturnContract enforces return-or-throw across all of them
from the single marker.

Advisory — extracting a base is a design call. Not auto-fixable.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        if ($this->handRolledRegistryCount() < self::MIN_REGISTRIES) {
            return $this->righteous();
        }

        $warnings = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if ($class->name === null || $class->isAbstract() || $class->extends !== null) {
                continue;
            }

            if (RegistryShape::detect($class) === null) {
                continue;
            }

            $name = $class->name->toString();
            $warnings[] = $this->warningAt(
                $class->getStartLine(),
                sprintf('%s hand-rolls the registry shape (register + keyed store + lookup), and it is one of %d+ such classes with no shared base. Extract an abstract `Registry` base and extend it — `commandments:scaffold` generates one — so the register/find/get contract lives (and is enforced) in one place.', $name, $this->handRolledRegistryCount()),
                $this->lineAt($content, $class->getStartLine()),
                'registry-pattern:' . $name,
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * How many classes across the scroll hand-roll the registry shape WITHOUT a
     * parent (a shared base). Memoized; uses the index's `parent` summary as a
     * cheap pre-filter so only base-less classes are re-parsed.
     */
    private function handRolledRegistryCount(): int
    {
        if ($this->handRolledCount !== null) {
            return $this->handRolledCount;
        }

        if ($this->index === null) {
            return $this->handRolledCount = 0;
        }

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $finder = new NodeFinder;
        $parsedFiles = [];
        $count = 0;
        $scanned = 0;

        foreach ($this->index->classes() as $fqcn => $summary) {
            if ($summary->parent !== null) {
                continue; // extends something → not a base-less hand-roll
            }

            $file = $summary->filePath;

            if (! array_key_exists($file, $parsedFiles)) {
                if ($scanned++ >= self::MAX_SCAN_FILES) {
                    break;
                }

                $content = @file_get_contents($file);

                try {
                    $parsedFiles[$file] = $content !== false ? ($parser->parse($content) ?? []) : [];
                } catch (Throwable) {
                    $parsedFiles[$file] = [];
                }
            }

            $short = self::shortName($fqcn);

            foreach ($finder->findInstanceOf($parsedFiles[$file], Node\Stmt\Class_::class) as $class) {
                if ($class->name?->toString() === $short
                    && $class->extends === null
                    && RegistryShape::detect($class) !== null
                ) {
                    $count++;

                    break;
                }
            }
        }

        return $this->handRolledCount = $count;
    }

    private static function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

    private function lineAt(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return trim($lines[$line - 1] ?? '');
    }
}
