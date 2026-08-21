<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Judge;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Cli\Judge\Views;
use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\Detector;
use JesseGall\CodeCommandments\Detectors\CrossFileSet;
use JesseGall\CodeCommandments\Vue\Codebase as VueCodebase;
use PHPUnit\Framework\TestCase;

/**
 * A scoped run (`--changes`, `--branch`) parses the tree but reports on a few files, so a rule that
 * reads no further than the file it judges is shown those files alone — the cost of judging a diff
 * has to track the diff (#519).
 */
final class ScopedViewTest extends TestCase
{
    private string $root = '';

    private static ?CrossFileSet $beyond = null;

    private static string $rules = '';

    /**
     * Two rules written as SOURCE, because that is what the classification reads: one that asks the
     * codebase about other files and one that reads only what it was handed. Declared ONCE — a class
     * cannot be declared twice in a process.
     */
    public static function setUpBeforeClass(): void
    {
        self::$rules = sys_get_temp_dir() . '/cc-views-rules-' . uniqid('', true) . '.php';

        file_put_contents(self::$rules, <<<'PHP'
            <?php

            namespace Consumer\Rules;

            use JesseGall\CodeCommandments\Ast\Codebase;
            use JesseGall\CodeCommandments\Detector;
            use JesseGall\CodeCommandments\Sins\Sin;

            final class LocalRule implements Detector
            {
                public function sin(): Sin
                {
                    throw new \LogicException('never asked');
                }

                public function find(Codebase $codebase): array
                {
                    return $codebase->whereClass()->get();
                }
            }

            final class WorldRule implements Detector
            {
                public function sin(): Sin
                {
                    throw new \LogicException('never asked');
                }

                public function find(Codebase $codebase): array
                {
                    return $codebase->declarationMatch('Consumer\\Thing') === null ? [] : ['x'];
                }
            }
            PHP);

        require_once self::$rules;

        self::$beyond = CrossFileSet::over(Codebase::scan(self::$rules));
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(self::$rules);
    }

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-views-' . uniqid('', true);

        @mkdir($this->root, 0777, true);
        file_put_contents($this->root . '/Touched.php', '<?php class Touched { public function one(): void {} }');
        file_put_contents($this->root . '/Untouched.php', '<?php class Untouched { public function two(): void {} }');

        foreach (['Touched', 'Untouched'] as $name) {
            file_put_contents("{$this->root}/{$name}.vue", "<template><div>{$name}</div></template>");
        }
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->root . '/*.*') ?: []);
        @rmdir($this->root);
    }

    public function test_a_view_holds_the_files_it_was_narrowed_to_and_nothing_else(): void
    {
        $view = $this->codebase()->focusedOn($this->root . '/Touched.php');

        $this->assertSame(['Touched'], $this->classesIn($view));
    }

    public function test_a_view_reparses_nothing(): void
    {
        $codebase = $this->codebase();
        $view = $codebase->focusedOn($this->root . '/Touched.php');

        $this->assertSame(
            $codebase->declarationMatch('Touched')?->node,
            $view->declarationMatch('Touched')?->node,
            'a view is the same scan, subsetted — it must not re-parse a file the scan already holds',
        );
    }

    public function test_a_path_the_scan_never_held_is_simply_not_there(): void
    {
        $this->assertSame([], $this->classesIn($this->codebase()->focusedOn($this->root . '/Absent.php')));
    }

    public function test_a_single_file_rule_is_shown_the_scoped_files_and_a_whole_tree_rule_the_tree(): void
    {
        $views = Views::of($this->codebase(), $this->scopedToTouched(), self::$beyond);

        $this->assertSame(['Touched'], $this->classesIn($views->for($this->rule('LocalRule'))));
        $this->assertSame(['Touched', 'Untouched'], $this->classesIn($views->for($this->rule('WorldRule'))));
    }

    public function test_an_unscoped_run_shows_every_rule_the_whole_tree(): void
    {
        $views = Views::of($this->codebase(), Scope::everything(), self::$beyond);

        $this->assertSame(['Touched', 'Untouched'], $this->classesIn($views->for($this->rule('LocalRule'))));
    }

    public function test_the_tree_is_offered_only_when_a_rule_will_read_it(): void
    {
        $views = Views::of($this->codebase(), $this->scopedToTouched(), self::$beyond);

        // What the caller asks before building the call and value-flow graphs: whole-program work
        // nothing in this run will read is work a scoped run must not pay for.
        $this->assertNull($views->wholeTreeFor([$this->rule('LocalRule')]));
        $this->assertNotNull($views->wholeTreeFor([$this->rule('LocalRule'), $this->rule('WorldRule')]));
    }

    public function test_the_frontend_narrows_through_the_same_base_type(): void
    {
        $components = VueCodebase::scan($this->root);

        $this->assertSame(1, $components->focusedOn($this->root . '/Touched.vue')->whereTag('div')->count());
        $this->assertSame(2, $components->whereTag('div')->count());
    }

    /**
     * @return list<string>
     */
    private function classesIn(Codebase $codebase): array
    {
        $names = array_keys($codebase->declarations());

        sort($names);

        return $names;
    }

    private function codebase(): Codebase
    {
        return Codebase::scan($this->root);
    }

    private function scopedToTouched(): Scope
    {
        return Scope::restrictedTo([$this->root . '/Touched.php']);
    }

    private function rule(string $name): Detector
    {
        $class = "Consumer\\Rules\\{$name}";

        return new $class;
    }
}
