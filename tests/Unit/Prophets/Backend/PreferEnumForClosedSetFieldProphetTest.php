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

    public function test_does_not_flag_an_attributed_promoted_param(): void
    {
        // issue #27: #[Input] etc. params are container-hydrated with a raw
        // string — retyping to a BackedEnum throws a TypeError, so exempt them.
        $judgment = $this->judge(
            'class ResourceFilter { public function __construct('
            . '#[Input(options: ResourceFilterOperator::class)] '
            . 'public readonly string $operator = "=") {} }'
        );

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_an_attributed_property(): void
    {
        $judgment = $this->judge('class A { #[Input] public string $status; }');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_still_flags_an_attribute_free_promoted_prop(): void
    {
        // The Spatie Data case stays valid — no hydration attribute.
        $judgment = $this->judge('class NodeSocketData extends Data { public function __construct(public string $direction) {} }');

        $this->assertCount(1, $judgment->warnings);
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

    public function test_declares_its_repent_inputs(): void
    {
        $names = array_map(static fn ($spec) => $spec->name, $this->prophet->repentInputs());

        // Either-or paths (create vs reuse), so all are optional and validated
        // at repent time.
        $this->assertContains('create-enum-class', $names);
        $this->assertContains('cases', $names);
        $this->assertContains('enum-class', $names);
        $this->assertContains('field', $names);

        foreach ($this->prophet->repentInputs() as $spec) {
            $this->assertFalse($spec->required, "{$spec->name} should be optional");
        }
    }

    public function test_repent_creates_enum_and_retypes_the_field(): void
    {
        $code = "<?php\nnamespace App\\Data;\nclass NodeSocketData extends Data { public function __construct(public string \$direction) {} }";
        $this->prophet->setRepentInput(['create-enum-class' => 'SocketDirection', 'cases' => 'input,output']);

        $result = $this->prophet->repent('/app/Data/NodeSocketData.php', $code);

        $this->assertTrue($result->absolved);
        // Field retyped.
        $this->assertStringContainsString('public SocketDirection $direction', $result->newContent);
        // Enum file created in the same namespace, with studly-cased cases.
        $this->assertArrayHasKey('/app/Data/SocketDirection.php', $result->createdFiles);
        $enum = $result->createdFiles['/app/Data/SocketDirection.php'];
        $this->assertStringContainsString('namespace App\\Data;', $enum);
        $this->assertStringContainsString('enum SocketDirection: string', $enum);
        $this->assertStringContainsString("case Input = 'input';", $enum);
        $this->assertStringContainsString("case Output = 'output';", $enum);
    }

    public function test_repent_preserves_nullability(): void
    {
        $code = "<?php\nclass A extends Data { public function __construct(public ?string \$mode = null) {} }";
        $this->prophet->setRepentInput(['create-enum-class' => 'Mode', 'cases' => 'fast,slow']);

        $result = $this->prophet->repent('/x.php', $code);

        $this->assertStringContainsString('public ?Mode $mode', $result->newContent);
    }

    public function test_repent_without_inputs_is_unrepentant(): void
    {
        $code = "<?php\nclass A extends Data { public string \$direction; }";

        $result = $this->prophet->repent('/x.php', $code);

        $this->assertFalse($result->absolved);
        $this->assertStringContainsString('create-enum-class', (string) $result->failureReason);
    }

    public function test_repent_converts_non_data_in_file_with_cross_file_checklist(): void
    {
        // A plain class is now converted IN-FILE (property + same-file readers),
        // with a precise cross-file checklist in the penance for the rest.
        $code = "<?php\nclass WorkflowTimelineStep {\n public string \$status = 'running';\n public function done(): bool { return \$this->status === 'completed'; }\n}";
        $this->prophet->setRepentInput(['create-enum-class' => 'RunStatus', 'cases' => 'running,completed']);

        $result = $this->prophet->repent('/x.php', $code);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('public RunStatus $status = RunStatus::Running', $result->newContent);
        $this->assertStringContainsString('$this->status === RunStatus::Completed', $result->newContent);
        // A cross-file checklist is handed back.
        $this->assertNotEmpty(array_filter($result->penance, static fn (string $p): bool => str_contains($p, 'CROSS-FILE')));
    }

    public function test_repent_reuses_an_existing_enum(): void
    {
        // issue #28(2): --input enum-class retypes to an existing enum, no creation.
        $code = "<?php\nnamespace App;\n\nclass A extends Data { public function __construct(public string \$status) {} }";
        $this->prophet->setRepentInput(['enum-class' => 'App\\Enums\\WorkflowRunStatus']);

        $result = $this->prophet->repent('/x.php', $code);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('public WorkflowRunStatus $status', $result->newContent);
        // No enum file created (reuse), and the FQCN imported.
        $this->assertSame([], $result->createdFiles);
        $this->assertStringContainsString('use App\\Enums\\WorkflowRunStatus;', $result->newContent);
    }

    public function test_repent_converts_an_enum_value_default_on_reuse(): void
    {
        // issue #29: a `Enum::Case->value` default must lose `->value`, else it
        // is a string default on an enum-typed property → PHP fatal.
        $code = "<?php\nnamespace App;\n\nclass Field extends Data { public function __construct(public string \$type = SchemaFieldType::String->value) {} }";
        $this->prophet->setRepentInput(['enum-class' => 'SchemaFieldType']);

        $result = $this->prophet->repent('/x.php', $code);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('public SchemaFieldType $type = SchemaFieldType::String', $result->newContent);
        $this->assertStringNotContainsString('->value', $result->newContent);
    }

    public function test_repent_converts_a_bare_string_default_on_create(): void
    {
        // A default that matches a created case becomes that case.
        $code = "<?php\nclass A extends Data { public function __construct(public string \$status = 'active') {} }";
        $this->prophet->setRepentInput(['create-enum-class' => 'Status', 'cases' => 'active,archived']);

        $result = $this->prophet->repent('/x.php', $code);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('public Status $status = Status::Active', $result->newContent);
    }

    public function test_repent_result_parses_after_default_conversion(): void
    {
        $code = "<?php\nnamespace App;\n\nclass Field extends Data { public function __construct(public string \$type = SchemaFieldType::String->value) {} }";
        $this->prophet->setRepentInput(['enum-class' => 'SchemaFieldType']);

        $fixed = $this->prophet->repent('/x.php', $code)->newContent;

        // The rewritten file must be syntactically valid PHP.
        $this->assertNotFalse((new \PhpParser\ParserFactory)->createForNewestSupportedVersion()->parse($fixed));
    }

    public function test_repent_converts_same_file_readers(): void
    {
        // issue #30: the exact Field.php readers must convert with the retype.
        $code = "<?php\n"
            . "class Field extends Data {\n"
            . "    public function __construct(public string \$type = SchemaFieldType::String->value) {}\n"
            . "    public function wireSocketType(): string { return \$this->type; }\n"
            . "    public function elementWireType(): string|null { return \$this->type === 'array' ? null : \$this->type; }\n"
            . "    public function label(): string { return 'Field: ' . \$this->type; }\n"
            . "}";
        $this->prophet->setRepentInput(['enum-class' => 'SchemaFieldType']);

        $fixed = $this->prophet->repent('/x.php', $code)->newContent;

        // (1) direct return in a `: string` method → ->value
        $this->assertStringContainsString('return $this->type->value;', $fixed);
        // (2a) comparison literal → enum case
        $this->assertStringContainsString("\$this->type === SchemaFieldType::Array", $fixed);
        // (2b) return-through-ternary in a `string|null` method → ->value
        $this->assertStringContainsString('null : $this->type->value', $fixed);
        // concat operand → ->value
        $this->assertStringContainsString("'Field: ' . \$this->type->value", $fixed);
        // and the property + default retyped
        $this->assertStringContainsString('public SchemaFieldType $type = SchemaFieldType::String', $fixed);
        // result parses
        $this->assertNotFalse((new \PhpParser\ParserFactory)->createForNewestSupportedVersion()->parse($fixed));
    }

    public function test_repent_emits_compareself_form_for_a_compareself_enum(): void
    {
        // issue #31: when the reused enum uses CompareSelf, the comparison must
        // become `Case->equals($x)` — not `$x === Case`, which SuggestCompareSelfTrait
        // would immediately flag.
        $enum = \JesseGall\CodeCommandments\Tests\Fixtures\EnumWithCompareSelf::class;
        $code = "<?php\nclass Field extends Data {\n public function __construct(public string \$type = 'array') {}\n public function w(): string { return \$this->type === 'array' ? 'a' : \$this->type; }\n}";
        $this->prophet->setRepentInput(['enum-class' => $enum, 'field' => 'type']);

        $fixed = $this->prophet->repent('/x.php', $code)->newContent;

        $this->assertStringContainsString('EnumWithCompareSelf::Array->equals($this->type)', $fixed);
        $this->assertStringNotContainsString("\$this->type === ", $fixed);
        $this->assertNotFalse((new \PhpParser\ParserFactory)->createForNewestSupportedVersion()->parse($fixed));
    }

    public function test_repent_keeps_identical_form_for_a_plain_enum(): void
    {
        $enum = \JesseGall\CodeCommandments\Tests\Fixtures\PlainEnumNoCompareSelf::class;
        $code = "<?php\nclass Field extends Data {\n public function __construct(public string \$type = 'array') {}\n public function w(): bool { return \$this->type === 'array'; }\n}";
        $this->prophet->setRepentInput(['enum-class' => $enum, 'field' => 'type']);

        $fixed = $this->prophet->repent('/x.php', $code)->newContent;

        $this->assertStringContainsString('$this->type === PlainEnumNoCompareSelf::Array', $fixed);
        $this->assertStringNotContainsString('->equals(', $fixed);
    }

    public function test_repent_does_not_unwrap_non_string_readers(): void
    {
        // A reader where the enum itself is fine (e.g. returned from an enum-typed
        // method) must NOT get ->value.
        $code = "<?php\nclass A extends Data {\n public function __construct(public string \$status = 'on') {}\n public function s(): SomeStatus { return \$this->status; }\n}";
        $this->prophet->setRepentInput(['enum-class' => 'SomeStatus']);

        $fixed = $this->prophet->repent('/x.php', $code)->newContent;

        $this->assertStringContainsString('return $this->status;', $fixed);
        $this->assertStringNotContainsString('$this->status->value', $fixed);
    }

    public function test_repent_is_ambiguous_with_multiple_fields_and_no_field_input(): void
    {
        $code = "<?php\nclass A extends Data { public string \$direction; public string \$status; }";
        $this->prophet->setRepentInput(['create-enum-class' => 'X', 'cases' => 'a,b']);

        $result = $this->prophet->repent('/x.php', $code);

        $this->assertFalse($result->absolved);
        $this->assertStringContainsString('field=', (string) $result->failureReason);
    }

    public function test_repent_targets_the_named_field(): void
    {
        $code = "<?php\nclass A extends Data { public string \$direction; public string \$status; }";
        $this->prophet->setRepentInput(['create-enum-class' => 'Status', 'cases' => 'active,archived', 'field' => 'status']);

        $result = $this->prophet->repent('/x.php', $code);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('public Status $status', $result->newContent);
        $this->assertStringContainsString('public string $direction', $result->newContent);
    }

    public function test_non_data_class_finding_is_autofixable_in_file(): void
    {
        $judgment = $this->judge('class WorkflowTimelineStep { public string $status; }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertTrue($judgment->warnings[0]->autoFixable);
        $this->assertStringContainsString('IN-FILE', $judgment->warnings[0]->message);
        $this->assertStringContainsString('cross-file', $judgment->warnings[0]->message);
    }

    public function test_data_class_finding_is_autofixable(): void
    {
        $judgment = $this->judge('class NodeSocketData extends Data { public function __construct(public string $direction) {} }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertTrue($judgment->warnings[0]->autoFixable);
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n" . $body);
    }
}
