<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Tests\Concerns\TemporaryFolder;
use PHPUnit\Framework\TestCase;

/**
 * Scanning is TOTAL: one file the parser refuses costs that file, never the run. A real monorepo
 * carries the odd file PHP itself would reject — a stub, a half-finished edit, a fixture written to
 * be invalid — and a scan of thousands must not end on the first of them.
 */
final class CodebaseTotalityTest extends TestCase
{
    use TemporaryFolder;

    public function test_a_duplicate_use_alias_does_not_end_the_scan(): void
    {
        // Names are RESOLVED in a traversal that runs after the syntax parses, and the resolver
        // throws the same PhpParser\Error the parser does. Guarding only the parse left this one
        // escaping: a single such file aborted a 3,765-file run with an uncaught fatal.
        $this->give('Broken.php', "<?php\nnamespace App;\nuse App\Other\ResetPassword;\nuse Core\Notifications\ResetPassword;\nclass Broken {}\n");
        $this->give('Fine.php', "<?php\nnamespace App;\nclass Fine {}\n");

        $codebase = Codebase::scan($this->root);

        $this->assertSame(1, count($codebase->whereClass()->get()), 'the healthy file is read, the refused one contributes nothing');
    }

    public function test_a_syntax_error_does_not_end_the_scan(): void
    {
        $this->give('Broken.php', "<?php\nnamespace App;\nclass Broken { public function (\n");
        $this->give('Fine.php', "<?php\nnamespace App;\nclass Fine {}\n");

        $this->assertNotNull(Codebase::scan($this->root)->classNamed('App\Fine'));
    }

    private function give(string $name, string $php): void
    {
        file_put_contents("{$this->root}/{$name}", $php);
    }
}
