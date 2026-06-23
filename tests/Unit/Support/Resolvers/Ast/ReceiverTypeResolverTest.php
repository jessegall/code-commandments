<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support\Resolvers\Ast;

use JesseGall\CodeCommandments\Support\Resolvers\Ast\FileImports;
use JesseGall\CodeCommandments\Support\Resolvers\Ast\ReceiverTypeResolver;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class ReceiverTypeResolverTest extends TestCase
{
    /** @return array<\PhpParser\Node> */
    private function parse(string $body): array
    {
        return (new ParserFactory)->createForNewestSupportedVersion()->parse("<?php\n" . $body) ?? [];
    }

    private function firstCall(array $ast, string $method): MethodCall
    {
        foreach ((new NodeFinder)->findInstanceOf($ast, MethodCall::class) as $call) {
            if ($call->name->toString() === $method) {
                return $call;
            }
        }

        $this->fail("no {$method}() call found");
    }

    public function test_resolves_a_typed_parameter_receiver_to_its_fqcn(): void
    {
        $ast = $this->parse(
            "namespace App;\nuse Illuminate\\Http\\Request;\n"
            . "class C { public function h(Request \$request) { return \$request->input('x'); } }"
        );
        $call = $this->firstCall($ast, 'input');

        $fqcn = ReceiverTypeResolver::resolve($call->var, $ast, FileImports::of($ast), FileImports::namespace($ast), $call);

        $this->assertSame('Illuminate\\Http\\Request', $fqcn);
    }

    public function test_resolves_a_typed_promoted_property_receiver(): void
    {
        $ast = $this->parse(
            "namespace App;\nuse App\\Bag;\n"
            . "class C { public function __construct(private Bag \$bag) {} public function h() { return \$this->bag->get('x'); } }"
        );
        $call = $this->firstCall($ast, 'get');

        $fqcn = ReceiverTypeResolver::resolve($call->var, $ast, FileImports::of($ast), FileImports::namespace($ast), $call);

        $this->assertSame('App\\Bag', $fqcn);
    }

    public function test_resolves_a_declared_property_receiver(): void
    {
        $ast = $this->parse(
            "namespace App;\nuse App\\Bag;\n"
            . "class C { private Bag \$bag; public function h() { return \$this->bag->get('x'); } }"
        );
        $call = $this->firstCall($ast, 'get');

        $this->assertSame('App\\Bag', ReceiverTypeResolver::resolve($call->var, $ast, FileImports::of($ast), FileImports::namespace($ast), $call));
    }

    public function test_returns_null_for_an_untyped_receiver(): void
    {
        $ast = $this->parse("namespace App;\nclass C { public function h(\$request) { return \$request->input('x'); } }");
        $call = $this->firstCall($ast, 'input');

        $this->assertNull(ReceiverTypeResolver::resolve($call->var, $ast, FileImports::of($ast), FileImports::namespace($ast), $call));
    }

    public function test_returns_null_for_a_chained_receiver(): void
    {
        $ast = $this->parse("namespace App;\nclass C { public function h() { return foo()->get('x'); } }");
        $call = $this->firstCall($ast, 'get');

        $this->assertNull(ReceiverTypeResolver::resolve($call->var, $ast, FileImports::of($ast), FileImports::namespace($ast), $call));
    }

    public function test_enclosing_class_and_function(): void
    {
        $ast = $this->parse("namespace App;\nclass Outer { public function m() { return \$x->get('x'); } }");
        $call = $this->firstCall($ast, 'get');

        $this->assertSame('Outer', ReceiverTypeResolver::enclosingClass($call, $ast)?->name?->toString());
        $this->assertSame('m', ReceiverTypeResolver::enclosingFunction($call, $ast)?->name?->toString());
    }
}
