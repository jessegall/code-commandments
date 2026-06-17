<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferEnumForClosedSetFieldProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferEnumForClosedSetFieldProphetTest extends TestCase
{
    private PreferEnumForClosedSetFieldProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferEnumForClosedSetFieldProphet;
    }

    public function test_flags_promoted_string_property_named_like_a_closed_set(): void
    {
        // The reported case: Spatie Data with `public string $direction`.
        $judgment = $this->judge('class NodeSocketData extends Data { public function __construct(public string $direction) {} }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('closed set', $judgment->warnings[0]->message);
        $this->assertStringContainsString('Direction', $judgment->warnings[0]->message);
    }

    public function test_flags_a_camelcase_suffix_at_a_word_boundary(): void
    {
        $judgment = $this->judge('class A { public function __construct(public string $sortDirection) {} }');

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_flags_a_snake_case_suffix(): void
    {
        $judgment = $this->judge('class A { public string $node_type; }');

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_flags_a_nullable_string(): void
    {
        $judgment = $this->judge('class A { public function __construct(public ?string $mode = null) {} }');

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_flags_a_plain_class_property(): void
    {
        $judgment = $this->judge('class A { public string $status; }');

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_does_not_match_a_noun_mid_word(): void
    {
        // `prototype` ends in `type` but not at a word boundary.
        $judgment = $this->judge('class A { public string $prototype; }');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_name_outside_the_list(): void
    {
        $judgment = $this->judge('class A { public string $title; }');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_non_string_type(): void
    {
        $judgment = $this->judge('class A { public int $level; }');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_plain_method_parameter(): void
    {
        // Only data FIELDS (properties + promoted ctor props). A transient method
        // parameter often carries a class-string / type name, not an enum value.
        $judgment = $this->judge('class A { public function findNodes(string $nodeType): array { return []; } }');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_configurable_names_can_disable_everything(): void
    {
        $prophet = (new PreferEnumForClosedSetFieldProphet)->configure(['names' => []]);

        $this->assertTrue($prophet->judge('/x.php', "<?php\nclass A { public string \$direction; }")->isRighteous());
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertNotNull($this->prophet->advisory());
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n" . $body);
    }
}
