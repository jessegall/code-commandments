<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\Archetype;
use JesseGall\CodeCommandments\Support\RoleInference;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Flag a hand-rolled SINGLETON — a class with a private/protected constructor and
 * a public static accessor that caches its sole instance in a static property
 * (`self::$instance ??= new self()`) — and nudge dependency injection instead.
 *
 * Detection is STRUCTURAL via {@see RoleInference} ({@see Archetype::Singleton}),
 * never a `getInstance` name: the static-property cache of `new self` plus a
 * non-public constructor is the unambiguous shape, so it fires on the UNMARKED
 * ones too. A singleton is global mutable state — a hidden, un-substitutable
 * dependency that makes the holder hard to test in isolation.
 */
#[IntroducedIn('2.9.0')]
class PreferInjectionOverSingletonProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Prefer dependency injection over a hand-rolled singleton';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A class is a singleton — a private/protected constructor plus a public '
                . 'static accessor that lazily constructs and caches the sole instance '
                . 'in a static property (`self::$instance ??= new self()`). It is global '
                . 'mutable state reached through a static call, not an injected dependency.'
            )
            ->leaveWhen(
                'The static instance is a genuine, stateless, process-wide constant with '
                . 'no collaborators (a pure flyweight), or a framework genuinely forces '
                . 'the pattern at a boundary you do not control. Even then, prefer binding '
                . 'a single shared instance in the container over a hard-coded accessor.'
            )
            ->whenUnsure(
                'Make the constructor public (or keep it as-is) and let callers receive '
                . 'the instance through their own constructor; register it as a shared '
                . 'binding in your DI container so there is still ONE instance, but an '
                . 'injectable, substitutable (mockable) one. Drop the static accessor.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A singleton hard-codes "there is exactly one of me, and you reach me through a
static call." That global access point is a hidden dependency: a class that calls
`Foo::getInstance()` does not declare its need for `Foo`, cannot be handed a test
double, and is coupled to the singleton's lifecycle.

Bad — a hand-rolled singleton:
    final class Config
    {
        private static ?self $instance = null;

        private function __construct(private array $values) {}

        public static function getInstance(): self
        {
            return self::$instance ??= new self(load_config());
        }
    }

Good — one shared instance, injected:
    final class Config
    {
        public function __construct(private array $values) {}
    }
    // bind it once as a shared/singleton service in the container, then:
    public function __construct(private Config $config) {}

You still get a single instance — but an injectable, substitutable one. Tests
pass a stub; nothing reaches through a static accessor.

WHAT FIRES — a class with a private/protected constructor AND a public static
method that caches `new self/static(...)` in a static property of the class.
Detected by SHAPE, not by the name `getInstance`.

WHAT DOES NOT — a memo/cache of OTHER values; a class with a public constructor;
a manual enum (a closed set of distinct instances); a stateless static helper.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $warnings = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if ($class->name === null) {
                continue;
            }

            if (RoleInference::infer($class)->archetype() !== Archetype::Singleton) {
                continue;
            }

            $name = $class->name->toString();

            $warnings[] = $this->warningAt(
                $class->getStartLine(),
                sprintf(
                    '`%s` is a singleton — a private constructor with a static accessor caching the sole instance. That is global mutable state reached through a static call: a hidden, un-substitutable dependency. Inject it instead — make the instance injectable and register it as a single shared binding in the container, so callers declare their need for it and tests can substitute it.',
                    $name,
                ),
                null,
                'singleton:' . $name,
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }
}
