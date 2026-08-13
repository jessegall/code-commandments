<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Repent;
use JesseGall\CodeCommandments\Hooks\HookIO;
use PHPUnit\Framework\TestCase;

/**
 * A rewriter that BREAKS costs its own fixes and nothing else (#456, #459). One custom scribe calling a
 * method that does not exist used to fatal the whole command, so no sin in the run could be auto-fixed —
 * the stacked docblocks and redundant return types beside it went unrepented too.
 */
final class BrokenScribeSurvivesTest extends TestCase
{
    private string $dir = '';

    protected function tearDown(): void
    {
        if ($this->dir !== '') {
            exec('rm -rf ' . escapeshellarg($this->dir));
        }
    }

    public function test_a_broken_rewriter_does_not_take_the_other_fixes_down(): void
    {
        $this->project(<<<'PHP'
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
            PHP);

        $this->breakOneStep();

        $code = new Repent(new HookIO(new FakeGit($this->dir)))->run(Input::of('repent', [$this->dir]));

        $this->assertSame(3, $code, 'a run that could not run a rewriter is neither a success nor a usage error');
        $this->assertStringContainsString(
            'public static function fromTag(Tag $tag)',
            (string) file_get_contents($this->dir . '/TagData.php'),
            'every other fix still applied',
        );
    }

    public function test_a_run_where_every_rewriter_ran_still_succeeds(): void
    {
        $this->project("<?php\nnamespace Demo;\nfinal class Plain {}\n");

        $code = new Repent(new HookIO(new FakeGit($this->dir)))->run(Input::of('repent', [$this->dir]));

        $this->assertSame(0, $code);
    }

    /**
     * A `.commandments/repent.php` that appends a step which throws the moment it runs — the shape of a
     * project's own scribe reaching for a `Writer` method the package does not declare.
     */
    private function breakOneStep(): void
    {
        mkdir($this->dir . '/.commandments', 0777, true);

        file_put_contents($this->dir . '/.commandments/repent.php', <<<'PHP'
            <?php

            use JesseGall\CodeCommandments\Cli\Scope\Scope;
            use JesseGall\CodeCommandments\Scribes\ScribeChain;
            use JesseGall\CodeCommandments\Scribes\ScribeStep;
            use JesseGall\CodeCommandments\WorkingCopy;

            return static fn (ScribeChain $chain): ScribeChain => $chain->append(new class implements ScribeStep
            {
                public function name(): string
                {
                    return 'BrokenScribe';
                }

                public function run(string|array $path, Scope $scope, WorkingCopy $overlay = new WorkingCopy()): array
                {
                    throw new \Error('Call to undefined method Writer::rewriteRange()');
                }
            });
            PHP);
    }

    private function project(string $data): void
    {
        $this->dir = sys_get_temp_dir() . '/cc-broken-scribe-' . uniqid('', true);
        mkdir($this->dir, 0777, true);

        file_put_contents(
            $this->dir . '/Spatie.php',
            "<?php\nnamespace Spatie\\LaravelData { class Data { public static function collect(\$items) {} } }\n",
        );
        file_put_contents($this->dir . '/TagData.php', $data);
    }
}
