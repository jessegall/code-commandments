<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use JesseGall\CodeCommandments\Prophets\Backend\BehaviouralEnumDispatchProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoRawLiteralProphet;
use JesseGall\CodeCommandments\Scanners\GenericFileScanner;
use JesseGall\CodeCommandments\Support\Caching\FindingsCache;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;
use JesseGall\CodeCommandments\Tests\TestCase;

/**
 * `--prophet` narrows which prophets actually RUN, not just what is reported — so
 * an unrelated prophet is never invoked (and can't surface a bug), and the
 * focused run never poisons the shared findings cache.
 */
class ProphetFilterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-pf-' . uniqid();
        @mkdir($this->dir, 0755, true);
        $this->dir = realpath($this->dir);
        Environment::setBasePath($this->dir);
        // A file with a NoRawLiteral sin but no behavioural enum dispatch.
        file_put_contents($this->dir . '/X.php', "<?php\nnamespace App;\nclass X { public function m(): string { return ''; } }\n");
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/{,.cache/}*', GLOB_BRACE) ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir . '/.cache');
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function manager(): ScrollManager
    {
        $registry = new ProphetRegistry();
        $prophets = [NoRawLiteralProphet::class, BehaviouralEnumDispatchProphet::class];
        $registry->registerMany('t', $prophets);
        $registry->setScrollConfig('t', ['path' => $this->dir, 'extensions' => ['php'], 'exclude' => [], 'prophets' => $prophets]);

        return new ScrollManager($registry, new GenericFileScanner());
    }

    private function prophetsThatRan(\Illuminate\Support\Collection $results): array
    {
        $ran = [];

        foreach ($results as $fileResults) {
            foreach ($fileResults as $class => $judgment) {
                $ran[class_basename($class)] = true;
            }
        }

        return array_keys($ran);
    }

    public function test_filter_runs_only_the_matched_prophet(): void
    {
        $manager = $this->manager();
        $manager->setProphetFilter('BehaviouralEnumDispatch');

        $ran = $this->prophetsThatRan($manager->judgeScroll('t'));

        $this->assertNotContains('NoRawLiteralProphet', $ran, 'An unrelated prophet must not be invoked.');
    }

    public function test_no_filter_runs_all_prophets(): void
    {
        $ran = $this->prophetsThatRan($this->manager()->judgeScroll('t'));

        $this->assertContains('NoRawLiteralProphet', $ran, 'Without a filter every prophet runs (the file has a raw-literal sin).');
    }

    public function test_a_filtered_run_does_not_touch_the_cache(): void
    {
        $cacheFile = $this->dir . '/.cache/findings.json';
        $manager = $this->manager();
        $manager->setFindingsCache(new FindingsCache($cacheFile, new Filesystem()));
        $manager->setProphetFilter('BehaviouralEnumDispatch');

        $manager->judgeScroll('t');

        $this->assertFileDoesNotExist($cacheFile, 'A focused run must not write the shared cache (partial findings would poison a later full judge).');
    }
}
