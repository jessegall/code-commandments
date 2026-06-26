<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use PHPUnit\Framework\TestCase;

final class CodebaseIndexTest extends TestCase
{
    public function test_method_declaration_selector_finds_nullable_object_finders(): void
    {
        $code = <<<'PHP'
        <?php
        class Workflow {}
        class Repo {
            public function find(int $id): ?Workflow { return null; }
            public function findOrFail(int $id): Workflow { return new Workflow; }
            public function name(int $id): ?string { return null; }
        }
        PHP;

        $finders = Codebase::fromString($code)
            ->whereMethodDeclaration()
            ->where(static fn (AstNode $node): bool => $node->returnsNullableObject())
            ->get();
        $names = array_map(static fn (NodeMatch $m): string => $m->enclosingFunctionName() ?? '?', $finders);

        // only the ?Workflow finder — not the total findOrFail, not the ?string scalar
        $this->assertSame(['find'], $names);
    }

    public function test_resolves_this_receiver_callers_and_denull(): void
    {
        $code = <<<'PHP'
        <?php
        class Workflow { public function record(): void {} }
        class Job {
            public function workflowFor(int $id): ?Workflow { return null; }
            public function handle(int $id): void {
                $this->workflowFor($id)?->record();
            }
            public function raw(int $id): void {
                $this->workflowFor($id);
            }
        }
        PHP;

        $callers = Codebase::fromString($code)->index()->callersOf('Job', 'workflowFor');

        $this->assertCount(2, $callers);

        $denulled = array_filter($callers, static fn (NodeMatch $m): bool => $m->isDeNulled());
        $this->assertCount(1, $denulled);
    }

    public function test_resolves_typed_property_receiver(): void
    {
        $code = <<<'PHP'
        <?php
        class Workflow {}
        class Repo {
            public function find(int $id): ?Workflow { return null; }
        }
        class Job {
            public function __construct(private readonly Repo $repo) {}
            public function handle(int $id): void {
                $this->repo->find($id) === null;
            }
        }
        PHP;

        $callers = Codebase::fromString($code)->index()->callersOf('Repo', 'find');

        $this->assertCount(1, $callers);
        $this->assertTrue($callers[0]->isDeNulled());
    }
}
