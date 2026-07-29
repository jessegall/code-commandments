<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Report;

use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Judge\Finding;
use JesseGall\CodeCommandments\Cli\Report\GitHubIssue;
use JesseGall\CodeCommandments\Cli\Report\Report;
use JesseGall\CodeCommandments\Cli\Report\SinReport;
use JesseGall\CodeCommandments\Custom;
use JesseGall\CodeCommandments\Detectors\Backend\ArrayBagDetector;
use JesseGall\CodeCommandments\Tests\Cli\CapturingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use PHPUnit\Framework\TestCase;

/**
 * A finding from a project-local detector (`.commandments/custom/`) used to read exactly like a
 * shipped one, so a false positive in a rule the project WROTE was reported upstream against a
 * package that does not ship it (#413/#414). Every surface that names a rule says whose it is, and
 * `report --detector` refuses to file one the project owns.
 */
final class CustomRuleOwnershipTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-own-' . uniqid('', true);
        mkdir($this->root . '/.commandments/custom', 0777, true);
        Custom::forget();
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
        Custom::forget();
    }

    public function test_the_console_report_and_checklist_name_a_custom_rule_as_custom(): void
    {
        $report = new SinReport('/app', [
            new Finding('HandDispatchedRelayDetector', 'backend/house-style', 'hand-dispatched-relay', '/app/Screen.php', '/app/Screen.php:78', 'Screen::save', custom: true),
            new Finding('ArrayBagDetector', 'backend/house-style', 'array-bag', '/app/Screen.php', '/app/Screen.php:90', 'Screen::rows'),
        ]);

        $console = $report->console();
        $checklist = $report->checklist();

        $this->assertStringContainsString('[HandDispatchedRelayDetector (custom)]', $console);
        $this->assertStringContainsString('[ArrayBagDetector]', $console);
        $this->assertStringNotContainsString('[ArrayBagDetector (custom)]', $console, 'a shipped rule is not tagged');

        $this->assertStringContainsString('[HandDispatchedRelayDetector (custom)]', $checklist);
        $this->assertStringContainsString("THIS project's own", $checklist, 'and the section says where the fix belongs');
    }

    public function test_a_group_of_shipped_findings_says_nothing_about_custom_rules(): void
    {
        $report = new SinReport('/app', [
            new Finding('ArrayBagDetector', 'backend/house-style', 'array-bag', '/app/Screen.php', '/app/Screen.php:90', 'Screen::rows'),
        ]);

        $this->assertStringNotContainsString('custom', $report->console());
        $this->assertStringNotContainsString('custom', $report->checklist());
    }

    public function test_the_folder_is_the_whole_ownership_test(): void
    {
        $this->writeCustomDetector('OwnedRuleDetector');

        $own = Custom::detectors($this->root);

        $this->assertCount(1, $own);
        $this->assertTrue(Custom::owns($own[0], $this->root), 'a class from the custom folder is the project\'s');
        $this->assertFalse(Custom::owns(new ArrayBagDetector(), $this->root), 'a shipped one never is');
    }

    public function test_a_detector_report_against_the_projects_own_rule_is_refused(): void
    {
        $this->writeCustomDetector('OwnRuleDetector');

        $filer = new class extends GitHubIssue {
            public string $title = '';

            public function file(string $title, string $body): int
            {
                $this->title = $title;

                return 0;
            }
        };

        ob_start();
        $code = new Report($filer, new CapturingHookIO(new FakeGit($this->root)))->run(Input::of('report', [
            '--detector=OwnRuleDetector',
            '--reason=the flagged code is correct',
            '--global',
        ]));
        ob_get_clean();

        $this->assertSame(2, $code);
        $this->assertSame('', $filer->title, 'nothing is filed upstream about a rule this project wrote');
    }

    /**
     * A project's own detector, written where a real one lives — discovered BY FILE, so the folder is
     * the whole ownership test.
     */
    private function writeCustomDetector(string $class): void
    {
        file_put_contents($this->root . "/.commandments/custom/{$class}.php", <<<PHP
        <?php

        namespace Commandments;

        use JesseGall\CodeCommandments\Ast\Codebase;
        use JesseGall\CodeCommandments\Backend\Detector;
        use JesseGall\CodeCommandments\Sins\Backend\ArrayBag;
        use JesseGall\CodeCommandments\Sins\Sin;

        final class {$class} implements Detector
        {
            public function sin(): Sin
            {
                return new ArrayBag();
            }

            public function find(Codebase \$codebase): array
            {
                return [];
            }
        }
        PHP);
    }
}
