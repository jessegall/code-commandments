<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Ast\Support\Frozen;
use JesseGall\CodeCommandments\Cli\Scope\FrozenScope;
use PHPUnit\Framework\TestCase;

/**
 * A file stamped `@code-commandments-generated` is REGENERATED, not hand-authored — judging it hands a
 * human work the next regeneration undoes, and rewriting it is work thrown away. `judge`'s help has
 * always promised those files are skipped; these tests hold it to that, through the same "never a
 * target" gate that carries `#[Frozen]` — so `repent` and `hints` honour it too.
 */
final class GeneratedFilesTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/cc-generated-' . uniqid('', true);
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function test_a_generated_file_reads_as_frozen(): void
    {
        $generated = <<<'PHP'
        <?php

        /**
         * DO NOT EDIT — regenerated whenever the resolver map changes.
         *
         * @code-commandments-generated
         */
        abstract class Predicate {}
        PHP;

        $this->assertTrue(Frozen::isFrozen($generated));
        $this->assertFalse(Frozen::isFrozen("<?php\n\nfinal class Money {}\n"));
    }

    public function test_a_generated_file_is_never_a_target(): void
    {
        file_put_contents($generated = $this->dir . '/Predicate.php', "<?php\n\n/** @code-commandments-generated */\nclass A {}\n");
        file_put_contents($handwritten = $this->dir . '/Money.php', "<?php\n\nclass B {}\n");

        $scope = new FrozenScope;

        $this->assertFalse($scope->includes($generated));
        $this->assertTrue($scope->includes($handwritten));
    }
}
