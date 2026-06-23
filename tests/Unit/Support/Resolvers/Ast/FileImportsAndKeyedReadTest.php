<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support\Resolvers\Ast;

use JesseGall\CodeCommandments\Support\Resolvers\Ast\FileImports;
use JesseGall\CodeCommandments\Support\Resolvers\Ast\KeyedReadResolver;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class FileImportsAndKeyedReadTest extends TestCase
{
    /** @return array<\PhpParser\Node> */
    private function parse(string $body): array
    {
        return (new ParserFactory)->createForNewestSupportedVersion()->parse("<?php\n" . $body) ?? [];
    }

    public function test_file_imports_maps_aliases_to_fqcns(): void
    {
        $ast = $this->parse("namespace App;\nuse Illuminate\\Http\\Request;\nuse App\\Foo\\Bar as Baz;\nclass C {}");

        $this->assertSame([
            'Request' => 'Illuminate\\Http\\Request',
            'Baz' => 'App\\Foo\\Bar',
        ], FileImports::of($ast));
        $this->assertSame('App', FileImports::namespace($ast));
    }

    public function test_file_imports_namespace_is_null_in_global_scope(): void
    {
        $this->assertNull(FileImports::namespace($this->parse("class C {}")));
    }

    public function test_keyed_read_method_call_with_key_and_default(): void
    {
        $ast = $this->parse("\$x = \$request->get('id', 5);");
        $call = (new NodeFinder)->findFirstInstanceOf($ast, Expr\MethodCall::class);

        $read = KeyedReadResolver::resolve($call);

        $this->assertSame('id', $read->key);
        $this->assertSame('get', $read->getter);
        $this->assertInstanceOf(Expr::class, $read->default);
    }

    public function test_keyed_read_array_access(): void
    {
        $ast = $this->parse("\$x = \$request['id'];");
        $fetch = (new NodeFinder)->findFirstInstanceOf($ast, Expr\ArrayDimFetch::class);

        $read = KeyedReadResolver::resolve($fetch);

        $this->assertSame('id', $read->key);
        $this->assertSame('get', $read->getter);
        $this->assertNull($read->default);
    }

    public function test_keyed_read_ignores_non_getter_and_dynamic_key(): void
    {
        $ast = $this->parse("\$a = \$request->path(); \$b = \$request->get(\$dynamic);");
        $calls = (new NodeFinder)->findInstanceOf($ast, Expr\MethodCall::class);

        foreach ($calls as $call) {
            $this->assertNull(KeyedReadResolver::resolve($call), $call->name->toString() . ' should not be a keyed read');
        }
    }
}
