<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use JesseGall\CodeCommandments\Scanners\GenericFileScanner;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\JudgeService;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;
use JesseGall\CodeCommandments\Tests\Fixtures\Prophets\ProfileMarkerProphet;
use JesseGall\CodeCommandments\Tracking\JsonConfessionTracker;
use PHPUnit\Framework\TestCase;

/**
 * Integration: the active profile drives JudgeService — warning suppression
 * (sins-only), default scope (staged/branch/full), and the gate exit code.
 */
class JudgeProfileBehaviourTest extends TestCase
{
    private string $dir;

    private ScrollManager $manager;

    private ProphetRegistry $registry;

    private JsonConfessionTracker $tracker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-judge-profile-' . uniqid();
        mkdir($this->dir, 0755, true);
        $this->git('init -q -b main');
        $this->git('config user.email t@t');
        $this->git('config user.name t');

        Environment::setBasePath($this->dir);

        $this->registry = new ProphetRegistry();
        $this->registry->registerMany('test', [ProfileMarkerProphet::class => []]);
        $this->registry->setScrollConfig('test', ['path' => $this->dir, 'extensions' => ['php']]);

        $this->manager = new ScrollManager($this->registry, new GenericFileScanner());
        $this->tracker = new JsonConfessionTracker($this->dir . '/.commandments/confessions.json', new Filesystem());
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf ' . escapeshellarg($this->dir));
        parent::tearDown();
    }

    private function git(string $args): void
    {
        shell_exec('git -C ' . escapeshellarg($this->dir) . ' ' . $args . ' 2>/dev/null');
    }

    private function file(string $name, string $body): string
    {
        file_put_contents($this->dir . '/' . $name, "<?php\n// {$body}\n");

        return $this->dir . '/' . $name;
    }

    private function setProfile(string $name): void
    {
        @mkdir($this->dir . '/.commandments', 0755, true);
        file_put_contents($this->dir . '/.commandments/profile', $name);
    }

    /** @return array{0:int,1:string} */
    private function judge(array $opts): array
    {
        $out = [];
        $sink = function (string $l) use (&$out): void {
            $out[] = $l;
        };
        $code = (new JudgeService($this->manager, $this->registry, $this->tracker, 'commandments', ' ', $sink, $sink))
            ->run($opts + ['no_cache' => true]);

        return [$code, implode("\n", $out)];
    }

    // --- warning suppression (allowWarnings) ---

    public function test_sins_only_suppresses_warnings(): void
    {
        $this->setProfile('sins-only');
        $this->file('Warn.php', 'WARN_ME');

        [$code, $out] = $this->judge(['path' => $this->dir]);

        $this->assertSame(JudgeService::SUCCESS, $code);
        $this->assertStringNotContainsString('WARN_ME found', $out);
        $this->assertStringNotContainsString('WARNINGS', $out);
    }

    public function test_phased_shows_warnings_but_a_warning_only_file_does_not_block(): void
    {
        $this->setProfile('phased');
        $this->file('Warn.php', 'WARN_ME');

        [$code, $out] = $this->judge(['path' => $this->dir]);

        $this->assertSame(JudgeService::SUCCESS, $code, 'a warning-only non-staged judge must not block');
        $this->assertStringContainsString('WARNINGS', $out);
        $this->assertStringContainsString('ProfileMarker', $out);
    }

    public function test_sins_only_keeps_sins_and_drops_warnings(): void
    {
        $this->setProfile('sins-only');
        $this->file('Both.php', 'SIN_ME WARN_ME');

        [$code, $out] = $this->judge(['path' => $this->dir]);

        $this->assertSame(JudgeService::FAILURE, $code);
        $this->assertStringContainsString('SINS', $out);
        $this->assertStringNotContainsString('WARN_ME found', $out);
    }

    public function test_a_sin_always_blocks(): void
    {
        $this->setProfile('phased');
        $this->file('Sin.php', 'SIN_ME');

        [$code, $out] = $this->judge(['path' => $this->dir]);

        $this->assertSame(JudgeService::FAILURE, $code);
        $this->assertStringContainsString('SINS', $out);
    }

    // --- profile-driven default scope ---

    public function test_grind_bare_judge_uses_branch_scope_and_sees_committed_work(): void
    {
        $this->file('base.php', 'clean');
        $this->git('add -A');
        $this->git('commit -qm base');

        $this->git('checkout -q -b feature');
        $this->file('feature.php', 'WARN_ME');
        $this->git('add -A');
        $this->git('commit -qm feature');

        $this->setProfile('grind');

        [$code, $out] = $this->judge([]); // bare judge → profile scope (branch)

        $this->assertSame(JudgeService::SUCCESS, $code, 'grind flags warnings but does not block');
        $this->assertStringContainsString('WARNINGS', $out);
        $this->assertStringContainsString('ProfileMarker', $out);
    }

    public function test_phased_bare_judge_uses_staged_scope(): void
    {
        $this->file('base.php', 'clean');
        $this->git('add -A');
        $this->git('commit -qm base');

        $this->setProfile('phased');
        $this->file('Sin.php', 'SIN_ME'); // unstaged

        [$codeUnstaged] = $this->judge([]);
        $this->assertSame(JudgeService::SUCCESS, $codeUnstaged, 'unstaged sin is out of staged scope');

        $this->git('add Sin.php');
        [$codeStaged, $out] = $this->judge([]);
        $this->assertSame(JudgeService::FAILURE, $codeStaged);
        $this->assertStringContainsString('SINS', $out);
    }

    public function test_no_profile_bare_judge_is_full_scan(): void
    {
        // No .commandments/profile → explicit scope is null → full scan finds the
        // unstaged sin (historical default behavior preserved).
        $this->file('Sin.php', 'SIN_ME');

        [$code, $out] = $this->judge([]);

        $this->assertSame(JudgeService::FAILURE, $code);
        $this->assertStringContainsString('SINS', $out);
    }

    public function test_no_profile_audits_the_whole_scroll_under_an_active_profile(): void
    {
        // phased would narrow a bare judge to staged scope (and miss an unstaged
        // sin); --no-profile ignores that and scans the whole scroll.
        $this->setProfile('phased');
        $this->file('base.php', 'clean');
        $this->git('add -A');
        $this->git('commit -qm base');
        $this->file('Sin.php', 'SIN_ME'); // unstaged

        [$staged] = $this->judge([]);
        $this->assertSame(JudgeService::SUCCESS, $staged, 'phased bare judge only sees staged files');

        [$code, $out] = $this->judge(['no_profile' => true]);
        $this->assertSame(JudgeService::FAILURE, $code);
        $this->assertStringContainsString('SINS', $out);
    }

    public function test_no_profile_shows_warnings_even_under_sins_only(): void
    {
        $this->setProfile('sins-only');
        $this->file('Warn.php', 'WARN_ME');

        [$suppressed, $outA] = $this->judge(['path' => $this->dir]);
        $this->assertStringNotContainsString('WARNINGS', $outA);

        [, $outB] = $this->judge(['path' => $this->dir, 'no_profile' => true]);
        $this->assertStringContainsString('WARNINGS', $outB);
    }

    public function test_branch_and_git_scopes_are_mutually_exclusive(): void
    {
        [$code, $out] = $this->judge(['git' => true, 'branch' => true]);

        $this->assertSame(JudgeService::FAILURE, $code);
        $this->assertStringContainsString('mutually exclusive', $out);
    }
}
