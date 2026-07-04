<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast\Support;

use JesseGall\CodeCommandments\Ast\Support\Frozen;
use PHPUnit\Framework\TestCase;

final class FrozenTest extends TestCase
{
    public function test_a_frozen_attribute_freezes_the_file(): void
    {
        $this->assertTrue(Frozen::isFrozen("<?php\n#[Frozen]\nclass Migration {}\n"));
    }

    public function test_an_at_frozen_docblock_tag_freezes_the_file(): void
    {
        $this->assertTrue(Frozen::isFrozen("<?php\n/**\n * @frozen\n */\nclass Migration {}\n"));
    }

    public function test_the_command_stamp_freezes_the_file(): void
    {
        $this->assertTrue(Frozen::isFrozen("<?php\n// @code-commandments-frozen\nclass Legacy {}\n"));
    }

    public function test_prose_mentioning_frozen_does_not_freeze(): void
    {
        $this->assertFalse(Frozen::isFrozen("<?php\nclass Lake { /* the frozen surface */ public function thaw(): void {} }\n"));
    }
}
