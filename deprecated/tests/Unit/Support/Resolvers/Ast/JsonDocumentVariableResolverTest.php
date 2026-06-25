<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support\Resolvers\Ast;

use JesseGall\CodeCommandments\Support\Resolvers\Ast\JsonDocumentVariableResolver;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class JsonDocumentVariableResolverTest extends TestCase
{
    private JsonDocumentVariableResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new JsonDocumentVariableResolver;
    }

    public function test_a_variable_assigned_from_json_decode_is_a_json_document(): void
    {
        $this->assertTrue($this->resolve('doc', '$doc = json_decode($raw, true); $doc["x"] = 1;'));
    }

    public function test_a_variable_handed_to_json_encode_is_a_json_document(): void
    {
        $this->assertTrue($this->resolve('data', '$data["scripts"] = []; return json_encode($data);'));
    }

    public function test_a_decode_via_a_wrapping_expression_still_counts(): void
    {
        $this->assertTrue($this->resolve('cfg', '$cfg = json_decode((string) file_get_contents($p), true) ?? [];'));
    }

    public function test_a_plain_domain_array_is_not_a_json_document(): void
    {
        $this->assertFalse($this->resolve('order', '$order = $this->repo->find(1); return $order["total"];'));
    }

    public function test_a_different_variable_in_scope_is_not_conflated(): void
    {
        // $other is decoded, but we ask about $row.
        $this->assertFalse($this->resolve('row', '$other = json_decode($raw, true); return $row["id"];'));
    }

    private function resolve(string $varName, string $body): bool
    {
        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse("<?php\nfunction f(\$raw, \$p, \$row) {\n{$body}\n}");
        $function = (new NodeFinder)->findFirstInstanceOf((array) $ast, Node\Stmt\Function_::class);
        self::assertInstanceOf(Node\FunctionLike::class, $function);

        $variable = null;
        foreach ((new NodeFinder)->findInstanceOf((array) $function->getStmts(), Expr\Variable::class) as $candidate) {
            if ($candidate->name === $varName) {
                $variable = $candidate;
                break;
            }
        }

        self::assertInstanceOf(Expr\Variable::class, $variable, "no \${$varName} in body");

        return $this->resolver->isJsonDocument($variable, $function);
    }
}
