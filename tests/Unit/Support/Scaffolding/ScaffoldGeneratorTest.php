<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support\Scaffolding;

use JesseGall\CodeCommandments\Support\Scaffolding\ScaffoldGenerator;
use JesseGall\CodeCommandments\Tests\TestCase;

class ScaffoldGeneratorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-scaffold-' . uniqid();
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf ' . escapeshellarg($this->dir));
        parent::tearDown();
    }

    public function test_generates_classes_with_rewritten_namespace(): void
    {
        $results = ScaffoldGenerator::packaged()->generate('Acme\\Support', $this->dir);

        $created = array_filter($results, fn ($r) => $r['status'] === ScaffoldGenerator::STATUS_CREATED);
        $this->assertNotEmpty($created);

        $trait = file_get_contents($this->dir . '/FromArrayOnly.php');
        $this->assertStringContainsString('namespace Acme\\Support;', $trait);
        $this->assertStringContainsString('trait FromArrayOnly', $trait);

        $this->assertFileExists($this->dir . '/Option.php');
        $this->assertFileExists($this->dir . '/NullCallable.php');
        $option = file_get_contents($this->dir . '/Option.php');
        $this->assertStringContainsString('namespace Acme\\Support;', $option);
        // The coalescing constructor — first non-null candidate, else none.
        $this->assertStringContainsString('public static function coalesce(mixed ...$candidates): self', $option);
    }

    public function test_compare_self_declares_singular_helpers_instance_only(): void
    {
        // Regression guard (issue #6): a static `Enum::equals($x, Enum::Case)`
        // against a known case is a sin — the rule mandates the case-anchored
        // instance form `Enum::Case->equals($x)`. If the trait also declared
        // `@method static bool equals(...)`, PHPStan resolves the case-arrow
        // call to the 2-arg static signature and errors on every anchored call.
        // So the singular family must be instance-only; only `*Any` is static.
        ScaffoldGenerator::packaged()->generate('Acme\\Support', $this->dir);

        $trait = file_get_contents($this->dir . '/CompareSelf.php');

        // Instance singular helpers ARE declared.
        $this->assertStringContainsString('@method bool equals(mixed $value)', $trait);
        $this->assertStringContainsString('@method bool notEquals(mixed $value)', $trait);

        // Static singular helpers must NOT be declared (they would shadow the
        // instance form for `Enum::Case->equals($x)`).
        $this->assertStringNotContainsString('@method static bool equals(', $trait);
        $this->assertStringNotContainsString('@method static bool notEquals(', $trait);
        $this->assertStringNotContainsString('@method static bool equalsIgnoreType(', $trait);
        $this->assertStringNotContainsString('@method static bool notEqualsIgnoreType(', $trait);

        // The set helpers carry a static declaration, and their variadic case
        // parameters are typed \UnitEnum, not self (issue #8): `self` in a trait
        // @method resolves to the trait, not the using enum, and would reject
        // the cases passed. \UnitEnum type-checks both instance and static use.
        $this->assertStringContainsString('@method static bool equalsAny(mixed $value, \UnitEnum ...$cases)', $trait);
        $this->assertStringContainsString('@method static bool notEqualsAny(mixed $value, \UnitEnum ...$cases)', $trait);
        $this->assertStringNotContainsString('self ...$cases', $trait);
    }

    public function test_generates_has_class_predicate(): void
    {
        ScaffoldGenerator::packaged()->generate('Acme\\Support', $this->dir);

        $hasClass = $this->dir . '/Resolvers/Predicates/HasClass.php';
        $this->assertFileExists($hasClass);
        $src = file_get_contents($hasClass);
        $this->assertStringContainsString('namespace Acme\\Support\\Resolvers\\Predicates;', $src);
        $this->assertStringContainsString('final class HasClass extends Predicate', $src);
        $this->assertStringContainsString('public static function of(string $class): self', $src);
        $this->assertStringContainsString('$value instanceof $this->class', $src);
    }

    public function test_generates_the_union_sum_type(): void
    {
        ScaffoldGenerator::packaged()->generate('Acme\\Support', $this->dir);

        $union = $this->dir . '/Union.php';
        $this->assertFileExists($union);
        $src = file_get_contents($union);
        $this->assertStringContainsString('namespace Acme\\Support;', $src);
        // Not final — a constrained subclass extends it; of() returns static.
        $this->assertStringContainsString("\nclass Union", $src);
        $this->assertStringContainsString('public static function of(mixed $value): static', $src);
        $this->assertStringContainsString('public function match(array $handlers): mixed', $src);
        $this->assertStringContainsString('public function is(string $type): bool', $src);
        $this->assertStringContainsString('protected function allowedTypes(): array', $src);

        // The ready-made scalar-constrained Union.
        $scalar = file_get_contents($this->dir . '/ScalarUnion.php');
        $this->assertStringContainsString('final class ScalarUnion extends Union', $scalar);
        $this->assertStringContainsString("return ['string', 'int', 'float', 'bool'];", $scalar);
    }

    public function test_generates_the_union_cast(): void
    {
        ScaffoldGenerator::packaged()->generate('Acme\\Support', $this->dir);

        $cast = $this->dir . '/UnionCast.php';
        $this->assertFileExists($cast);
        $src = file_get_contents($cast);
        $this->assertStringContainsString('namespace Acme\\Support;', $src);
        $this->assertStringContainsString('final class UnionCast implements Cast, Transformer', $src);
        $this->assertStringContainsString('use JesseGall\\PhpTypes\\T;', $src);
        // References Union (same namespace) and the allow-list check.
        $this->assertStringContainsString('return Union::of($value);', $src);
        $this->assertStringContainsString('T::any($value, ...$this->allowed)', $src);
    }

    public function test_generates_the_scalar_option(): void
    {
        ScaffoldGenerator::packaged()->generate('Acme\\Support', $this->dir);

        $scalarOption = $this->dir . '/ScalarOption.php';
        $this->assertFileExists($scalarOption);
        $src = file_get_contents($scalarOption);
        $this->assertStringContainsString('namespace Acme\\Support;', $src);
        // Wraps an Option<scalar> — the present type is constrained natively.
        $this->assertStringContainsString('final class ScalarOption', $src);
        $this->assertStringContainsString('public static function some(string|int|float|bool $value): self', $src);
        $this->assertStringContainsString('public function getOrThrow(): string|int|float|bool', $src);
    }

    public function test_generates_capture_and_wrap_result_factories(): void
    {
        ScaffoldGenerator::packaged()->generate('Acme\\Support', $this->dir);

        $capture = $this->dir . '/Resolvers/Factories/Capture.php';
        $wrap = $this->dir . '/Resolvers/Factories/Wrap.php';
        $this->assertFileExists($capture);
        $this->assertFileExists($wrap);

        $captureSrc = file_get_contents($capture);
        $this->assertStringContainsString('namespace Acme\\Support\\Resolvers\\Factories;', $captureSrc);
        $this->assertStringContainsString('final class Capture', $captureSrc);
        $this->assertStringContainsString('return $value;', $captureSrc);

        $wrapSrc = file_get_contents($wrap);
        $this->assertStringContainsString('final class Wrap', $wrapSrc);
        $this->assertStringContainsString('return [$value];', $wrapSrc);
        $this->assertStringContainsString('@return array{0: T}', $wrapSrc);
    }

    public function test_generated_kernel_is_generic_and_uses_then(): void
    {
        // The resolver kernel threads PHPStan generics so resolve() infers what
        // the then() factories return instead of mixed. Guard the load-bearing
        // markers and the then() rename (when() is gone).
        ScaffoldGenerator::packaged()->generate('Acme\\Support', $this->dir);

        $predicate = file_get_contents($this->dir . '/Resolvers/Predicates/Predicate.php');
        $this->assertStringContainsString('@template-covariant TIn', $predicate);
        $this->assertStringContainsString('public function then(callable $make): callable', $predicate);
        $this->assertStringContainsString('@param  callable(TIn): TOut  $make', $predicate);
        // when() must be gone — then() is the only name.
        $this->assertStringNotContainsString('public function when(', $predicate);

        $resolver = file_get_contents($this->dir . '/Resolvers/Resolver.php');
        $this->assertStringContainsString('@template TResult', $resolver);
        $this->assertStringContainsString('@return self<T|null>', $resolver);
        $this->assertStringContainsString('@return self<list<T>>', $resolver);
        $this->assertStringContainsString('@return TResult', $resolver);
        // Typed result guard: resolveInstanceOf($input, Type::class): ?Type.
        $this->assertStringContainsString('public function resolveInstanceOf(mixed $input, string $type): ?object', $resolver);
        $this->assertStringContainsString('@param  class-string<T>  $type', $resolver);

        // ResolverDecorator base: domain resolvers extend it; it owns the
        // plumbing (add/resolve) and has NO make() (subclasses construct).
        $decorator = file_get_contents($this->dir . '/Resolvers/ResolverDecorator.php');
        $this->assertStringContainsString('abstract class ResolverDecorator', $decorator);
        $this->assertStringContainsString('final protected function add(callable $entry): static', $decorator);
        $this->assertStringContainsString('public function resolve(mixed $input): mixed', $decorator);
        $this->assertStringContainsString('public function resolveInstanceOf(mixed $input, string $type): ?object', $decorator);
        $this->assertStringNotContainsString('new static()', $decorator);

        // Transform path is typed end-to-end too.
        $transform = file_get_contents($this->dir . '/Resolvers/Transforms/Transform.php');
        $this->assertStringContainsString('@template-covariant TOut', $transform);
        $stripPrefix = file_get_contents($this->dir . '/Resolvers/Transforms/StripPrefix.php');
        $this->assertStringContainsString('@extends Transform<string>', $stripPrefix);
    }

    public function test_generated_kernel_is_phpstan_clean(): void
    {
        // Regression guard (issue #13): the generated resolver/predicate kernel
        // must pass PHPStan max out of the box. Three defects had to be patched
        // by hand in the consumer — guard each here.
        ScaffoldGenerator::packaged()->generate('Acme\\Support', $this->dir);

        // 1. Singleton factories need `@var self|null` on the static, otherwise
        // `make()` infers a `mixed` return.
        foreach (['Resolvers/Predicates/IsNull.php', 'Resolvers/Strategies/FirstResultWins.php', 'Resolvers/Strategies/CollectResults.php'] as $rel) {
            $src = file_get_contents($this->dir . '/' . $rel);
            $this->assertStringContainsString('/** @var self|null $instance */', $src, "$rel missing @var on singleton static");
        }

        // 2 & 3. No `callable(mixed): mixed` in *param* position — that shape
        // triggers contravariance errors at every typed call site
        // (`fn(string $t): WireType`) and rejects real entry collections.
        foreach (['Resolvers/Resolver.php', 'Resolvers/Predicates/Predicate.php', 'Resolvers/Predicates/PredicateEntry.php', 'Resolvers/Strategies/ResolveStrategy.php'] as $rel) {
            $src = file_get_contents($this->dir . '/' . $rel);
            foreach (explode("\n", $src) as $line) {
                // A bare `callable(mixed): mixed` *factory* @param is the problem
                // (it rejects `fn(string $t): X`). The same shape on a resolver
                // *entry* collection ($resolvers — array/iterable/variadic of the
                // already-built fn(mixed): mixed entries) is correct and
                // PHPStan-clean, so don't flag those.
                $isEntryCollection = str_contains($line, '$resolvers');
                if (str_contains($line, '@param') && str_contains($line, 'callable(mixed): mixed') && ! $isEntryCollection) {
                    $this->fail("$rel still has a contravariant callable(mixed): mixed @param: " . trim($line));
                }
            }
        }
    }

    public function test_generates_sub_namespaced_classes_into_a_subdirectory(): void
    {
        ScaffoldGenerator::packaged()->generate('Acme\\Support', $this->dir);

        // Predicates carry subNamespace `Resolvers\Predicates`.
        $isNull = $this->dir . '/Resolvers/Predicates/IsNull.php';
        $this->assertFileExists($isNull);
        $this->assertStringContainsString('namespace Acme\\Support\\Resolvers\\Predicates;', file_get_contents($isNull));

        // The Resolver base carries subNamespace `Resolvers`.
        $resolver = $this->dir . '/Resolvers/Resolver.php';
        $this->assertFileExists($resolver);
        $this->assertStringContainsString('namespace Acme\\Support\\Resolvers;', file_get_contents($resolver));

        // IsNull extends Predicate from the same namespace — no cross-namespace import needed.
        $this->assertStringContainsString('extends Predicate', file_get_contents($isNull));
        $this->assertFileExists($this->dir . '/Resolvers/Predicates/Predicate.php');
    }

    public function test_result_class_name_includes_the_sub_namespace(): void
    {
        $results = ScaffoldGenerator::packaged()->generate('Acme\\Support', $this->dir);

        $byName = [];
        foreach ($results as $r) {
            $byName[$r['name']] = $r['class'];
        }

        $this->assertSame('Acme\\Support\\Resolvers\\Predicates\\IsNull', $byName['predicate-is-null'] ?? null);
        $this->assertSame('Acme\\Support\\Resolvers\\Resolver', $byName['resolver'] ?? null);
        // A flat scaffold is unaffected.
        $this->assertSame('Acme\\Support\\Option', $byName['option'] ?? null);
    }

    public function test_force_refreshes_a_relocated_class_in_place(): void
    {
        // Generate fresh, then simulate a consumer relocating CompareSelf into
        // a `Enums` sub-namespace (a common tidy-up). A forced re-scaffold must
        // refresh THAT file in place — preserving its namespace — not write a
        // stale duplicate at the flat path.
        ScaffoldGenerator::packaged()->generate('Acme\\Support', $this->dir);

        $flat = $this->dir . '/CompareSelf.php';
        $this->assertFileExists($flat);

        $enumsDir = $this->dir . '/Enums';
        @mkdir($enumsDir, 0755, true);
        $relocated = $enumsDir . '/CompareSelf.php';
        rename($flat, $relocated);
        file_put_contents(
            $relocated,
            str_replace('namespace Acme\\Support;', 'namespace Acme\\Support\\Enums;', file_get_contents($relocated)),
        );

        // Tamper with the relocated file so we can prove it was rewritten.
        file_put_contents($relocated, str_replace('@method bool equals(mixed $value)', '// stale', file_get_contents($relocated)));

        ScaffoldGenerator::packaged()->generate('Acme\\Support', $this->dir, force: true);

        // No duplicate created at the flat path.
        $this->assertFileDoesNotExist($flat);

        $refreshed = file_get_contents($relocated);
        // Rewritten with current stub content…
        $this->assertStringContainsString('@method bool equals(mixed $value)', $refreshed);
        $this->assertStringNotContainsString('// stale', $refreshed);
        // …while preserving the relocated namespace.
        $this->assertStringContainsString('namespace Acme\\Support\\Enums;', $refreshed);
    }

    public function test_is_idempotent_and_skips_existing(): void
    {
        $gen = ScaffoldGenerator::packaged();
        $gen->generate('Acme\\Support', $this->dir);

        $second = $gen->generate('Acme\\Support', $this->dir);

        foreach ($second as $result) {
            $this->assertSame(ScaffoldGenerator::STATUS_SKIPPED, $result['status']);
        }
    }

    public function test_force_rewrites_existing(): void
    {
        $gen = ScaffoldGenerator::packaged();
        $gen->generate('Acme\\Support', $this->dir);

        file_put_contents($this->dir . '/Option.php', '<?php // hand-edited');

        $results = $gen->generate('Acme\\Support', $this->dir, force: true);

        $option = collect($results)->firstWhere('name', 'option');
        $this->assertSame(ScaffoldGenerator::STATUS_REWRITTEN, $option['status']);
        $this->assertStringContainsString('final readonly class Option', file_get_contents($this->dir . '/Option.php'));
    }

    public function test_except_skips_named_scaffolds(): void
    {
        $results = ScaffoldGenerator::packaged()->generate('Acme\\Support', $this->dir, except: ['option']);

        $this->assertFileDoesNotExist($this->dir . '/Option.php');
        $this->assertFileExists($this->dir . '/FromArrayOnly.php');
    }
}
