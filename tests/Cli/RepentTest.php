<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Repent;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end tests for the `repent` command over a throwaway temp project. `repent` runs the
 * whole scribe chain to a FIXPOINT in one invocation (each sweep reads through the in-memory
 * {@see \JesseGall\CodeCommandments\WorkingCopy} overlay), so the two properties that matters
 * are proven here: it CONVERGES (a second run is a no-op) and every rewrite is valid PHP.
 */
final class RepentTest extends TestCase
{
    /** @var list<string> */
    private array $projects = [];

    private const string DATA_STUB = "<?php\nnamespace Spatie\\LaravelData { class Data { public static function collect(\$items) {} } }\n";

    protected function tearDown(): void
    {
        foreach ($this->projects as $dir) {
            $this->deleteDir($dir);
        }

        $this->projects = [];
    }

    public function test_converges_in_one_invocation_and_a_second_run_is_a_no_op(): void
    {
        // A Data class with a non-`from` factory to rename AND collected elsewhere: renaming the
        // factory, hinting `from`, hinting `collect`, and rewriting the call site are steps that
        // build on each other. One `repent` must settle ALL of it — the fixpoint — so a second
        // run finds nothing.
        $dir = $this->project([
            'NodeData.php' => <<<'PHP'
                <?php
                namespace Demo;
                use Spatie\LaravelData\Data;
                use Demo\Models\Node;

                final class NodeData extends Data
                {
                    public function __construct(public readonly string $key) {}

                    public static function forNode(Node $node): self
                    {
                        return self::from(['key' => $node->key]);
                    }
                }
                PHP,
            'Listing.php' => <<<'PHP'
                <?php
                namespace Demo;
                class Listing
                {
                    public function rows(array $nodes)
                    {
                        return NodeData::collect($nodes);
                    }

                    public function one($node)
                    {
                        return NodeData::forNode($node);
                    }
                }
                PHP,
        ]);

        $this->assertSame(0, $this->repent([$dir])['code']);

        // The rename + BOTH hints landed in the single run.
        $data = $this->read($dir, 'NodeData.php');
        $this->assertStringContainsString('public static function fromNode(Node $node)', $data, 'the factory is renamed');
        $this->assertStringContainsString('@method static static from(Node $node)', $data, 'the from hint is present');
        $this->assertStringContainsString('collect(iterable $items)', $data, 'the collect hint is present in the SAME run (it used to lag a pass)');
        $this->assertStringContainsString('NodeData::from($node)', $this->read($dir, 'Listing.php'), 'the call site is rewritten');

        $this->assertValidPhp($dir);

        // The decisive property: a second run changes nothing (converged in one).
        $converged = $this->snapshot($dir);
        $this->assertSame(0, $this->repent([$dir])['code']);
        $this->assertSame($converged, $this->snapshot($dir), 'a second repent must be a no-op');
    }

    public function test_dry_run_reflects_the_converged_result_without_writing(): void
    {
        $dir = $this->project([
            'TagData.php' => <<<'PHP'
                <?php
                namespace Demo;
                use Spatie\LaravelData\Data;
                use Demo\Models\Tag;

                final class TagData extends Data
                {
                    public function __construct(public readonly string $label) {}

                    public static function forTag(Tag $tag): self
                    {
                        return self::from(['label' => $tag->label]);
                    }
                }
                PHP,
        ]);

        $before = $this->read($dir, 'TagData.php');
        $diffFile = $dir . '/out.diff';

        $preview = $this->repent([$dir, '--dry-run=' . $diffFile]);
        $this->assertSame(0, $preview['code']);
        $this->assertSame($before, $this->read($dir, 'TagData.php'), 'a dry-run must not touch the tree');

        $diff = (string) file_get_contents($diffFile);
        $this->assertStringContainsString('+    public static function fromTag(Tag $tag)', $diff);
    }

    /**
     * @param  list<string>  $args
     * @return array{code: int, out: string}
     */
    private function repent(array $args): array
    {
        ob_start();
        $code = new Repent()->run($args);
        $out = (string) ob_get_clean();

        return ['code' => $code, 'out' => $out];
    }

    private function assertValidPhp(string $dir): void
    {
        foreach (glob($dir . '/*.php') ?: [] as $file) {
            exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $status);
            $this->assertSame(0, $status, "rewritten file does not parse: {$file}\n" . implode("\n", $out));
        }
    }

    /**
     * @param  array<string, string>  $files
     */
    private function project(array $files): string
    {
        $dir = sys_get_temp_dir() . '/cc-repent-' . uniqid('', true);
        mkdir($dir, 0777, true);
        $this->projects[] = $dir;

        file_put_contents($dir . '/Spatie.php', self::DATA_STUB);

        foreach ($files as $name => $contents) {
            file_put_contents($dir . '/' . $name, $contents . "\n");
        }

        return $dir;
    }

    private function read(string $dir, string $name): string
    {
        return (string) file_get_contents($dir . '/' . $name);
    }

    /**
     * Every PHP file's content, keyed by path — a whole-tree snapshot to compare runs by.
     *
     * @return array<string, string>
     */
    private function snapshot(string $dir): array
    {
        $files = [];

        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $files[$file] = (string) file_get_contents($file);
        }

        return $files;
    }

    private function deleteDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $file) {
            is_dir($file) ? $this->deleteDir($file) : @unlink($file);
        }

        @rmdir($dir);
    }
}
