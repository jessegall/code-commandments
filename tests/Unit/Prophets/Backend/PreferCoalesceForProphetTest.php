<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferCoalesceForProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferCoalesceForProphetTest extends TestCase
{
    private PreferCoalesceForProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferCoalesceForProphet;
    }

    private function judge(string $body): \JesseGall\CodeCommandments\Results\Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n" . $body);
    }

    public function test_flags_double_coalesce_with_a_variable_key(): void
    {
        $judgment = $this->judge('$x = T_Array::coalesce($forward[$current] ?? null);');

        $this->assertCount(1, $judgment->sins);
        $this->assertStringContainsString('coalesceFor($forward, $current)', $judgment->sins[0]->message);
    }

    public function test_flags_double_coalesce_with_a_property_key(): void
    {
        $judgment = $this->judge('$x = T_Array::coalesce($outgoing[$node->id] ?? null);');

        $this->assertCount(1, $judgment->sins);
    }

    public function test_flags_bare_coalesce_of_a_dynamic_lookup(): void
    {
        // T_Array::coalesce($arr[$key]) is already $arr[$key] ?? [] the long way.
        $judgment = $this->judge('$x = T_Array::coalesce($forward[$current]);');

        $this->assertCount(1, $judgment->sins);
    }

    public function test_does_not_flag_a_literal_key(): void
    {
        // A literal key is a record access — NoArrayStringIndexing's territory.
        $judgment = $this->judge('$x = T_Array::coalesce($config["label"] ?? null);');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_coalesce_of_a_non_lookup(): void
    {
        $judgment = $this->judge('$x = T_Array::coalesce($maybeArray ?? null);');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_is_marked_auto_fixable(): void
    {
        $judgment = $this->judge('$x = T_Array::coalesce($forward[$current] ?? null);');

        $this->assertTrue($judgment->sins[0]->autoFixable);
    }

    public function test_repent_rewrites_to_coalesce_for(): void
    {
        $result = $this->prophet->repent('/x.php', "<?php\n\$x = T_Array::coalesce(\$forward[\$current] ?? null);\n");

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('T_Array::coalesceFor($forward, $current)', (string) $result->newContent);
        $this->assertStringNotContainsString('?? null', (string) $result->newContent);
    }

    public function test_repent_carries_a_non_trivial_default(): void
    {
        $result = $this->prophet->repent('/x.php', "<?php\n\$x = T_Array::coalesce(\$a[\$k] ?? \$fallback);\n");

        $this->assertStringContainsString('T_Array::coalesceFor($a, $k, $fallback)', (string) $result->newContent);
    }

    public function test_repent_leaves_a_literal_key_alone(): void
    {
        $result = $this->prophet->repent('/x.php', "<?php\n\$x = T_Array::coalesce(\$config['label'] ?? null);\n");

        $this->assertFalse($result->absolved);
    }
}
