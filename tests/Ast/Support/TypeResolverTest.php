<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast\Support;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\TypeResolver;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\NodeFinder;
use PHPUnit\Framework\TestCase;

/**
 * {@see TypeResolver} follows the receiver chain — a local typed from its assignment origin, then a
 * property/method chain read off it — hop by hop against a codebase-wide type index.
 */
final class TypeResolverTest extends TestCase
{
    private const CODE = <<<'PHP'
        <?php
        namespace App;
        class C {}
        class B { public function __construct(public readonly C $c) {} }
        class A {
            public function __construct(public readonly B $b) {}
            public function self(): A { return $this; }
            public static function for(string $id): static { return new static(); }
            public function with(): static { return $this; }
        }
        class Sink { public function take(int $n, ?int $maybe = null): void {} }
        class Use_ {
            public function run(A $a): void {
                $x = A::from([]);
                $chain = $a->b->c;
                $ret = $a->self()->b;
                $made = A::for('x');
                $fluent = $a->with();
            }
        }
        PHP;

    /**
     * Resolve the type of the RHS of the assignment to `$name` inside `App\Use_::run`.
     */
    private function typeOfLocal(string $name): ?string
    {
        $codebase = Codebase::fromString(self::CODE);
        $file = $codebase->files()[0];
        $function = $this->functionNamed($file->ast, 'run');
        $assign = $this->assignmentTo($function, $name);

        return TypeResolver::forCodebase($codebase)->typeOf($assign, $function, 'App\\Use_');
    }

    public function test_types_a_local_from_a_from_factory_origin(): void
    {
        self::assertSame('App\\A', $this->typeOfLocal('x'));
    }

    public function test_follows_a_property_chain_receiver_by_receiver(): void
    {
        self::assertSame('App\\C', $this->typeOfLocal('chain'));
    }

    public function test_follows_a_method_return_then_a_property(): void
    {
        self::assertSame('App\\B', $this->typeOfLocal('ret'));
    }

    public function test_resolves_a_static_returning_named_constructor_to_its_class(): void
    {
        // `A::for()` is typed `: static` — it must resolve to A, not to a class literally named "static".
        self::assertSame('App\\A', $this->typeOfLocal('made'));
    }

    public function test_resolves_a_static_returning_fluent_method_to_its_receiver(): void
    {
        self::assertSame('App\\A', $this->typeOfLocal('fluent'));
    }

    public function test_reads_parameter_nullability_by_position(): void
    {
        $resolver = TypeResolver::forCodebase(Codebase::fromString(self::CODE));

        self::assertFalse($resolver->paramIsNullable('App\\Sink', 'take', 0));
        self::assertTrue($resolver->paramIsNullable('App\\Sink', 'take', 1));
        self::assertNull($resolver->paramIsNullable('App\\Sink', 'take', 9), 'an unknown position is unknown, not non-nullable');
    }

    private function functionNamed(array $ast, string $name): FunctionLike
    {
        foreach (new NodeFinder()->findInstanceOf($ast, FunctionLike::class) as $function) {
            if ($function instanceof Node\Stmt\ClassMethod && $function->name->toString() === $name) {
                return $function;
            }
        }

        self::fail("no method {$name}");
    }

    private function assignmentTo(FunctionLike $function, string $name): Node
    {
        foreach (new NodeFinder()->findInstanceOf($function, Node\Expr\Assign::class) as $assign) {
            if ($assign->var instanceof Variable && $assign->var->name === $name) {
                return $assign->expr;
            }
        }

        self::fail("no assignment to \${$name}");
    }
}
