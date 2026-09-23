<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Py;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\WorkingCopy;
use PHPUnit\Framework\TestCase;

/**
 * A Python rule composes what a PHP or a TypeScript rule composes: a selector opens a query,
 * `where`/`reject` narrow it, and a terminal returns matches that know their `file:line`.
 */
final class QuerySurfaceTest extends TestCase
{
    private const string SOURCE = <<<'PY'
        import os


        def load(path):
            return os.listdir(path)


        class Store:
            limit = 10

            def rows(self):
                return [row for row in load(".") if row]

            def clear(self):
                self.rows().clear()
        PY;

    public function test_where_function_finds_module_functions_and_methods(): void
    {
        $this->assertSame(['FunctionDef load', 'FunctionDef rows', 'FunctionDef clear'], $this->scopes($this->codebase()->whereFunction()->get()));
    }

    public function test_where_method_declaration_finds_only_methods(): void
    {
        $this->assertSame(['FunctionDef rows', 'FunctionDef clear'], $this->scopes($this->codebase()->whereMethodDeclaration()->get()));
    }

    public function test_where_class_finds_classes(): void
    {
        $this->assertSame(['ClassDef Store'], $this->scopes($this->codebase()->whereClass()->get()));
    }

    public function test_where_and_reject_narrow_with_a_typed_closure(): void
    {
        $found = $this->codebase()
            ->whereFunction()
            ->where(static fn (NodeMatch $match): bool => $match->isMethod())
            ->reject(static fn (NodeMatch $match): bool => $match->name() === 'clear')
            ->get();

        $this->assertSame(['FunctionDef rows'], $this->scopes($found));
    }

    public function test_a_match_knows_its_file_and_line(): void
    {
        $load = $this->codebase()->whereFunction()->first();

        $this->assertSame('shop/store.py:4', $load->location());
        $this->assertStringStartsWith('def load(path):', $load->span()->text());
    }

    public function test_where_call_reaches_calls_at_any_depth(): void
    {
        $calls = $this->codebase()->whereCall()->get();

        $this->assertSame(['os.listdir', 'load', 'self.rows().clear', 'self.rows'], array_map(static fn (ExprMatch $call): string => $call->callName(), $calls));
        $this->assertSame('shop/store.py:12', $calls[1]->location());
    }

    public function test_where_file_lists_every_module(): void
    {
        $this->assertSame(['shop/store.py:1'], $this->codebase()->whereFile()->locations());
    }

    public function test_a_scan_reads_python_files_through_the_overlay(): void
    {
        $root = sys_get_temp_dir() . '/cc-py-scan-' . uniqid();
        mkdir("{$root}/__pycache__", 0777, true);
        file_put_contents("{$root}/orders.py", "def total():\n    return 1\n");
        file_put_contents("{$root}/__pycache__/orders.py", "def cached():\n    return 1\n");

        $codebase = Codebase::scan($root, new WorkingCopy(["{$root}/orders.py" => "def pending():\n    return 2\n"]));

        exec('rm -rf ' . escapeshellarg($root));

        $this->assertSame(['FunctionDef pending'], $this->scopes($codebase->whereFunction()->get()));
    }

    public function test_focusing_keeps_only_the_given_files(): void
    {
        $codebase = new Codebase(['a.py' => "def a():\n    pass\n", 'b.py' => "def b():\n    pass\n"]);

        $this->assertSame(['FunctionDef b'], $this->scopes($codebase->focusedOn('b.py')->whereFunction()->get()));
    }

    private function codebase(): Codebase
    {
        return Codebase::fromString(self::SOURCE, 'shop/store.py');
    }

    /**
     * @param  list<NodeMatch>  $matches
     * @return list<string>
     */
    private function scopes(array $matches): array
    {
        return array_map(static fn (NodeMatch $match): string => $match->scope(), $matches);
    }
}
