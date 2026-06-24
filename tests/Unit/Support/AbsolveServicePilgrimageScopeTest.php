<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use Illuminate\Filesystem\Filesystem;
use JesseGall\CodeCommandments\Prophets\Backend\NoRawRequestProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferOptionOverNullProphet;
use JesseGall\CodeCommandments\Scanners\GenericFileScanner;
use JesseGall\CodeCommandments\Support\AbsolveService;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageRunner;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;
use JesseGall\CodeCommandments\Tracking\JsonConfessionTracker;
use PHPUnit\Framework\TestCase;

/**
 * While a pilgrimage is active for the current session, `absolve` is clamped to the
 * prophet the walk is on: the bulk verbs are refused and a mismatched --prophet errors,
 * so the agent can't bulk-dismiss or wander off the walk.
 */
class AbsolveServicePilgrimageScopeTest extends TestCase
{
    private string $dir;

    private ScrollManager $manager;

    private ProphetRegistry $registry;

    private JsonConfessionTracker $tracker;

    private string|false $previousSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . '/cc-abscope-' . uniqid();
        mkdir($this->dir . '/.commandments', 0755, true);
        Environment::setBasePath($this->dir);

        file_put_contents($this->dir . '/ServiceController.php', <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Http\Request;
        class ServiceController {
            public function store(Request $request): mixed { return $request->input('name'); }
        }
        PHP);

        $prophets = [PreferOptionOverNullProphet::class, NoRawRequestProphet::class];

        file_put_contents($this->dir . '/commandments.php', '<?php return ' . var_export([
            'scrolls' => ['backend' => [
                'path' => $this->dir,
                'extensions' => ['php'],
                'exclude' => [],
                'prophets' => $prophets,
            ]],
        ], true) . ';');

        $this->registry = new ProphetRegistry();
        $this->registry->registerMany('backend', $prophets);
        $this->registry->setScrollConfig('backend', [
            'path' => $this->dir,
            'extensions' => ['php'],
            'exclude' => [],
            'prophets' => $prophets,
        ]);
        $this->manager = new ScrollManager($this->registry, new GenericFileScanner());
        $this->tracker = new JsonConfessionTracker($this->dir . '/.commandments/confessions.json', new Filesystem());

        $this->previousSession = getenv('CLAUDE_CODE_SESSION_ID');
        putenv('CLAUDE_CODE_SESSION_ID=sess-A');

        // Begin a real walk so the persisted cursor lands on an actual prophet (the
        // NoRawRequest sin in the controller), owned by this session.
        (new PilgrimageRunner($this->dir, ['scrolls' => ['backend' => [
            'path' => $this->dir,
            'extensions' => ['php'],
            'exclude' => [],
            'prophets' => $prophets,
        ]]], 'backend'))->begin();
    }

    protected function tearDown(): void
    {
        $this->previousSession === false
            ? putenv('CLAUDE_CODE_SESSION_ID')
            : putenv('CLAUDE_CODE_SESSION_ID=' . $this->previousSession);

        shell_exec('rm -rf ' . escapeshellarg($this->dir));
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $opts
     * @return array{0: int, 1: list<string>}
     */
    private function absolve(array $opts): array
    {
        $errors = [];

        $status = AbsolveService::run(
            $this->manager,
            $this->registry,
            $this->tracker,
            $opts,
            $this->dir,
            static fn (string $l) => null,
            function (string $l) use (&$errors): void {
                $errors[] = $l;
            },
        );

        return [$status, $errors];
    }

    public function test_refuses_bulk_baseline_mid_walk(): void
    {
        [$status, $errors] = $this->absolve(['all' => true, 'reason' => 'x']);

        $this->assertSame(AbsolveService::FAILURE, $status);
        $this->assertStringContainsString('one finding at a time', implode("\n", $errors));
    }

    public function test_refuses_batch_warnings_mid_walk(): void
    {
        [$status, $errors] = $this->absolve(['warnings' => true, 'reason' => 'x']);

        $this->assertSame(AbsolveService::FAILURE, $status);
        $this->assertStringContainsString('one finding at a time', implode("\n", $errors));
    }

    public function test_errors_on_a_mismatched_prophet(): void
    {
        [$status, $errors] = $this->absolve(['at' => 'ServiceController.php:5', 'prophet' => 'ZzzNoSuchProphet', 'reason' => 'x']);

        $this->assertSame(AbsolveService::FAILURE, $status);
        $this->assertStringContainsString('scoped to the current pilgrimage prophet', implode("\n", $errors));
    }

    public function test_refuses_a_bare_fingerprint_mid_walk(): void
    {
        [$status, $errors] = $this->absolve(['fingerprint' => 'deadbeef', 'reason' => 'x']);

        $this->assertSame(AbsolveService::FAILURE, $status);
        $this->assertStringContainsString('by location', implode("\n", $errors));
    }
}
