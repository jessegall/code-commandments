<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast\Support;

use JesseGall\CodeCommandments\Ast\Support\Frozen;
use PHPUnit\Framework\TestCase;

/**
 * A freeze marker counts where it is DECLARED, never where it is merely spelled (#405) — otherwise a
 * file that documents the feature freezes itself, and every finding in it disappears without a word.
 */
final class FrozenTest extends TestCase
{
    public function test_a_stamp_in_a_comment_freezes_the_file(): void
    {
        $this->assertTrue(Frozen::isFrozen("<?php\n\n// " . Frozen::FILE_MARKER . " — deliberately immutable\n\nclass A {}\n"));
    }

    public function test_a_generated_stamp_in_a_docblock_freezes_the_file(): void
    {
        $this->assertTrue(Frozen::isFrozen("<?php\n\n/**\n * " . Frozen::GENERATED_MARKER . "\n */\nclass A {}\n"));
    }

    public function test_an_at_frozen_tag_freezes_the_file(): void
    {
        $this->assertTrue(Frozen::isFrozen("<?php\n\n/** @frozen */\nclass A {}\n"));
    }

    public function test_the_attribute_freezes_the_file(): void
    {
        $this->assertTrue(Frozen::isFrozen("<?php\n\nuse JesseGall\\CodeCommandments\\Testing\\Frozen;\n\n#[Frozen]\nclass A {}\n"));
    }

    public function test_a_help_text_that_MENTIONS_a_marker_does_not_freeze_the_file(): void
    {
        // The bug, exactly: our own CLI kernel documents the feature in its usage heredoc, and froze
        // itself — hiding real findings from judge and skipping the file in repent, silently.
        $source = <<<'PHP_SOURCE'
        <?php

        final class Kernel
        {
            private const string USAGE = <<<TXT
                commandments judge [path]
                  Files marked @code-commandments-generated are skipped automatically
                TXT;
        }
        PHP_SOURCE;

        $this->assertFalse(Frozen::isFrozen($source));
    }

    public function test_a_string_literal_naming_the_marker_does_not_freeze_the_file(): void
    {
        $this->assertFalse(Frozen::isFrozen("<?php\n\nclass A { public const string M = '" . Frozen::FILE_MARKER . "'; }\n"));
    }

    public function test_a_test_asserting_on_the_stamp_does_not_freeze_itself(): void
    {
        $source = "<?php\n\nclass FreezeTest\n{\n    public function test_it(): void\n    {\n        \$this->assertStringContainsString('@frozen', \$file);\n    }\n}\n";

        $this->assertFalse(Frozen::isFrozen($source));
    }

    public function test_an_ordinary_file_is_not_frozen(): void
    {
        $this->assertFalse(Frozen::isFrozen("<?php\n\nclass A { public function b(): void {} }\n"));
    }
}
