<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\ParamList;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use PHPUnit\Framework\TestCase;

final class ParamListTest extends TestCase
{
    public function test_a_parameter_is_found_by_its_name(): void
    {
        $params = $this->paramsOf('public function pay(int $cents, string $currency): void {}');

        $this->assertSame('currency', $this->nameOf($params->named('currency')->unwrap()));
        $this->assertTrue($params->named('missing')->isNone());
    }

    public function test_a_parameter_is_found_by_its_position(): void
    {
        $params = $this->paramsOf('public function pay(int $cents, string $currency): void {}');

        $this->assertSame('cents', $this->nameOf($params->at(0)->unwrap()));
        $this->assertSame('currency', $this->nameOf($params->at(1)->unwrap()));
        $this->assertTrue($params->at(2)->isNone());
    }

    public function test_a_missing_declaration_answers_the_same_questions(): void
    {
        // A caller whose method could not be resolved asks the list the same way and gets none back,
        // rather than having to guard the function itself.
        $params = ParamList::of(null);

        $this->assertTrue($params->isEmpty());
        $this->assertTrue($params->named('cents')->isNone());
        $this->assertTrue($params->at(0)->isNone());
    }

    private function paramsOf(string $method): ParamList
    {
        $codebase = Codebase::fromString("<?php\nfinal class Till\n{\n    {$method}\n}\n");
        $declaration = $codebase->whereMethodDeclaration()->get()[0]->node;

        $this->assertInstanceOf(ClassMethod::class, $declaration);

        return ParamList::of($declaration);
    }

    private function nameOf(Param $param): string
    {
        return (string) $param->var->name;
    }
}
