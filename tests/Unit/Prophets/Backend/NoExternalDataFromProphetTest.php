<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoExternalDataFromProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoExternalDataFromProphetTest extends TestCase
{
    private NoExternalDataFromProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoExternalDataFromProphet;
    }

    public function test_flags_custom_from_factory_definition(): void
    {
        $j = $this->judge('class NodeSummaryData extends \Spatie\LaravelData\Data { public static function fromDescriptor($d): self { return self::from([]); } }');

        $this->assertCount(1, $j->sins);
        $this->assertStringContainsString('reserved `from` prefix', $j->sins[0]->message);
        $this->assertFalse($j->sins[0]->autoFixable);
    }

    public function test_flags_external_magic_from_array_as_autofixable(): void
    {
        $j = $this->judge('class WorkflowSummaryData extends \Spatie\LaravelData\Data {} class Cmd { public function h() { return WorkflowSummaryData::from(["id" => 1]); } }');

        $this->assertCount(1, $j->sins);
        $this->assertStringContainsString('forArray', $j->sins[0]->message);
        $this->assertTrue($j->sins[0]->autoFixable);
    }

    public function test_flags_external_magic_from_object_not_autofixable(): void
    {
        $j = $this->judge('class NodeData extends \Spatie\LaravelData\Data {} class Cmd { public function h($descriptor) { return NodeData::from($descriptor); } }');

        $this->assertCount(1, $j->sins);
        $this->assertStringContainsString('object dispatch', $j->sins[0]->message);
        $this->assertFalse($j->sins[0]->autoFixable);
    }

    public function test_flags_external_custom_from_call(): void
    {
        $j = $this->judge('class NodeData extends \Spatie\LaravelData\Data {} class Cmd { public function h($m) { return NodeData::fromModel($m); } }');

        $this->assertCount(1, $j->sins);
        $this->assertStringContainsString('renamed to a `for*`', $j->sins[0]->message);
    }

    public function test_allows_self_from_inside_the_class(): void
    {
        $j = $this->judge('class NodeData extends \Spatie\LaravelData\Data { public static function forDescriptor($d): self { return self::from(["k" => $d->k]); } }');

        $this->assertCount(0, $j->sins);
    }

    public function test_allows_static_and_parent_from(): void
    {
        $j = $this->judge('class NodeData extends \Spatie\LaravelData\Data { public static function a(): self { return static::from([]); } public static function b(): self { return parent::from([]); } }');

        $this->assertCount(0, $j->sins);
    }

    public function test_allows_calling_own_class_by_name(): void
    {
        $j = $this->judge('class NodeData extends \Spatie\LaravelData\Data { public static function blank(): self { return NodeData::from([]); } }');

        $this->assertCount(0, $j->sins);
    }

    public function test_ignores_from_on_non_data_class(): void
    {
        $j = $this->judge('class Cmd { public function h($x) { return \Carbon\Carbon::from($x); return Status::from($x); } }');

        $this->assertCount(0, $j->sins);
    }

    public function test_does_not_flag_non_from_factories(): void
    {
        $j = $this->judge('class NodeData extends \Spatie\LaravelData\Data { public static function forModel($m): self { return self::from([]); } public static function make(): self { return self::from([]); } }');

        $this->assertCount(0, $j->sins);
    }

    public function test_severity_warning_emits_warnings(): void
    {
        $this->prophet->configure(['severity' => 'warning']);

        $j = $this->judge('class NodeData extends \Spatie\LaravelData\Data {} class Cmd { public function h() { return NodeData::from(["id" => 1]); } }');

        $this->assertCount(0, $j->sins);
        $this->assertCount(1, $j->warnings);
    }

    public function test_repent_rewrites_external_from_array_to_for_array(): void
    {
        $src = '<?php class NodeData extends \Spatie\LaravelData\Data {} class Cmd { public function h() { return NodeData::from(["id" => 1]); } }';

        $result = $this->prophet->repent('/x.php', $src);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('NodeData::forArray(["id" => 1])', $result->newContent);
        $this->assertStringNotContainsString('NodeData::from(', $result->newContent);
    }

    public function test_repent_leaves_object_from_untouched(): void
    {
        $src = '<?php class NodeData extends \Spatie\LaravelData\Data {} class Cmd { public function h($o) { return NodeData::from($o); } }';

        $this->assertFalse($this->prophet->repent('/x.php', $src)->absolved);
    }

    public function test_repent_leaves_self_from_untouched(): void
    {
        $src = '<?php class NodeData extends \Spatie\LaravelData\Data { public static function blank(): self { return self::from([]); } }';

        $this->assertFalse($this->prophet->repent('/x.php', $src)->absolved);
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n" . $body);
    }
}
