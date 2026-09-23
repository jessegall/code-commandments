<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Py;

use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Node\Block;
use JesseGall\CodeCommandments\Py\Node\ExceptHandler;
use JesseGall\CodeCommandments\Py\Node\MatchCase;
use JesseGall\CodeCommandments\Py\Node\Module;
use JesseGall\CodeCommandments\Py\Node\Node;
use JesseGall\CodeCommandments\Py\Node\Param;
use JesseGall\CodeCommandments\Py\Parser;
use JesseGall\CodeCommandments\Support\FileTree;
use PHPUnit\Framework\TestCase;

/**
 * The parser against a real Python codebase: every first-party file of agent-journal parses without a
 * degraded expression, and holds exactly as many statements as Python's own `ast` module counts in it —
 * so no statement is skipped or split. `PY_CORPUS=<dir>` points it at another codebase to prove one
 * before calibrating on it. Skipped where the corpus or a Python interpreter is not present.
 */
final class AgentJournalCorpusTest extends TestCase
{
    private const string CORPUS = '/projects/agent-journal';

    /**
     * Counts every statement of each file named on stdin, one per line, as Python itself reads it.
     */
    private const string AST_COUNTER = <<<'PY'
        import ast, json, sys
        print(json.dumps({path: sum(isinstance(node, ast.stmt) for node in ast.walk(ast.parse(open(path).read()))) for path in sys.stdin.read().split()}))
        PY;

    public function test_every_file_parses_whole_and_counts_as_python_counts_it(): void
    {
        $root = getenv('PY_CORPUS') ?: (string) getenv('HOME') . self::CORPUS;

        if (! is_dir($root) || trim((string) shell_exec('command -v python3')) === '') {
            $this->markTestSkipped('needs ~/projects/agent-journal and python3');
        }

        $files = iterator_to_array(FileTree::filesIn($root, 'py'), false);
        $expected = $this->pythonCounts($files);
        $mismatches = [];

        foreach ($files as $file) {
            $module = Parser::module((string) file_get_contents($file));

            $this->assertSame([], $this->degraded($module), "{$file} holds syntax the parser could not read");

            if (self::statements($module) !== $expected[$file]) {
                $mismatches[] = $file;
            }
        }

        $this->assertSame([], $mismatches, 'these files hold a different number of statements than Python reads in them');
    }

    /**
     * @param  list<string>  $files
     * @return array<string, int>
     */
    private function pythonCounts(array $files): array
    {
        $process = proc_open(['python3', '-c', self::AST_COUNTER], [0 => ['pipe', 'r'], 1 => ['pipe', 'w']], $pipes);
        fwrite($pipes[0], implode("\n", $files));
        fclose($pipes[0]);
        $counts = json_decode((string) stream_get_contents($pipes[1]), true, flags: JSON_THROW_ON_ERROR);
        proc_close($process);

        return $counts;
    }

    /**
     * The offsets of every expression the parser had to give up on.
     *
     * @return list<int>
     */
    private function degraded(Module $module): array
    {
        $offsets = [];

        foreach ($module->descendants() as $node) {
            foreach ($node->expressions() as $expression) {
                foreach ($expression->flatten() as $part) {
                    if ($part->is(ExprKind::Unknown)) {
                        $offsets[] = $part->start;
                    }
                }
            }
        }

        return $offsets;
    }

    /**
     * The statements of $module, counted the way Python's `ast.stmt` counts them — without the
     * blocks, parameters, handlers and cases the tree gives nodes of their own.
     */
    private static function statements(Module $module): int
    {
        return count(array_filter(
            $module->descendants(),
            static fn (Node $node): bool => ! ($node instanceof Block || $node instanceof Param || $node instanceof ExceptHandler || $node instanceof MatchCase),
        ));
    }
}
