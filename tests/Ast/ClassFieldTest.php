<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast;

use JesseGall\CodeCommandments\Ast\Codebase;
use PhpParser\Node\Stmt\ClassMethod;
use PHPUnit\Framework\TestCase;

final class ClassFieldTest extends TestCase
{
    public function test_fields_reads_promoted_params_and_declared_properties(): void
    {
        $class = $this->classNamed(<<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Attributes\Hidden;
        use Spatie\LaravelData\Attributes\FromContainer;
        class Page {
            public readonly Canvas $canvas;
            private int $count = 0;
            public function __construct(
                #[Hidden] #[FromContainer(Service::class)]
                public readonly Service $service,
                protected string $id,
            ) {}
        }
        PHP);

        $fields = $class->fields();
        $names = array_map(static fn ($f): string => $f->name, $fields);

        // Promoted params come first (in signature order), then declared properties.
        $this->assertSame(['service', 'id', 'canvas', 'count'], $names);

        $byName = [];
        foreach ($fields as $field) {
            $byName[$field->name] = $field;
        }

        $this->assertTrue($byName['service']->isPromoted);
        $this->assertTrue($byName['service']->isPublic);
        $this->assertTrue($byName['service']->hasAttribute('Hidden'));
        $this->assertTrue($byName['service']->hasAttribute('FromContainer'));
        $this->assertFalse($byName['service']->hasAttribute('Computed'));

        $this->assertFalse($byName['id']->isPublic, 'protected promoted param');
        $this->assertFalse($byName['canvas']->isPromoted, 'declared property');
        $this->assertFalse($byName['count']->isPublic, 'private property');
        $this->assertSame([], $byName['canvas']->attributeNames());
    }

    public function test_get_constructor_runs_the_closure_only_when_present(): void
    {
        $withCtor = $this->classNamed(<<<'PHP'
        <?php
        namespace App;
        class HasCtor {
            public function __construct(public int $a, public int $b) {}
        }
        PHP);

        $paramCount = $withCtor->fromConstructor(static fn (ClassMethod $c): int => count($c->params));
        $this->assertSame(2, $paramCount);
        $this->assertInstanceOf(ClassMethod::class, $withCtor->getConstructor());

        $noCtor = $this->classNamed(<<<'PHP'
        <?php
        namespace App;
        class NoCtor { public int $x = 1; }
        PHP);

        $this->assertNull($noCtor->fromConstructor(static fn (ClassMethod $c): int => count($c->params)));
        $this->assertNull($noCtor->getConstructor());
    }

    private function classNamed(string $php): \JesseGall\CodeCommandments\Ast\AstNode
    {
        $codebase = Codebase::fromString($php, '/proj/app/File.php');

        return $codebase->whereClass()->first() ?? $codebase->classNamed(null);
    }
}
