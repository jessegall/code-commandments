<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Make\Blueprint;
use JesseGall\CodeCommandments\Cli\Make\Engine;
use JesseGall\CodeCommandments\Cli\Make\Make;
use JesseGall\CodeCommandments\Cli\Config\ConfigFile;
use PHPUnit\Framework\TestCase;

/**
 * `commandments make` scaffolds a project's OWN commandment. Everything it writes must be real,
 * runnable PHP the moment it lands (an author fills TODOs, never fixes syntax), it must register
 * the detector in the config through the AST, and it must never quietly overwrite work.
 */
final class MakeTest extends TestCase
{
    /** @var list<string> */
    private array $dirs = [];

    protected function tearDown(): void
    {
        foreach ($this->dirs as $dir) {
            exec('rm -rf ' . escapeshellarg($dir));
        }

        $this->dirs = [];
    }

    public function test_it_writes_a_skill_a_sin_and_a_detector_and_registers_it(): void
    {
        $dir = $this->project();

        $this->assertSame(0, $this->make($dir, 'NullableElementReturn', '--skill=element-purity'));

        $custom = "{$dir}/.commandments/custom";

        $this->assertFileExists("{$custom}/ElementPurity.php");
        $this->assertFileExists("{$custom}/NullableElementReturn.php");
        $this->assertFileExists("{$custom}/NullableElementReturnDetector.php");

        $this->assertSame(
            ['Commandments\\NullableElementReturnDetector'],
            ConfigFile::inProject($dir)->detectors(),
        );
    }

    public function test_everything_it_writes_is_valid_php(): void
    {
        $dir = $this->project();
        $this->make($dir, 'NoRawSql', '--skill=data-access');

        foreach (glob("{$dir}/.commandments/custom/*.php") ?: [] as $file) {
            exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $status);
            $this->assertSame(0, $status, "scaffolded file is not valid PHP:\n" . implode("\n", $out));
        }
    }

    public function test_pointing_at_an_existing_skill_writes_no_skill_of_its_own(): void
    {
        $dir = $this->project();
        $this->make($dir, 'NullableElementReturn', '--skill=absence');

        $written = array_map('basename', glob("{$dir}/.commandments/custom/*.php") ?: []);

        $this->assertSame(['NullableElementReturn.php', 'NullableElementReturnDetector.php'], $written);
        $this->assertStringContainsString(
            'JesseGall\\CodeCommandments\\Skills\\Backend\\Absence::class',
            (string) file_get_contents("{$dir}/.commandments/custom/NullableElementReturn.php"),
            'the sin points at the SHIPPED skill it matched',
        );
    }

    public function test_a_skill_named_after_its_only_sin_does_not_collide_with_it(): void
    {
        $dir = $this->project();
        $this->make($dir, 'NullableElementReturn');

        // No --skill at all: the slug is derived from the sin, so the skill class would want the
        // sin's very name. It must not take the sin's file.
        $this->assertFileExists("{$dir}/.commandments/custom/NullableElementReturnSkill.php");
        $this->assertStringContainsString(
            'extends Sin',
            (string) file_get_contents("{$dir}/.commandments/custom/NullableElementReturn.php"),
            'the sin file is still the sin',
        );
    }

    public function test_it_refuses_to_overwrite_without_force(): void
    {
        $dir = $this->project();
        $this->make($dir, 'NoRawSql', '--skill=data-access');
        file_put_contents("{$dir}/.commandments/custom/NoRawSqlDetector.php", '<?php // mine');

        $this->assertSame(2, $this->make($dir, 'NoRawSql', '--skill=data-access'));
        $this->assertSame('<?php // mine', file_get_contents("{$dir}/.commandments/custom/NoRawSqlDetector.php"));

        $this->assertSame(0, $this->make($dir, 'NoRawSql', '--skill=data-access', '--force'));
        $this->assertStringNotContainsString('// mine', (string) file_get_contents("{$dir}/.commandments/custom/NoRawSqlDetector.php"));
    }

    public function test_a_frontend_detector_reads_the_vue_codebase(): void
    {
        $dir = $this->project();
        $this->make($dir, 'DeepTemplate', '--engine=frontend', '--skill=template-depth');

        $detector = (string) file_get_contents("{$dir}/.commandments/custom/DeepTemplateDetector.php");

        $this->assertStringContainsString('use JesseGall\\CodeCommandments\\Vue\\Codebase;', $detector);
        $this->assertStringContainsString('Frontend\\Detector', $detector);
        $this->assertStringContainsString('whereElement()', $detector);
    }

    public function test_an_unknown_engine_is_refused(): void
    {
        $this->assertSame(2, $this->make($this->project(), 'Whatever', '--engine=sideways'));
    }

    public function test_it_needs_a_name(): void
    {
        $this->assertSame(2, $this->make($this->project(), ''));
    }

    public function test_the_blueprint_derives_every_name_from_one_word(): void
    {
        $blueprint = Blueprint::of('nullable-element-return', Engine::Backend, 'backend/absence', null, '/tmp');

        $this->assertSame('NullableElementReturn', $blueprint->sin);
        $this->assertSame('nullable-element-return', $blueprint->id);
        $this->assertSame('NullableElementReturnDetector', $blueprint->detector());
        $this->assertSame('commandments-backend-absence', $blueprint->skillId());
    }

    public function test_the_detector_suffix_names_the_same_commandment(): void
    {
        $suffixed = Blueprint::of('NullableElementReturnDetector', Engine::Backend, 'x/y', 'A\\B', '/tmp');

        $this->assertSame('NullableElementReturn', $suffixed->sin);
    }

    /**
     * Run `make` in $dir with the given arguments, silently.
     */
    private function make(string $dir, string $name, string ...$options): int
    {
        $cwd = getcwd();
        chdir($dir);

        ob_start();

        try {
            $arguments = $name === '' ? $options : [$name, ...$options];

            return new Make()->run(Input::of('make', $arguments));
        } finally {
            ob_end_clean();
            chdir((string) $cwd);
        }
    }

    private function project(): string
    {
        $dir = sys_get_temp_dir() . '/cc-make-' . uniqid('', true);
        mkdir("{$dir}/src", 0777, true);
        file_put_contents("{$dir}/composer.json", '{"name":"demo/app","autoload":{"psr-4":{"Demo\\\\":"src/"}}}');
        $this->dirs[] = $dir;

        return $dir;
    }
}
