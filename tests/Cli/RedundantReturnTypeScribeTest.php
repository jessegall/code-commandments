<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Cli\Rewriting\RedundantReturnTypeScribe;
use JesseGall\CodeCommandments\Cli\Scope\Scope;
use PHPUnit\Framework\TestCase;

/**
 * Real-file tests for the arrow-fn return-type Scribe: write PHP into a temp dir,
 * scan + rewrite it, assert the new content (and that it still parses), clean up.
 */
final class RedundantReturnTypeScribeTest extends TestCase
{
    /** @var list<string> */
    private array $dirs = [];

    protected function tearDown(): void
    {
        foreach ($this->dirs as $dir) {
            foreach (glob($dir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
        $this->dirs = [];
    }

    public function test_strips_a_redundant_static_factory_return_type(): void
    {
        $new = $this->rewrite(<<<'PHP'
            <?php
            namespace Demo;
            final class Account {
                public static function forDefault(string $d): self { return new self; }
            }
            final class Maker {
                public function make(string $driver): \Closure
                {
                    return static fn (): Account => Account::forDefault($driver);
                }
            }
            PHP);

        $this->assertStringContainsString('static fn () => Account::forDefault($driver)', $new);
        $this->assertStringNotContainsString('fn (): Account', $new);
        $this->assertTrue($this->parses($new));
    }

    public function test_strips_a_redundant_new_of_the_same_class(): void
    {
        $new = $this->rewrite(<<<'PHP'
            <?php
            namespace Demo;
            final class Account { public function __construct() {} }
            final class Maker {
                public function make(): \Closure { return fn (): Account => new Account(); }
            }
            PHP);

        $this->assertStringContainsString('fn () => new Account()', $new);
        $this->assertTrue($this->parses($new));
    }

    public function test_keeps_a_scalar_return_type_it_could_coerce(): void
    {
        $src = <<<'PHP'
            <?php
            namespace Demo;
            final class Maker {
                public function make(): \Closure { return fn (): int => strlen('x'); }
            }
            PHP;

        // No object-typed arrow fn → nothing to rewrite at all.
        $this->assertSame([], $this->rewrites($src));
    }

    public function test_keeps_a_widening_return_type(): void
    {
        // The arrow declares the interface but builds the concrete — that's a
        // deliberate widening, not redundant.
        $src = <<<'PHP'
            <?php
            namespace Demo;
            interface Animal {}
            final class Dog implements Animal { public function __construct() {} }
            final class Maker {
                public function make(): \Closure { return fn (): Animal => new Dog(); }
            }
            PHP;

        $this->assertSame([], $this->rewrites($src));
    }

    public function test_keeps_an_unresolved_instance_call(): void
    {
        $src = <<<'PHP'
            <?php
            namespace Demo;
            final class Account {}
            final class Maker {
                public function make($svc): \Closure { return fn (): Account => $svc->build(); }
            }
            PHP;

        $this->assertSame([], $this->rewrites($src));
    }

    /**
     * @return array<string, string>
     */
    private function rewrites(string $source): array
    {
        $dir = sys_get_temp_dir() . '/cc-scribe-' . uniqid('', true);
        mkdir($dir, 0777, true);
        $this->dirs[] = $dir;
        file_put_contents($dir . '/Demo.php', $source . "\n");

        return new RedundantReturnTypeScribe()->rewrites(Codebase::scan($dir), Scope::everything());
    }

    private function rewrite(string $source): string
    {
        $changes = $this->rewrites($source);
        $this->assertCount(1, $changes, 'expected exactly one file rewritten');

        return (string) reset($changes);
    }

    private function parses(string $php): bool
    {
        $tmp = (string) tempnam(sys_get_temp_dir(), 'cc-lint-');
        file_put_contents($tmp, $php);
        exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $status);
        @unlink($tmp);

        return $status === 0;
    }
}
