<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\TypeHonestyProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

/**
 * TypeHonestyProphet — the Boundary & Typing discipline.
 *
 * Two verdicts (see docs/disciplines.md, BoundaryTypingDiscipline):
 *   V1 FAKE-REQUIRED   (sin)  — a nullable value coalesced to its type's EMPTY
 *                               literal to fill a REQUIRED, non-nullable, no-default
 *                               constructor slot.
 *   V2 PHANTOM-NULLABLE (warn) — a boundary DTO (Spatie Data) whose EVERY field is
 *                               `?T = null` (ratio 1.0, ≥2 fields).
 *
 * Detection is reflection/AST over the EFFECTIVE constructor — never name lists.
 * Tests keep the call site and the target class in one snippet so the constructor
 * resolves in-file (no CodebaseIndex needed).
 */
class TypeHonestyProphetTest extends TestCase
{
    private TypeHonestyProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new TypeHonestyProphet;
    }

    // ---------------------------------------------------------------------
    // V1 — FAKE-REQUIRED (sin): empty-literal coalesce into a required slot
    // ---------------------------------------------------------------------

    public function test_v1_flags_empty_string_into_required_string(): void
    {
        $j = $this->judge(<<<'PHP'
            class Action { public function __construct(public string $id) {} public static function from(array $a): self { return new self($a['id']); } }
            class Decoder { public function d($raw): Action { return Action::from(['id' => $raw->id ?? '']); } }
            PHP);

        $this->assertCount(1, $j->sins);
        $this->assertStringContainsString('required', $j->sins[0]->message);
    }

    public function test_v1_flags_t_string_empty_call_into_required_string(): void
    {
        // The real-world case: `?? T_String::empty()` — a no-arg empty factory call.
        $j = $this->judge(<<<'PHP'
            class Action { public function __construct(public string $id) {} public static function from(array $a): self { return new self($a['id']); } }
            class Decoder { public function d($raw): Action { return Action::from(['id' => $raw->id ?? \JesseGall\PhpTypes\T_String::empty()]); } }
            PHP);

        $this->assertCount(1, $j->sins);
    }

    public function test_v1_flags_t_string_empty_constant(): void
    {
        $j = $this->judge(<<<'PHP'
            class Action { public function __construct(public string $id) {} public static function from(array $a): self { return new self($a['id']); } }
            class Decoder { public function d($raw): Action { return Action::from(['id' => $raw->id ?? \JesseGall\PhpTypes\T_String::EMPTY]); } }
            PHP);

        $this->assertCount(1, $j->sins);
    }

    public function test_v1_ignores_empty_array_into_required_array(): void
    {
        // An empty array is a LEGITIMATE value — looping over `[]` is harmless. Whether
        // a required array truly needs elements can only be judged by following how it
        // is used (a future use-following verdict), so V1 does not fire on arrays.
        $j = $this->judge(<<<'PHP'
            class Action { public function __construct(public array $items) {} public static function from(array $a): self { return new self($a['items']); } }
            class Decoder { public function d($raw): Action { return Action::from(['items' => $raw->items ?? []]); } }
            PHP);

        $this->assertTrue($j->isRighteous());
    }

    public function test_v1_ignores_zero_into_required_int(): void
    {
        // 0 is frequently a real value (a count, an offset). Without following the use,
        // a zero default is not a fake identity the way an empty string is. Deferred.
        $j = $this->judge(<<<'PHP'
            class Action { public function __construct(public int $count) {} public static function from(array $a): self { return new self($a['count']); } }
            class Decoder { public function d($raw): Action { return Action::from(['count' => $raw->count ?? 0]); } }
            PHP);

        $this->assertTrue($j->isRighteous());
    }

    public function test_v1_ignores_false_into_required_bool(): void
    {
        // false is a real value, not an absent one. Deferred like the numerics/arrays.
        $j = $this->judge(<<<'PHP'
            class Action { public function __construct(public bool $active) {} public static function from(array $a): self { return new self($a['active']); } }
            class Decoder { public function d($raw): Action { return Action::from(['active' => $raw->active ?? false]); } }
            PHP);

        $this->assertTrue($j->isRighteous());
    }

    public function test_v1_flags_self_const_key(): void
    {
        // Real decoders key the array by a class constant, not a string literal.
        $j = $this->judge(<<<'PHP'
            class Action { public function __construct(public string $name) {} public static function from(array $a): self { return new self($a['name']); } }
            class Decoder { private const KEY_NAME = 'name'; public function d($raw): Action { return Action::from([self::KEY_NAME => $raw->name ?? '']); } }
            PHP);

        $this->assertCount(1, $j->sins);
    }

    public function test_v1_two_required_slots_yield_two_sins(): void
    {
        $j = $this->judge(<<<'PHP'
            class Action { public function __construct(public string $id, public string $summary) {} public static function from(array $a): self { return new self($a['id'], $a['summary']); } }
            class Decoder { public function d($raw): Action { return Action::from(['id' => $raw->id ?? '', 'summary' => $raw->summary ?? '']); } }
            PHP);

        $this->assertCount(2, $j->sins);
    }

    public function test_v1_sin_is_not_autofixable_and_carries_a_stable_symbol(): void
    {
        $j = $this->judge(<<<'PHP'
            class Action { public function __construct(public string $id) {} public static function from(array $a): self { return new self($a['id']); } }
            class Decoder { public function d($raw): Action { return Action::from(['id' => $raw->id ?? '']); } }
            PHP);

        $this->assertCount(1, $j->sins);
        $this->assertNotTrue($j->sins[0]->autoFixable);
        $this->assertNotNull($j->sins[0]->symbol);
    }

    public function test_v1_ignores_nullable_target_param(): void
    {
        // Optional slot: coalescing-to-empty is fine (the field accepts absence).
        $j = $this->judge(<<<'PHP'
            class Action { public function __construct(public ?string $summary = null) {} public static function from(array $a): self { return new self($a['summary'] ?? null); } }
            class Decoder { public function d($raw): Action { return Action::from(['summary' => $raw->summary ?? '']); } }
            PHP);

        $this->assertTrue($j->isRighteous());
    }

    public function test_v1_ignores_target_param_with_default(): void
    {
        // A param with its own default owns the empty value — not faked at the call site.
        $j = $this->judge(<<<'PHP'
            class Action { public function __construct(public string $summary = '') {} public static function from(array $a): self { return new self($a['summary'] ?? ''); } }
            class Decoder { public function d($raw): Action { return Action::from(['summary' => $raw->summary ?? '']); } }
            PHP);

        $this->assertTrue($j->isRighteous());
    }

    public function test_v1_ignores_non_empty_default(): void
    {
        // `?? 'n/a'` / `?? 5` is an intentional fallback value, not an empty-identity fake.
        $j = $this->judge(<<<'PHP'
            class Action { public function __construct(public string $id, public int $count) {} public static function from(array $a): self { return new self($a['id'], $a['count']); } }
            class Decoder { public function d($raw): Action { return Action::from(['id' => $raw->id ?? 'n/a', 'count' => $raw->count ?? 5]); } }
            PHP);

        $this->assertTrue($j->isRighteous());
    }

    public function test_v1_ignores_bare_empty_literal_without_coalesce(): void
    {
        // A plain `'id' => ''` is not the coalesce-to-empty pattern this verdict owns.
        $j = $this->judge(<<<'PHP'
            class Action { public function __construct(public string $id) {} public static function from(array $a): self { return new self($a['id']); } }
            class Decoder { public function d(): Action { return Action::from(['id' => '']); } }
            PHP);

        $this->assertTrue($j->isRighteous());
    }

    public function test_v1_ignores_unresolvable_target_class(): void
    {
        // Conservative: cannot resolve the constructor -> never fire a sin.
        $j = $this->judge(<<<'PHP'
            class Decoder { public function d($raw) { return \Vendor\Unknown::from(['id' => $raw->id ?? '']); } }
            PHP);

        $this->assertTrue($j->isRighteous());
    }

    public function test_v1_ignores_key_not_matching_any_param(): void
    {
        $j = $this->judge(<<<'PHP'
            class Action { public function __construct(public string $id) {} public static function from(array $a): self { return new self($a['id']); } }
            class Decoder { public function d($raw): Action { return Action::from(['zzz' => $raw->zzz ?? '']); } }
            PHP);

        $this->assertTrue($j->isRighteous());
    }

    // ---------------------------------------------------------------------
    // V2 — PHANTOM-NULLABLE (warn): an all-`?T = null` boundary DTO
    // ---------------------------------------------------------------------

    public function test_v2_flags_all_nullable_data_dto(): void
    {
        // V2 fires only with a consumer that DE-NULLS a field (use-following gate):
        // here `strtoupper($x->a)` consumes the field as a required value.
        $j = $this->judge(<<<'PHP'
            use Spatie\LaravelData\Data;
            class RawX extends Data { public function __construct(public readonly ?string $a = null, public readonly ?int $b = null) {} }
            class Consumer { public function h(): string { $x = RawX::from([]); return strtoupper($x->a); } }
            PHP);

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString('nullable', $j->warnings[0]->message);
    }

    public function test_v2_flags_three_field_dto_once(): void
    {
        // One finding per class, not one per field — even though a field is consumed.
        $j = $this->judge(<<<'PHP'
            use Spatie\LaravelData\Data;
            class RawX extends Data { public function __construct(public readonly ?string $a = null, public readonly ?string $b = null, public readonly ?array $c = null) {} }
            class Consumer { public function h(): void { $x = RawX::from([]); foreach ($x->c as $i) { echo $i; } } }
            PHP);

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString('RawX', (string) $j->warnings[0]->symbol);
    }

    public function test_v2refine_ignores_dto_with_no_consumer(): void
    {
        // Use-following: with no consumer evidence that any field is required, V2 is
        // silent (conservative) — an all-nullable DTO alone is not enough.
        $j = $this->judge(<<<'PHP'
            use Spatie\LaravelData\Data;
            class RawZ extends Data { public function __construct(public readonly ?string $a = null, public readonly ?int $b = null) {} }
            PHP);

        $this->assertTrue($j->isRighteous());
    }

    public function test_v2refine_ignores_dto_whose_consumer_keeps_the_null(): void
    {
        // ScheduleSpec shape: the field is read then branched on (`=== null`) — the null
        // is MEANINGFUL (genuinely optional), so V2 stays silent.
        $j = $this->judge(<<<'PHP'
            use Spatie\LaravelData\Data;
            class RawY extends Data { public function __construct(public readonly ?string $cron = null, public readonly ?int $interval = null) {} }
            class Consumer { public function h(): void { $raw = RawY::from([])->cron; if ($raw === null) { return; } } }
            PHP);

        $this->assertTrue($j->isRighteous());
    }

    public function test_v2refine_fires_when_field_coalesced_to_nonnull_default(): void
    {
        // RawGraphPayload shape: the consumer erases the null to an empty default — the
        // field should not be nullable (it should default), so V2 fires.
        $j = $this->judge(<<<'PHP'
            use Spatie\LaravelData\Data;
            class RawG extends Data { public function __construct(public readonly ?array $nodes = null, public readonly ?array $edges = null) {} }
            class Consumer { public function h(): array { $p = RawG::from([]); return $p->nodes ?? []; } }
            PHP);

        $this->assertCount(1, $j->warnings);
    }

    public function test_v2_ignores_dto_with_a_nonnullable_discriminator(): void
    {
        // A type-discriminated DTO (one required field + the rest nullable) is a
        // different, defensible shape — ratio < 1.0, do not fire.
        $j = $this->judge(<<<'PHP'
            use Spatie\LaravelData\Data;
            class RawX extends Data { public function __construct(public readonly string $type, public readonly ?string $a = null, public readonly ?int $b = null) {} }
            PHP);

        $this->assertTrue($j->isRighteous());
    }

    public function test_v2_ignores_nullable_field_with_nonnull_default(): void
    {
        // `?string $a = 'x'` is nullable-with-a-real-default, not the `?T = null` smell.
        $j = $this->judge(<<<'PHP'
            use Spatie\LaravelData\Data;
            class RawX extends Data { public function __construct(public readonly ?string $a = 'x', public readonly ?int $b = null) {} }
            PHP);

        $this->assertTrue($j->isRighteous());
    }

    public function test_v2_ignores_single_field_dto(): void
    {
        $j = $this->judge(<<<'PHP'
            use Spatie\LaravelData\Data;
            class RawX extends Data { public function __construct(public readonly ?string $a = null) {} }
            PHP);

        $this->assertTrue($j->isRighteous());
    }

    public function test_v2_ignores_all_nullable_plain_class_not_a_boundary(): void
    {
        // V2 is boundary-scoped: a plain class (not a Data/FormRequest) is left alone.
        $j = $this->judge(<<<'PHP'
            class Plain { public function __construct(public readonly ?string $a = null, public readonly ?int $b = null) {} }
            PHP);

        $this->assertTrue($j->isRighteous());
    }

    // ---------------------------------------------------------------------
    // V5 — REQUIRED-BUT-NULLABLE: type says optional, validation says required
    // ---------------------------------------------------------------------

    public function test_v5_flags_nullable_field_required_by_array_rules(): void
    {
        $j = $this->judge(<<<'PHP'
            use Spatie\LaravelData\Data;
            class Payload extends Data {
                public function __construct(public readonly ?string $name = null) {}
                public static function rules(): array { return ['name' => ['required', 'string']]; }
            }
            PHP);

        $this->assertCount(1, $j->sins);
        $this->assertStringContainsString('required', $j->sins[0]->message);
    }

    public function test_v5_flags_nullable_field_required_by_pipe_string_rules(): void
    {
        $j = $this->judge(<<<'PHP'
            use Spatie\LaravelData\Data;
            class Payload extends Data {
                public function __construct(public readonly ?string $name = null) {}
                public function rules(): array { return ['name' => 'required|string']; }
            }
            PHP);

        $this->assertCount(1, $j->sins);
    }

    public function test_v5_flags_nullable_field_with_required_attribute(): void
    {
        $j = $this->judge(<<<'PHP'
            use Spatie\LaravelData\Data;
            use Spatie\LaravelData\Attributes\Validation\Required;
            class Payload extends Data {
                public function __construct(#[Required] public readonly ?string $name = null) {}
            }
            PHP);

        $this->assertCount(1, $j->sins);
    }

    public function test_v5_ignores_non_nullable_required_field(): void
    {
        // Type and contract agree — no contradiction.
        $this->assertTrue($this->judge(<<<'PHP'
            use Spatie\LaravelData\Data;
            class Payload extends Data {
                public function __construct(public readonly string $name) {}
                public static function rules(): array { return ['name' => ['required', 'string']]; }
            }
            PHP)->isRighteous());
    }

    public function test_v5_ignores_nullable_field_not_required(): void
    {
        $this->assertTrue($this->judge(<<<'PHP'
            use Spatie\LaravelData\Data;
            class Payload extends Data {
                public function __construct(public readonly ?string $name = null) {}
                public static function rules(): array { return ['name' => ['string']]; }
            }
            PHP)->isRighteous());
    }

    public function test_v5_ignores_conditional_required_and_explicit_nullable(): void
    {
        // `required_if` is conditional (not unconditionally required); an explicit
        // `nullable` rule means the null is intended.
        $this->assertTrue($this->judge(<<<'PHP'
            use Spatie\LaravelData\Data;
            class Payload extends Data {
                public function __construct(public readonly ?string $a = null, public readonly ?string $b = null) {}
                public static function rules(): array { return ['a' => ['required_if:b,1'], 'b' => ['required', 'nullable']]; }
            }
            PHP)->isRighteous());
    }

    public function test_v5_ignores_non_boundary_class_with_rules(): void
    {
        $this->assertTrue($this->judge(<<<'PHP'
            class NotData {
                public function __construct(public readonly ?string $name = null) {}
                public function rules(): array { return ['name' => ['required']]; }
            }
            PHP)->isRighteous());
    }

    // ---------------------------------------------------------------------
    // V7 — NONNULL-GUARD: a null check on a value the type says is never null
    // ---------------------------------------------------------------------

    public function test_v7_flags_identical_null_on_nonnullable_param(): void
    {
        $j = $this->judge('class C { public function h(string $x): bool { return $x === null; } }');

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString('non-nullable', $j->warnings[0]->message);
    }

    public function test_v7_flags_not_identical_null(): void
    {
        $j = $this->judge('class C { public function h(int $n): bool { return $n !== null; } }');

        $this->assertCount(1, $j->warnings);
    }

    public function test_v7_flags_null_on_the_left(): void
    {
        $j = $this->judge('class C { public function h(string $x): bool { return null === $x; } }');

        $this->assertCount(1, $j->warnings);
    }

    public function test_v7_flags_is_null_on_nonnullable(): void
    {
        $j = $this->judge('class C { public function h(string $x): bool { return is_null($x); } }');

        $this->assertCount(1, $j->warnings);
    }

    public function test_v7_flags_nonnullable_this_property(): void
    {
        $j = $this->judge('class C { private string $name = ""; public function h(): bool { return $this->name === null; } }');

        $this->assertCount(1, $j->warnings);
    }

    public function test_v7_ignores_nullable_param(): void
    {
        $this->assertTrue($this->judge('class C { public function h(?string $x): bool { return $x === null; } }')->isRighteous());
    }

    public function test_v7_ignores_untyped_and_mixed(): void
    {
        $this->assertTrue($this->judge('class C { public function h($x): bool { return $x === null; } }')->isRighteous());
        $this->assertTrue($this->judge('class C { public function h(mixed $x): bool { return $x === null; } }')->isRighteous());
    }

    public function test_v7_ignores_comparison_to_a_non_null_value(): void
    {
        $this->assertTrue($this->judge('class C { public function h(string $x): bool { return $x === "a"; } }')->isRighteous());
    }

    public function test_v7_ignores_empty_on_nonnullable(): void
    {
        // empty() is a falsiness check (empty('') is true) — legitimate on a non-nullable,
        // not a dead null-guard. V7 owns only the null comparison.
        $this->assertTrue($this->judge('class C { public function h(string $x): bool { return empty($x); } }')->isRighteous());
    }

    // ---------------------------------------------------------------------
    // V4 — MIXED-SEAM: a private/protected mixed/object param every caller types
    // ---------------------------------------------------------------------

    public function test_v4_flags_mixed_param_when_all_callers_pass_same_type(): void
    {
        $j = $this->judge(<<<'PHP'
            class C {
                private function h(mixed $x): void {}
                public function a(Foo $f): void { $this->h($f); }
                public function b(Foo $g): void { $this->h($g); }
            }
            PHP);

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString('Foo', $j->warnings[0]->message);
    }

    public function test_v4_flags_object_param_single_typed_caller(): void
    {
        $j = $this->judge(<<<'PHP'
            class C {
                private function h(object $x): void {}
                public function a(Node $n): void { $this->h($n); }
            }
            PHP);

        $this->assertCount(1, $j->warnings);
    }

    public function test_v4_ignores_when_callers_pass_different_types(): void
    {
        $this->assertTrue($this->judge(<<<'PHP'
            class C {
                private function h(mixed $x): void {}
                public function a(Foo $f): void { $this->h($f); }
                public function b(Bar $b): void { $this->h($b); }
            }
            PHP)->isRighteous());
    }

    public function test_v4_ignores_when_a_caller_passes_a_scalar_literal(): void
    {
        $this->assertTrue($this->judge(<<<'PHP'
            class C {
                private function h(mixed $x): void {}
                public function a(Foo $f): void { $this->h($f); }
                public function b(): void { $this->h('s'); }
            }
            PHP)->isRighteous());
    }

    public function test_v4_ignores_untyped_caller_arg(): void
    {
        $this->assertTrue($this->judge(<<<'PHP'
            class C {
                private function h(mixed $x): void {}
                public function a($f): void { $this->h($f); }
            }
            PHP)->isRighteous());
    }

    public function test_v4_ignores_no_callers_and_public_method_and_typed_param(): void
    {
        $this->assertTrue($this->judge('class C { private function h(mixed $x): void {} }')->isRighteous());
        $this->assertTrue($this->judge('class C { public function h(mixed $x): void {} public function a(Foo $f): void { $this->h($f); } }')->isRighteous());
        $this->assertTrue($this->judge('class C { private function h(Foo $x): void {} public function a(Foo $f): void { $this->h($f); } }')->isRighteous());
    }

    // ---------------------------------------------------------------------
    // V6 — BOOL-UNION: a `T|false` / `T|bool` (T a class) found-or-not sentinel
    // ---------------------------------------------------------------------

    public function test_v6_flags_class_or_false_return(): void
    {
        $j = $this->judge('class C { public function find(): User|false { return false; } }');

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString('Option', $j->warnings[0]->message);
    }

    public function test_v6_ignores_class_or_bool_polyform(): void
    {
        // `T|bool` is a poly-form (bool is a real value — a flag, or a closure-or-bool
        // condition), not a found-or-not sentinel. Only the literal `false` type fires.
        $this->assertTrue($this->judge('class C { public function find(): User|bool { return false; } }')->isRighteous());
    }

    public function test_v6_ignores_polyform_value_unions(): void
    {
        // Closure|false (callable poly-form), multi-member poly-forms, and bool|Optional
        // are real value unions, not single-object found-or-not sentinels.
        $this->assertTrue($this->judge('class C { public function p(): Closure|false { return false; } }')->isRighteous());
        $this->assertTrue($this->judge('class C { public function p(): Closure|string|bool { return false; } }')->isRighteous());
        $this->assertTrue($this->judge('class C { public function p(): Foo|Bar|false { return false; } }')->isRighteous());
        $this->assertTrue($this->judge('class C { public function h(bool|Optional $x): void {} }')->isRighteous());
    }

    public function test_v6_ignores_framework_response_defer_contract(): void
    {
        // `Response|false` is the framework render/defer contract (the handler reads
        // `=== false`), not author-chosen absence — not the author's to model with Option.
        $this->assertTrue($this->judge('class C { public function render($r): RedirectResponse|false { return false; } }')->isRighteous());
        $this->assertTrue($this->judge('class C { public function render($r): JsonResponse|false { return false; } }')->isRighteous());
    }

    public function test_v6_flags_class_or_false_param(): void
    {
        $j = $this->judge('class C { public function h(User|false $u): void {} }');

        $this->assertCount(1, $j->warnings);
    }

    public function test_v6_flags_on_a_plain_function(): void
    {
        $j = $this->judge('function find(): User|false { return false; }');

        $this->assertCount(1, $j->warnings);
    }

    public function test_v6_ignores_scalar_or_false_stdlib_idiom(): void
    {
        // strpos/preg-style `string|false`, `int|false`, `array|false` are stdlib idioms,
        // not object-presence sentinels — T must be a class type to fire.
        $this->assertTrue($this->judge('class C { public function p(): string|false { return false; } }')->isRighteous());
        $this->assertTrue($this->judge('class C { public function p(): int|false { return false; } }')->isRighteous());
        $this->assertTrue($this->judge('class C { public function p(): array|false { return false; } }')->isRighteous());
    }

    public function test_v6_ignores_nullable_class(): void
    {
        // `?User` is the Option-adoption case (AbsenceOption), not a bool sentinel.
        $this->assertTrue($this->judge('class C { public function p(): ?User { return null; } }')->isRighteous());
    }

    public function test_v6_ignores_nullable_bool(): void
    {
        $this->assertTrue($this->judge('class C { public function p(): ?bool { return null; } }')->isRighteous());
    }

    // ---------------------------------------------------------------------
    // V3 — DTO-OR-ARRAY-SEAM: an internal `DataClass|array` re-hydration seam
    // ---------------------------------------------------------------------

    public function test_v3_flags_data_or_array_private_param(): void
    {
        $j = $this->judge('use Spatie\LaravelData\Data; class Foo extends Data {} class C { private function h(Foo|array $x): void {} }');

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString('hydrate', $j->warnings[0]->message);
    }

    public function test_v3_flags_data_or_array_protected_return(): void
    {
        $j = $this->judge('use Spatie\LaravelData\Data; class Foo extends Data {} class C { protected function h(): Foo|array { return []; } }');

        $this->assertCount(1, $j->warnings);
    }

    public function test_v3_ignores_public_method(): void
    {
        // A public `T|array` is a deliberate flexible entry point — internal seams only.
        $this->assertTrue($this->judge('use Spatie\LaravelData\Data; class Foo extends Data {} class C { public function h(Foo|array $x): void {} }')->isRighteous());
    }

    public function test_v3_ignores_arrayable_or_array(): void
    {
        // The blessed `Arrayable|array` shape — Arrayable is not a Data class.
        $this->assertTrue($this->judge('class C { private function h(\Illuminate\Contracts\Support\Arrayable|array $x): void {} }')->isRighteous());
    }

    public function test_v3_ignores_non_data_class_union(): void
    {
        // `Collection|array` / any non-Data `T|array` — T must resolve to a Data class.
        $this->assertTrue($this->judge('class Foo {} class C { private function h(Foo|array $x): void {} }')->isRighteous());
    }

    public function test_v3_ignores_plain_array_and_nullable_data(): void
    {
        $this->assertTrue($this->judge('class C { private function h(array $x): void {} }')->isRighteous());
        $this->assertTrue($this->judge('use Spatie\LaravelData\Data; class Foo extends Data {} class C { private function h(?Foo $x): void {} }')->isRighteous());
    }

    // ---------------------------------------------------------------------
    // V8 — DISCRIMINATED-PUNT: a mixed payload discriminated + coerced per arm
    // ---------------------------------------------------------------------

    public function test_v8_flags_tagged_union_punt(): void
    {
        $j = $this->judge(<<<'PHP'
            use Spatie\LaravelData\Data;
            class Payload extends Data {
                public function __construct(public readonly string $aspect, public readonly mixed $value) {}
            }
            class Handler {
                public function h(Payload $p): mixed {
                    return match ($p->aspect) {
                        'a' => (string) $p->value,
                        'b' => (array) $p->value,
                    };
                }
            }
            PHP);

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString('tagged-union', $j->warnings[0]->message);
    }

    public function test_v8_ignores_dto_with_no_matching_consumer(): void
    {
        // A mixed-payload DTO alone — no consumer discriminates it — is not flagged.
        $this->assertTrue($this->judge(<<<'PHP'
            use Spatie\LaravelData\Data;
            class Payload extends Data {
                public function __construct(public readonly string $aspect, public readonly mixed $value) {}
            }
            PHP)->isRighteous());
    }

    public function test_v8_ignores_match_that_does_not_touch_the_payload(): void
    {
        $this->assertTrue($this->judge(<<<'PHP'
            use Spatie\LaravelData\Data;
            class Payload extends Data {
                public function __construct(public readonly string $aspect, public readonly mixed $value) {}
            }
            class Handler {
                public function h(Payload $p): string {
                    return match ($p->aspect) { 'a' => 'x', default => 'y' };
                }
            }
            PHP)->isRighteous());
    }

    public function test_v8_ignores_dto_without_a_mixed_payload(): void
    {
        $this->assertTrue($this->judge(<<<'PHP'
            use Spatie\LaravelData\Data;
            class Payload extends Data {
                public function __construct(public readonly string $aspect, public readonly string $value) {}
            }
            class Handler {
                public function h(Payload $p): string {
                    return match ($p->aspect) { 'a' => $p->value, default => '' };
                }
            }
            PHP)->isRighteous());
    }

    // ---------------------------------------------------------------------
    // Meta
    // ---------------------------------------------------------------------

    public function test_emits_an_advisory_rubric_for_its_warnings(): void
    {
        $this->assertNotNull($this->prophet->advisory());
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n" . $body);
    }
}
