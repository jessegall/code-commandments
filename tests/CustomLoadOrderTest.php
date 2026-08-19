<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests;

use JesseGall\CodeCommandments\Custom;
use PHPUnit\Framework\TestCase;

/**
 * The project's own folder is not PSR-4 mapped, so its files are REQUIRED rather than autoloaded —
 * and a require happens in file order. A rule whose trait or base class sits in a file sorted after
 * it therefore fataled on load, taking the whole run with it, for no reason a reader could see: the
 * folder is a flat bag of the project's classes and nothing about it says which loads first.
 */
final class CustomLoadOrderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-order-' . uniqid('', true);
        mkdir($this->root . '/.commandments/custom/Naming', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_a_rule_loads_though_its_trait_sorts_after_it(): void
    {
        // `ProseRuleDetector.php` sorts before `ReadsAsProse.php`, so the trait is not there yet
        // when the class that uses it is required.
        $this->write('Naming/ProseRuleDetector.php', <<<'PHP'
        namespace Project\Naming;

        final class ProseRuleDetector
        {
            use ReadsAsProse;
        }
        PHP);

        $this->write('Naming/ReadsAsProse.php', <<<'PHP'
        namespace Project\Naming;

        trait ReadsAsProse
        {
            public function reads(): string
            {
                return 'as prose';
            }
        }
        PHP);

        Custom::skills($this->root);

        $this->assertTrue(class_exists('Project\\Naming\\ProseRuleDetector'));
        $this->assertSame('as prose', new \Project\Naming\ProseRuleDetector()->reads());
    }

    public function test_a_rule_loads_though_its_base_class_sorts_after_it(): void
    {
        // The same for inheritance: `AbstractProjectRule` sorts before `ZoneRule` by name, so this
        // one is about the OTHER order — a base required after nothing needed it yet.
        $this->write('ZoneRule.php', <<<'PHP'
        namespace Project;

        final class ZoneRule extends AbstractProjectRule
        {
            public function zone(): string
            {
                return 'chilled';
            }
        }
        PHP);

        $this->write('AbstractProjectRule.php', <<<'PHP'
        namespace Project;

        abstract class AbstractProjectRule
        {
            public function owner(): string
            {
                return 'the project';
            }
        }
        PHP);

        Custom::skills($this->root);

        $this->assertSame('the project', new \Project\ZoneRule()->owner());
    }

    private function write(string $path, string $php): void
    {
        $file = $this->root . '/.commandments/custom/' . $path;

        @mkdir(dirname($file), 0777, true);
        file_put_contents($file, "<?php\n\n" . $php . "\n");
    }
}
