<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Report;

use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Report\GitHubIssue;
use JesseGall\CodeCommandments\Cli\Report\Report;
use PHPUnit\Framework\TestCase;

/**
 * The report command must carry its CODE ORIGIN: a bug-report requires ≥1 `--ref` (or `--global`),
 * and every ref's source is injected into the issue body — a bug spanning files references each.
 */
final class ReportTest extends TestCase
{
    private string $a;

    private string $b;

    protected function setUp(): void
    {
        $this->a = sys_get_temp_dir() . '/cc-report-a-' . uniqid('', true) . '.php';
        $this->b = sys_get_temp_dir() . '/cc-report-b-' . uniqid('', true) . '.vue';
        file_put_contents($this->a, "<?php\nfunction alpha() {\n    return 1;\n}\n");
        file_put_contents($this->b, "<template>\n  <div>beta</div>\n</template>\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->a);
        @unlink($this->b);
    }

    private function filer(): GitHubIssue
    {
        return new class extends GitHubIssue {
            public string $title = '';

            public string $body = '';

            public function file(string $title, string $body): int
            {
                $this->title = $title;
                $this->body = $body;

                return 0;
            }
        };
    }

    private function fire(GitHubIssue $filer, string ...$args): int
    {
        ob_start();
        $code = new Report($filer)->run(Input::of('report', $args));
        ob_get_clean();

        return $code;
    }

    public function test_a_bug_report_without_a_ref_is_rejected(): void
    {
        $filer = $this->filer();

        $this->assertSame(2, $this->fire($filer, '--reason=it broke'), 'a bug-report needs a code origin');
        $this->assertSame('', $filer->title, 'nothing was filed');
    }

    public function test_global_opts_out_of_the_ref_requirement(): void
    {
        $filer = $this->filer();

        $this->assertSame(0, $this->fire($filer, '--reason=cli crashes with no args', '--global'));
        $this->assertStringContainsString('[bug-report]', $filer->title);
    }

    public function test_it_injects_every_referenced_file(): void
    {
        $filer = $this->filer();

        $this->assertSame(0, $this->fire(
            $filer,
            '--reason=the extract broke across two files',
            "--ref={$this->a}:2-3",
            "--ref={$this->b}:2",
        ));

        $this->assertStringContainsString('function alpha()', $filer->body, 'first file injected');
        $this->assertStringContainsString('<div>beta</div>', $filer->body, 'second file injected');
        $this->assertStringContainsString(':2-3`', $filer->body, 'the range is shown');
        $this->assertStringContainsString('```php', $filer->body);
        $this->assertStringContainsString('```vue', $filer->body);
    }

    public function test_legacy_file_and_line_still_work_as_one_ref(): void
    {
        $filer = $this->filer();

        $this->assertSame(0, $this->fire($filer, '--reason=x', "--file={$this->a}", '--line=2'));
        $this->assertStringContainsString('function alpha()', $filer->body);
    }

    public function test_an_unmarked_detector_report_files_without_a_best_design(): void
    {
        $filer = $this->filer();

        $this->assertSame(0, $this->fire($filer, '--detector=SomeDetector', '--reason=false positive'));
        $this->assertStringContainsString('[detector-report] SomeDetector', $filer->title);
    }

    public function test_a_marked_detector_report_requires_a_best_design(): void
    {
        $filer = $this->filer();

        $this->assertSame(2, $this->fire($filer, '--detector=AllNullableDataDetector', '--reason=false positive'));
        $this->assertSame('', $filer->title, 'a RequiresBestDesign detector is not filed without --best-design');
    }

    public function test_the_best_design_requirement_is_lenient_on_the_detector_suffix(): void
    {
        $filer = $this->filer();

        // Same detector, named without the `Detector` suffix — still resolved, still required.
        $this->assertSame(2, $this->fire($filer, '--detector=AllNullableData', '--reason=false positive'));
        $this->assertSame('', $filer->title);
    }

    public function test_a_marked_detector_report_injects_the_best_design(): void
    {
        $filer = $this->filer();

        $this->assertSame(0, $this->fire(
            $filer,
            '--detector=AllNullableDataDetector',
            '--reason=the flagged shape is the truthful contract',
            '--best-design=an all-nullable Data is the honest shape here',
        ));

        $this->assertStringContainsString('Cleanest design the reporter can conceive', $filer->body);
        $this->assertStringContainsString('an all-nullable Data is the honest shape here', $filer->body);
    }

    public function test_a_hedged_detector_report_reason_is_rejected(): void
    {
        $filer = $this->filer();

        $this->assertSame(2, $this->fire(
            $filer,
            '--detector=AllNullableDataDetector',
            '--reason=the finding is real but it needs its own migration',
            '--best-design=a proper sum type',
        ));
        $this->assertSame('', $filer->title, 'a hedged report is not filed');
    }

    public function test_a_hedge_in_the_best_design_is_rejected(): void
    {
        $filer = $this->filer();

        $this->assertSame(2, $this->fire(
            $filer,
            '--detector=AllNullableDataDetector',
            '--reason=the code is correct as written',
            '--best-design=a sum type would be cleaner but that is a bigger refactor',
        ));
        $this->assertSame('', $filer->title);
    }

    public function test_a_flat_word_containing_but_does_not_trip_the_hedge_gate(): void
    {
        $filer = $this->filer();

        $this->assertSame(0, $this->fire(
            $filer,
            '--detector=AllNullableDataDetector',
            '--reason=the attribute distribution is the honest contract',
            '--best-design=the flagged code as written',
        ), 'the substring "but" inside "attribute"/"distribution" must not trip the gate');
    }
}
