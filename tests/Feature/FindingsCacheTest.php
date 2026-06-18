<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use JesseGall\CodeCommandments\Scanners\GenericFileScanner;
use JesseGall\CodeCommandments\Support\Caching\FindingsCache;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;
use JesseGall\CodeCommandments\Tests\Fixtures\Prophets\CountingProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

/**
 * The findings cache must NEVER serve a stale result. These cover every way the
 * inputs can change — the file, ANOTHER file (cross-file), the file set, the
 * ruleset — plus --no-cache and round-trip correctness.
 */
class FindingsCacheTest extends TestCase
{
    private string $dir;
    private string $cacheFile;

    protected function setUp(): void
    {
        parent::setUp();
        CountingProphet::reset();

        $this->dir = sys_get_temp_dir() . '/cc-cache-' . uniqid();
        @mkdir($this->dir, 0755, true);
        $this->cacheFile = $this->dir . '/.cache/findings.json';
        Environment::setBasePath($this->dir);
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

    private function write(string $name, string $body): void
    {
        file_put_contents($this->dir . '/' . $name, "<?php\n// {$body}\n");
    }

    private function manager(array $config = []): ScrollManager
    {
        $registry = new ProphetRegistry();
        $registry->registerMany('test', [CountingProphet::class]);
        $registry->setScrollConfig('test', array_merge([
            'path' => $this->dir,
            'extensions' => ['php'],
            'exclude' => [],
            'prophets' => [CountingProphet::class],
        ], $config));

        $manager = new ScrollManager($registry, new GenericFileScanner());
        $manager->setFindingsCache(new FindingsCache($this->cacheFile, new Filesystem()));

        return $manager;
    }

    public function test_unchanged_scroll_is_a_cache_hit_and_does_not_rejudge(): void
    {
        $this->write('A.php', 'FLAG_ME');
        $this->write('B.php', 'clean');

        $first = $this->manager()->judgeScroll('test');
        $callsAfterFirst = CountingProphet::$calls;
        $this->assertSame(2, $callsAfterFirst, 'First pass judges both files.');

        // Fresh manager (new in-memory state) reading the on-disk cache.
        $second = $this->manager()->judgeScroll('test');

        $this->assertSame($callsAfterFirst, CountingProphet::$calls, 'Nothing changed — the second pass must NOT re-judge.');
        $this->assertSame($first->keys()->all(), $second->keys()->all(), 'Cached pass returns the same files as a fresh judge.');

        // The cached finding for A.php is reproduced field-for-field.
        $aKey = realpath($this->dir . '/A.php');
        $this->assertTrue($second->has($aKey));
        $this->assertCount(1, $second->get($aKey)->get(CountingProphet::class)->warnings);
        $this->assertSame('FLAG_ME found', $second->get($aKey)->get(CountingProphet::class)->warnings[0]->message);
    }

    public function test_changing_the_file_busts_its_cache(): void
    {
        $this->write('A.php', 'clean');
        $this->manager()->judgeScroll('test');
        $before = CountingProphet::$calls;

        $this->write('A.php', 'FLAG_ME');   // content changed
        $result = $this->manager()->judgeScroll('test');

        $this->assertGreaterThan($before, CountingProphet::$calls, 'A changed file must be re-judged.');
        $this->assertSame(1, $result->count(), 'A.php now flags.');
    }

    public function test_changing_a_DIFFERENT_file_busts_the_cross_file_cache(): void
    {
        // The headline safety case: cross-file prophets depend on the whole
        // scroll, so a change to ANY file must invalidate every cached entry.
        $this->write('A.php', 'FLAG_ME');
        $this->write('B.php', 'clean');
        $this->manager()->judgeScroll('test');
        $before = CountingProphet::$calls;

        $this->write('B.php', 'also touched');   // a DIFFERENT file changed
        $this->manager()->judgeScroll('test');

        $this->assertGreaterThan($before, CountingProphet::$calls, 'Touching B must invalidate the cached findings for A too.');
    }

    public function test_adding_a_file_busts_the_cache(): void
    {
        $this->write('A.php', 'FLAG_ME');
        $this->manager()->judgeScroll('test');
        $before = CountingProphet::$calls;

        $this->write('C.php', 'new file');
        $this->manager()->judgeScroll('test');

        $this->assertGreaterThan($before, CountingProphet::$calls, 'A new file changes the scroll fingerprint.');
    }

    public function test_deleting_a_file_busts_the_cache(): void
    {
        $this->write('A.php', 'FLAG_ME');
        $this->write('B.php', 'clean');
        $this->manager()->judgeScroll('test');
        $before = CountingProphet::$calls;

        @unlink($this->dir . '/B.php');
        $this->manager()->judgeScroll('test');

        $this->assertGreaterThan($before, CountingProphet::$calls, 'A deleted file changes the scroll fingerprint.');
    }

    public function test_changing_the_ruleset_config_busts_the_cache(): void
    {
        $this->write('A.php', 'FLAG_ME');
        $this->manager()->judgeScroll('test');
        $before = CountingProphet::$calls;

        // Same files, different config → different ruleset version.
        $this->manager(['some_setting' => 'changed'])->judgeScroll('test');

        $this->assertGreaterThan($before, CountingProphet::$calls, 'A config change must invalidate the cache.');
    }

    public function test_the_commandments_config_file_is_never_judged(): void
    {
        $this->write('commandments.php', 'FLAG_ME');   // the tool's own config
        $this->write('Real.php', 'FLAG_ME');

        $this->manager()->judgeScroll('test');

        $this->assertSame(1, CountingProphet::$calls, 'commandments.php is configuration, not code — never judged.');
    }

    public function test_no_cache_never_reads_the_cache(): void
    {
        $this->write('A.php', 'FLAG_ME');
        $this->manager()->judgeScroll('test');
        $before = CountingProphet::$calls;

        $manager = $this->manager();
        $manager->setUseCache(false);
        $manager->judgeScroll('test');

        $this->assertGreaterThan($before, CountingProphet::$calls, '--no-cache forces a fresh, authoritative judge.');
    }
}
