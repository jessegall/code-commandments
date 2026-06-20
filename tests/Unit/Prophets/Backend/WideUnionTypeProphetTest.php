<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\WideUnionTypeProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class WideUnionTypeProphetTest extends TestCase
{
    private WideUnionTypeProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new WideUnionTypeProphet;
    }

    public function test_exempts_its_own_union_and_option_primitives(): void
    {
        // The Union/Option primitives this prophet recommends live in the
        // configured support_namespace and must never flag themselves.
        $exempt = $this->prophet->exemptClasses();

        $this->assertContains('App\\Support\\Union', $exempt);
        $this->assertContains('App\\Support\\ScalarUnion', $exempt);
        $this->assertContains('App\\Support\\UnionCast', $exempt);
        $this->assertContains('App\\Support\\Option', $exempt);
    }

    public function test_exempt_classes_follow_configured_support_namespace(): void
    {
        $this->prophet->configure(['support_namespace' => 'Acme\\Primitives']);

        $this->assertContains('Acme\\Primitives\\Union', $this->prophet->exemptClasses());
        $this->assertNotContains('App\\Support\\Union', $this->prophet->exemptClasses());
    }

    public function test_two_member_union_is_a_warning(): void
    {
        $judgment = $this->judge('class A { public function m(string | int $x): void {} }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertCount(0, $judgment->sins);
        // string | int is an all-scalar union → ScalarUnion is its home.
        $this->assertStringContainsString('ScalarUnion', $judgment->warnings[0]->message);
    }

    public function test_three_plus_member_union_is_a_sin(): void
    {
        $judgment = $this->judge('class A { public function m(array | string | null $x = null): void {} }');

        $this->assertTrue($judgment->isFallen());
        $this->assertCount(1, $judgment->sins);
        $this->assertCount(0, $judgment->warnings);
    }

    public function test_all_scalar_union_warns_and_suggests_scalar_union(): void
    {
        // No null → polymorphism → warning (not a blocking sin), still steered
        // to ScalarUnion.
        $judgment = $this->judge('class A { public function m(string | int | float $x): void {} }');

        $this->assertCount(0, $judgment->sins);
        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('ScalarUnion', $judgment->warnings[0]->message);
    }

    public function test_null_free_polymorphic_union_is_a_warning_not_a_sin(): void
    {
        // issue #26: an always-present one-of-N union (here the pipe-spec DSL
        // `string | object | array`) is ad-hoc polymorphism, not value-or-nothing
        // — a warning, never a blocking sin.
        $judgment = $this->judge('class Pipeline { public function pipe(string | object | array $p): void {} }');

        $this->assertCount(0, $judgment->sins);
        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('polymorphism', $judgment->warnings[0]->message);
    }

    public function test_null_bearing_wide_union_is_still_a_sin(): void
    {
        // Value-or-nothing keeps the blocking sin.
        $judgment = $this->judge('class A { public function m(array | string | null $x = null): void {} }');

        $this->assertCount(1, $judgment->sins);
        $this->assertStringContainsString('Option', $judgment->sins[0]->message);
    }

    public function test_nullable_scalar_union_suggests_scalar_option(): void
    {
        $judgment = $this->judge('class A { public function m(string | int | null $x = null): void {} }');

        $this->assertCount(1, $judgment->sins);
        $this->assertStringContainsString('ScalarOption', $judgment->sins[0]->message);
    }

    public function test_non_scalar_union_keeps_general_guidance(): void
    {
        $judgment = $this->judge('class A { public function m(array | string $x): void {} }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringNotContainsString('ScalarUnion', $judgment->warnings[0]->message);
        $this->assertStringContainsString('Option', $judgment->warnings[0]->message);
    }

    public function test_class_union_suggests_a_shared_interface(): void
    {
        // #62: a null-free union of classes that are one concept — the cleanest
        // remedy is a shared interface, not only Option / Union-wrapping.
        $judgment = $this->judge('class A { public function m(ResourceFilterCondition | ResourceFilterGroup $node): void {} }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('shared interface', $judgment->warnings[0]->message);
    }

    public function test_docblock_three_member_with_spaces_is_a_sin(): void
    {
        // The space inside array<string, int> must not truncate the type.
        $judgment = $this->judge('class A { /** @param array<string, int>|string|null $x */ public function m($x) {} }');

        $this->assertCount(1, $judgment->sins);
    }

    public function test_does_not_flag_a_simple_nullable(): void
    {
        // `?T` is the idiomatic nullable (a NullableType, not a union) — exempt.
        $judgment = $this->judge('class A { public function m(?Thing $x = null): void {} }');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_spelled_out_nullable(): void
    {
        // `T | null` is the same type as `?T` — a simple nullable. Flagging one
        // syntax but not the other is inconsistent (issue #24). The reported
        // code: `paletteFor(WorkflowType | null $type)`.
        $judgment = $this->judge('class A { public function paletteFor(WorkflowType | null $type): array { return []; } }');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_spelled_out_nullable_in_docblock(): void
    {
        $judgment = $this->judge('class A { /** @param WorkflowType|null $type */ public function paletteFor($type): array { return []; } }');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_arrayable_array_convenience_union(): void
    {
        // `Arrayable | array` is the typed-or-raw input contract (Spatie Data
        // implements Arrayable; callers pass the object or its plain array) —
        // not value-or-nothing, not polymorphism. Issue #32: the reported code
        // `link(Arrayable | array $data = T_Array::EMPTY)`.
        $judgment = $this->judge('class A { public function link(\Illuminate\Contracts\Support\Arrayable | array $data = []): void {} }');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_arrayable_array_union_by_short_name(): void
    {
        $judgment = $this->judge('class A { public function link(Arrayable | array $data = []): void {} }');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_arrayable_array_convenience_union_in_docblock(): void
    {
        $judgment = $this->judge('class A { /** @param Arrayable|array $data */ public function link($data = []): void {} }');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_view_or_redirect_controller_union(): void
    {
        // #37: `View | RedirectResponse` is the Laravel render-or-redirect
        // controller idiom — a framework contract, not under-modelled polymorphism.
        $judgment = $this->judge('class C { public function show(): \Illuminate\View\View | \Illuminate\Http\RedirectResponse { } }');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_response_or_redirect_union_by_short_name(): void
    {
        $judgment = $this->judge('class C { public function show(): JsonResponse | RedirectResponse { } }');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_render_or_redirect_union_in_docblock(): void
    {
        $judgment = $this->judge('class C { /** @return View|RedirectResponse */ public function show() { return null; } }');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_render_or_redirect_exemption_does_not_swallow_a_third_member(): void
    {
        $judgment = $this->judge('class C { public function show(): View | RedirectResponse | JsonResponse { } }');

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_arrayable_array_exemption_does_not_swallow_a_third_member(): void
    {
        // Add a third member and the convenience exemption must NOT apply.
        $judgment = $this->judge('class A { public function link(Arrayable | array | string $data = []): void {} }');

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_three_member_union_with_null_still_fires(): void
    {
        // A simple nullable is width-1-plus-null; this is width-2-plus-null —
        // still under-modelled, so the null exemption must NOT swallow it.
        $judgment = $this->judge('class A { public function m(array | string | null $x = null): void {} }');

        $this->assertCount(1, $judgment->sins);
    }

    public function test_does_not_flag_a_union_inside_an_attribute_class(): void
    {
        // Attribute ctor args must be constant expressions — an Option/Union can
        // never live there, so the suggestion is unactionable (issue #25 pt 1).
        $judgment = $this->judge(
            '#[\Attribute(\Attribute::TARGET_PROPERTY)] '
            . 'class ContextInput { public function __construct('
            . 'public array | string | null $options = null, '
            . 'public string | int $id = 0) {} }'
        );

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_flags_a_wide_union_in_a_plain_class_not_an_attribute(): void
    {
        // The same shape outside an attribute class still fires.
        $judgment = $this->judge('class Plain { public function __construct(public array | string | null $options = null) {} }');

        $this->assertCount(1, $judgment->sins);
    }

    public function test_does_not_flag_a_union_on_an_override_method(): void
    {
        // The signature is inherited from an interface/base — not the author's
        // to change, so the suggestion is unactionable (issue #25 pt 2).
        $judgment = $this->judge(
            'class A extends Base { #[\Override] public function morph(): array | string | null { return null; } }'
        );

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_flags_a_union_on_a_non_override_method(): void
    {
        $judgment = $this->judge('class A { public function morph(): array | string | null { return null; } }');

        $this->assertCount(1, $judgment->sins);
    }

    public function test_does_not_flag_a_union_inside_a_generic(): void
    {
        $judgment = $this->judge('class A { /** @return Option<array|string> */ public function m(): Option { return Option::none(); } }');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_warning_band_can_be_disabled(): void
    {
        $prophet = (new WideUnionTypeProphet)->configure(['warnings_enabled' => false]);

        // 2-member warning gone …
        $this->assertTrue($prophet->judge('/x.php', "<?php\nclass A { public function m(string | int \$x): void {} }")->isRighteous());
        // … but 3+ is still a sin.
        $this->assertCount(1, $prophet->judge('/x.php', "<?php\nclass A { public function m(array | string | null \$x = null): void {} }")->sins);
    }

    public function test_respects_configured_thresholds(): void
    {
        $prophet = (new WideUnionTypeProphet)->configure(['warn_at_types' => 3, 'sin_at_types' => 4]);

        // 2 now below the warning floor.
        $this->assertTrue($prophet->judge('/x.php', "<?php\nclass A { public function m(string | int \$x): void {} }")->isRighteous());
        // 3 is now a warning.
        $this->assertCount(1, $prophet->judge('/x.php', "<?php\nclass A { public function m(array | string | null \$x = null): void {} }")->warnings);
        // 4 is a sin.
        $this->assertCount(1, $prophet->judge('/x.php', "<?php\nclass A { public function m(array | string | int | null \$x = null): void {} }")->sins);
    }

    public function test_data_property_union_suggests_union_but_is_not_autofixable(): void
    {
        // #77: the UnionCast/Union rewrite changes the property's runtime type and
        // breaks readers — it is a SUGGESTION (advisory), never a blind auto-fix.
        $judgment = $this->judge('class NodeSocketData extends Data { public function __construct(public array | string $isVisibleRule) {} }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertNotTrue($judgment->warnings[0]->autoFixable);
        $this->assertStringContainsString('UnionCast', $judgment->warnings[0]->message);
        $this->assertStringContainsString('NOT auto-fixable', $judgment->warnings[0]->message);
    }

    public function test_repent_does_not_rewrite_a_data_union_property(): void
    {
        // #77: never auto-apply the UnionCast rewrite (it was producing
        // non-compiling, behaviour-breaking edits — missing imports + type change).
        $prophet = (new WideUnionTypeProphet)->configure(['support_namespace' => 'App\\Support']);
        $code = "<?php\nnamespace App\\Data;\n\nuse App\\Data\\Data;\n\nclass NodeSocketData extends Data\n{\n    public function __construct(\n        public array | string \$isVisibleRule,\n    ) {}\n}";

        $this->assertFalse($prophet->repent('/x.php', $code)->absolved);
    }

    public function test_non_data_union_is_not_autofixable(): void
    {
        $judgment = $this->judge('class Plain { public array | string $x; }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertNotTrue($judgment->warnings[0]->autoFixable);
    }

    public function test_class_member_union_is_not_autofixable(): void
    {
        // Money is a class, not a builtin T case — leave it to the human.
        $judgment = $this->judge('class A extends Data { public function __construct(public Money | string $x) {} }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertNotTrue($judgment->warnings[0]->autoFixable);
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertNotNull($this->prophet->advisory());
    }

    public function test_exempts_a_callable_polyform_union(): void
    {
        // #139: a callable mixed with a value/class-string has no common supertype
        // to narrow to — a deliberate poly-form (lazy-or-eager, predicate). Exempt.
        $native = $this->judge('class A { public function d(\Closure | object $x): void {} }');
        $this->assertTrue($native->isRighteous(), 'Closure|object lazy-or-eager must be exempt');

        $docblock = $this->judge("class A {\n/** @return bool|\\Closure|class-string */\npublic function c() { return true; } }");
        $this->assertTrue($docblock->isRighteous(), 'bool|Closure|class-string predicate must be exempt');
    }

    public function test_a_null_bearing_callable_union_still_fires(): void
    {
        // null present ⇒ value-or-nothing (→ Option), never a poly-form exemption.
        $judgment = $this->judge('class A { public function e(): \Closure | array | null { return null; } }');

        $this->assertGreaterThan(0, count($judgment->sins) + count($judgment->warnings));
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n" . $body);
    }

    // ── #62: narrow-to-a-shared-interface (index-backed) ─────────────────

    public function test_flags_and_auto_fixes_a_class_union_with_a_narrow_shared_interface(): void
    {
        [$prophet, $file, $src] = $this->indexedUnion('ResourceFilterCondition | ResourceFilterGroup', sharedInterface: true);

        $judgment = $prophet->judge($file, $src);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('narrow shared interface `ResourceFilterNode`', $judgment->warnings[0]->message);
        $this->assertTrue($judgment->warnings[0]->autoFixable);

        $result = $prophet->repent($file, $src);
        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('apply(ResourceFilterNode $node)', $result->newContent);
        $this->assertStringNotContainsString('ResourceFilterCondition | ResourceFilterGroup', $result->newContent);
    }

    public function test_refuses_to_narrow_to_an_over_broad_interface(): void
    {
        // Both members implement only Stringable — narrowing to it would WIDEN
        // the type. Must NOT suggest the interface and must NOT auto-fix.
        [$prophet, $file, $src] = $this->indexedUnion('Foo | Bar', sharedInterface: false);

        $judgment = $prophet->judge($file, $src);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringNotContainsString('narrow shared interface', $judgment->warnings[0]->message);
        $this->assertNotTrue($judgment->warnings[0]->autoFixable);
        $this->assertFalse($prophet->repent($file, $src)->absolved);
    }

    /**
     * @return array{0: WideUnionTypeProphet, 1: string, 2: string}
     */
    private function indexedUnion(string $union, bool $sharedInterface): array
    {
        $dir = sys_get_temp_dir() . '/cc-union-' . uniqid();
        @mkdir($dir, 0755, true);
        $dir = realpath($dir);
        $ns = 'App\\Filters';

        if ($sharedInterface) {
            file_put_contents("$dir/Node.php", "<?php\nnamespace {$ns};\ninterface ResourceFilterNode {}\n");
            file_put_contents("$dir/Cond.php", "<?php\nnamespace {$ns};\nclass ResourceFilterCondition implements ResourceFilterNode {}\n");
            file_put_contents("$dir/Grp.php", "<?php\nnamespace {$ns};\nclass ResourceFilterGroup implements ResourceFilterNode {}\n");
        } else {
            file_put_contents("$dir/Foo.php", "<?php\nnamespace {$ns};\nclass Foo implements \\Stringable { public function __toString(): string { return ''; } }\n");
            file_put_contents("$dir/Bar.php", "<?php\nnamespace {$ns};\nclass Bar implements \\Stringable { public function __toString(): string { return ''; } }\n");
        }

        $file = "$dir/Filter.php";
        $src = "<?php\nnamespace {$ns};\nclass Filter { public function apply({$union} \$node): void {} }\n";
        file_put_contents($file, $src);

        $index = \JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex::build(glob("$dir/*.php") ?: []);

        $prophet = new WideUnionTypeProphet;
        $prophet->setCodebaseIndex($index);
        $prophet->configure(['warn_at' => 2]);

        return [$prophet, $file, $src];
    }
}
