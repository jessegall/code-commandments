<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferCoercionHelperProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferNativeTypedAccessorProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferNativeTypedAccessorProphetTest extends TestCase
{
    private PreferNativeTypedAccessorProphet $prophet;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferNativeTypedAccessorProphet();
        $this->dir = sys_get_temp_dir() . '/cc_native_accessor_' . uniqid();
        @mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*.php') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    /**
     * Write the given files, build a cross-file index over them, and judge the
     * named callee file with the index injected.
     *
     * @param  array<string, string>  $files  name => source
     */
    private function interproc(string $calleeFile, array $files): Judgment
    {
        foreach ($files as $name => $src) {
            file_put_contents($this->dir . '/' . $name, $src);
        }

        $this->prophet->setCodebaseIndex(CodebaseIndex::build(glob($this->dir . '/*.php') ?: []));

        $path = $this->dir . '/' . $calleeFile;

        return $this->prophet->judge($path, (string) file_get_contents($path));
    }

    /**
     * Wrap a method body in a class whose method takes a typed-bag param. The
     * `use` makes the prophet resolve the receiver to the real (reflectable)
     * fixture, so its structural gate confirms the accessor family.
     */
    private function judge(string $body, string $type = 'FluentBag', string $param = 'request'): Judgment
    {
        $src = "<?php\n"
            . "namespace App;\n"
            . "use JesseGall\\CodeCommandments\\Tests\\Fixtures\\Backend\\FluentBag;\n"
            . "use JesseGall\\CodeCommandments\\Tests\\Fixtures\\Backend\\PlainDto;\n"
            . "use JesseGall\\PhpTypes\\T_String;\n"
            . "use JesseGall\\PhpTypes\\T_Int;\n"
            . "use JesseGall\\PhpTypes\\T_Float;\n"
            . "use JesseGall\\PhpTypes\\T_Bool;\n"
            . "class Handler {\n"
            . "  public function handle({$type} \${$param}) {\n"
            . "    {$body}\n"
            . "  }\n"
            . "}\n";

        return $this->prophet->judge('/Handler.php', $src);
    }

    // ---- FIRES: the three coercion forms -----------------------------------

    public function test_flags_php_types_coercer_over_get(): void
    {
        $j = $this->judge('return T_String::coerce($request->get(\'id\'));');

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString("string('id')", $j->warnings[0]->message);
        $this->assertTrue($j->warnings[0]->autoFixable);
    }

    public function test_flags_coerce_or_null_to_float(): void
    {
        $j = $this->judge('return T_Float::coerceOrNull($request->get(\'rate\'));');

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString("float('rate')", $j->warnings[0]->message);
    }

    public function test_flags_bool_cast_over_get_with_default(): void
    {
        $j = $this->judge('$live = (bool) $request->get(\'live\', false); return $live;');

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString("boolean('live')", $j->warnings[0]->message);
    }

    public function test_flags_int_cast(): void
    {
        $j = $this->judge('return (int) $request->get(\'qty\', 5);');

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString("integer('qty')", $j->warnings[0]->message);
    }

    public function test_flags_string_cast(): void
    {
        $j = $this->judge('return (string) $request->get(\'name\');');

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString("string('name')", $j->warnings[0]->message);
    }

    public function test_flags_cast_over_input_method(): void
    {
        $j = $this->judge('return (string) $request->input(\'name\');');

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString("string('name')", $j->warnings[0]->message);
    }

    public function test_flags_cast_over_array_access(): void
    {
        $j = $this->judge('return (string) $request[\'id\'];');

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString("string('id')", $j->warnings[0]->message);
    }

    public function test_flags_guard_ternary_with_intermediate_variable(): void
    {
        // The headline case: $raw + is_array(...) guard collapses to ->array().
        $j = $this->judge(
            '$rawPayload = $request->get(\'payload\');'
            . "\n        \$payload = is_array(\$rawPayload) ? \$rawPayload : [];"
            . "\n        return \$payload;"
        );

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString("array('payload')", $j->warnings[0]->message);
        // Advisory only — collapsing also removes the dead $rawPayload.
        $this->assertFalse($j->warnings[0]->autoFixable);
    }

    public function test_flags_guard_ternary_directly_over_read(): void
    {
        $j = $this->judge('return is_string($request->get(\'name\')) ? $request->get(\'name\') : \'\';');

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString("string('name')", $j->warnings[0]->message);
    }

    public function test_flags_property_receiver(): void
    {
        $src = "<?php\nnamespace App;\n"
            . "use JesseGall\\CodeCommandments\\Tests\\Fixtures\\Backend\\FluentBag;\n"
            . "class Handler {\n"
            . "  public function __construct(private FluentBag \$request) {}\n"
            . "  public function handle() { return (string) \$this->request->get('id'); }\n"
            . "}\n";

        $j = $this->prophet->judge('/Handler.php', $src);

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString("string('id')", $j->warnings[0]->message);
    }

    public function test_flags_multiple_sites_in_one_method(): void
    {
        $j = $this->judge(
            'return [T_String::coerce($request->get(\'a\')), (bool) $request->get(\'b\'), (int) $request->get(\'c\')];'
        );

        $this->assertCount(3, $j->warnings);
    }

    // ---- DATAFLOW: value split across lines / passed down ------------------

    public function test_flags_coercion_of_variable_traced_to_read_passed_down(): void
    {
        // The real-world split: read into a var, coerce it at the USE site where
        // it is passed into another method. AST traces $id back to the read.
        $j = $this->judge(
            '$id = $request->get(\'id\');'
            . "\n        return \$this->broadcast(T_String::coerce(\$id), \$other);"
        );

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString("string('id')", $j->warnings[0]->message);
        // Traced through a variable — the read is on another line, advisory only.
        $this->assertFalse($j->warnings[0]->autoFixable);
    }

    public function test_flags_cast_of_variable_traced_to_read(): void
    {
        $j = $this->judge(
            '$live = $request->get(\'live\', false);'
            . "\n        return (bool) \$live;"
        );

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString("boolean('live')", $j->warnings[0]->message);
    }

    public function test_flags_guard_far_downstream_of_the_read(): void
    {
        // The read and its is_array() guard are many statements apart — the
        // trace scans the whole enclosing function, so distance is irrelevant.
        $j = $this->judge(
            '$payload = $request->get(\'payload\');'
            . "\n        \$this->log('x');"
            . "\n        \$token = \$this->mint();"
            . "\n        \$count = 0;"
            . "\n        foreach (\$this->items() as \$i) { \$count++; }"
            . "\n        \$data = is_array(\$payload) ? \$payload : [];"
            . "\n        return \$data;"
        );

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString("array('payload')", $j->warnings[0]->message);
    }

    public function test_silent_when_variable_reassigned_after_the_read(): void
    {
        // The nearest preceding assignment is NOT the read, so the trace breaks.
        $j = $this->judge(
            '$id = $request->get(\'id\'); $id = $this->fallback();'
            . "\n        return T_String::coerce(\$id);"
        );

        $this->assertTrue($j->isRighteous());
    }

    public function test_repent_leaves_variable_traced_coercion_to_author(): void
    {
        // Only the direct in-place wrap is auto-fixed; a traced read is advisory.
        $src = $this->wrap('$id = $request->get(\'id\'); return T_String::coerce($id);');

        $this->assertFalse($this->prophet->repent('/Handler.php', $src)->absolved);
    }

    // ---- STAYS SILENT: edge cases ------------------------------------------

    public function test_silent_when_receiver_is_not_a_typed_bag(): void
    {
        // PlainDto exposes get() + a lone string() but NOT the accessor family.
        $j = $this->judge('return (string) $dto->get(\'id\');', 'PlainDto', 'dto');

        $this->assertTrue($j->isRighteous());
    }

    public function test_silent_when_receiver_type_is_unresolvable(): void
    {
        $src = "<?php\nnamespace App;\nclass Handler {\n"
            . "  public function handle(\$request) { return (string) \$request->get('id'); }\n"
            . "}\n";

        $this->assertTrue($this->prophet->judge('/Handler.php', $src)->isRighteous());
    }

    public function test_silent_when_no_coercion_wraps_the_read(): void
    {
        $j = $this->judge('$id = $request->get(\'id\'); return $id;');

        $this->assertTrue($j->isRighteous());
    }

    public function test_silent_when_coercion_is_not_over_a_keyed_read(): void
    {
        // path() is not an untyped bag getter — nothing to simplify.
        $j = $this->judge('return (string) $request->path();');

        $this->assertTrue($j->isRighteous());
    }

    public function test_silent_for_unsupported_guard_predicate(): void
    {
        // is_numeric is deliberately NOT mapped — the kept value's family is
        // ambiguous (int? float? numeric-string?), so we never guess an accessor.
        $j = $this->judge(
            '$raw = $request->get(\'n\'); return is_numeric($raw) ? $raw : 0;'
        );

        $this->assertTrue($j->isRighteous());
    }

    public function test_silent_for_dynamic_non_string_key(): void
    {
        $j = $this->judge('$k = \'id\'; return (string) $request->get($k);');

        $this->assertTrue($j->isRighteous());
    }

    public function test_silent_when_guard_keeps_a_different_value(): void
    {
        // The kept branch is a different read than the guarded one — not an
        // identity coercion, so it is not a hand-rolled accessor.
        $j = $this->judge(
            'return is_array($request->get(\'a\')) ? $request->get(\'b\') : [];'
        );

        $this->assertTrue($j->isRighteous());
    }

    // ---- INTERPROCEDURAL: read in one method, guard/coercion in another ----

    private function bagUse(): string
    {
        return "use JesseGall\\CodeCommandments\\Tests\\Fixtures\\Backend\\FluentBag;\n";
    }

    public function test_interproc_flags_guard_on_param_fed_a_bag_read(): void
    {
        $callee = "<?php\nnamespace App;\nclass Service {\n"
            . "  public function process(\$payload) { return is_array(\$payload) ? \$payload : []; }\n"
            . "}\n";
        $caller = "<?php\nnamespace App;\n" . $this->bagUse()
            . "class Controller {\n"
            . "  public function __construct(private Service \$service) {}\n"
            . "  public function handle(FluentBag \$request) {\n"
            . "    \$payload = \$request->get('payload');\n"
            . "    return \$this->service->process(\$payload);\n"
            . "  }\n"
            . "}\n";

        $j = $this->interproc('Service.php', ['Service.php' => $callee, 'Controller.php' => $caller]);

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString("array('payload')", $j->warnings[0]->message);
        $this->assertStringContainsString('call site', $j->warnings[0]->message);
        $this->assertFalse($j->warnings[0]->autoFixable);
    }

    public function test_interproc_flags_cast_on_param_with_inline_read_at_call_site(): void
    {
        $callee = "<?php\nnamespace App;\nclass Service {\n"
            . "  public function flag(\$value) { return (bool) \$value; }\n"
            . "}\n";
        $caller = "<?php\nnamespace App;\n" . $this->bagUse()
            . "class Controller {\n"
            . "  public function __construct(private Service \$service) {}\n"
            . "  public function handle(FluentBag \$request) {\n"
            . "    return \$this->service->flag(\$request->get('live'));\n"
            . "  }\n"
            . "}\n";

        $j = $this->interproc('Service.php', ['Service.php' => $callee, 'Controller.php' => $caller]);

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString("boolean('live')", $j->warnings[0]->message);
    }

    public function test_interproc_silent_when_a_caller_passes_a_non_bag_value(): void
    {
        // Two callers; one feeds a bag read, the other a plain literal — the
        // parameter is polymorphic, so nothing fires (no false positive).
        $callee = "<?php\nnamespace App;\nclass Service {\n"
            . "  public function process(\$payload) { return is_array(\$payload) ? \$payload : []; }\n"
            . "}\n";
        $bagCaller = "<?php\nnamespace App;\n" . $this->bagUse()
            . "class A {\n  public function __construct(private Service \$service) {}\n"
            . "  public function h(FluentBag \$request) { return \$this->service->process(\$request->get('payload')); }\n}\n";
        $otherCaller = "<?php\nnamespace App;\n"
            . "class B {\n  public function __construct(private Service \$service) {}\n"
            . "  public function h() { return \$this->service->process(['hardcoded']); }\n}\n";

        $j = $this->interproc('Service.php', [
            'Service.php' => $callee,
            'A.php' => $bagCaller,
            'B.php' => $otherCaller,
        ]);

        $this->assertTrue($j->isRighteous());
    }

    public function test_interproc_silent_when_param_is_typed(): void
    {
        // A typed `array $payload` cannot receive the bag's untyped get() — the
        // guard is a different smell, not a hand-rolled accessor.
        $callee = "<?php\nnamespace App;\nclass Service {\n"
            . "  public function process(array \$payload) { return is_array(\$payload) ? \$payload : []; }\n"
            . "}\n";
        $caller = "<?php\nnamespace App;\n" . $this->bagUse()
            . "class Controller {\n  public function __construct(private Service \$service) {}\n"
            . "  public function handle(FluentBag \$request) { return \$this->service->process(\$request->array('payload')); }\n}\n";

        $j = $this->interproc('Service.php', ['Service.php' => $callee, 'Controller.php' => $caller]);

        $this->assertTrue($j->isRighteous());
    }

    public function test_interproc_silent_when_no_callers_known(): void
    {
        // The guard is on a param, but nothing in the index calls it — unknown,
        // so silent (we never guess).
        $callee = "<?php\nnamespace App;\nclass Service {\n"
            . "  public function process(\$payload) { return is_array(\$payload) ? \$payload : []; }\n"
            . "}\n";

        $j = $this->interproc('Service.php', ['Service.php' => $callee]);

        $this->assertTrue($j->isRighteous());
    }

    public function test_interproc_silent_without_index(): void
    {
        // No index injected at all — the interprocedural pass is skipped.
        $src = "<?php\nnamespace App;\nclass Service {\n"
            . "  public function process(\$payload) { return is_array(\$payload) ? \$payload : []; }\n"
            . "}\n";

        $this->assertTrue((new PreferNativeTypedAccessorProphet())->judge('/Service.php', $src)->isRighteous());
    }

    // ---- REPENT -------------------------------------------------------------

    public function test_repent_rewrites_cast_dropping_natural_zero_default(): void
    {
        $src = $this->wrap('$live = (bool) $request->get(\'live\', false); return $live;');

        $result = $this->prophet->repent('/Handler.php', $src);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString("\$request->boolean('live')", $result->newContent);
        $this->assertStringNotContainsString('(bool)', $result->newContent);
    }

    public function test_repent_carries_meaningful_default_through(): void
    {
        $src = $this->wrap('return (int) $request->get(\'qty\', 5);');

        $result = $this->prophet->repent('/Handler.php', $src);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString("\$request->integer('qty', 5)", $result->newContent);
    }

    public function test_repent_rewrites_php_types_coercer(): void
    {
        $src = $this->wrap('return T_String::coerce($request->get(\'id\'));');

        $result = $this->prophet->repent('/Handler.php', $src);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString("\$request->string('id')", $result->newContent);
        $this->assertStringNotContainsString('T_String::coerce', $result->newContent);
    }

    public function test_repent_leaves_guard_ternary_form_to_author(): void
    {
        // The guard-ternary fix removes a now-dead variable; not auto-applied.
        $src = $this->wrap(
            '$raw = $request->get(\'payload\'); $payload = is_array($raw) ? $raw : []; return $payload;'
        );

        $this->assertFalse($this->prophet->repent('/Handler.php', $src)->absolved);
    }

    public function test_repent_is_idempotent_no_op_on_clean_code(): void
    {
        $src = $this->wrap('return $request->string(\'id\');');

        $this->assertFalse($this->prophet->repent('/Handler.php', $src)->absolved);
    }

    // ---- COLLISION WIRING ---------------------------------------------------

    public function test_supersedes_prefer_coercion_helper(): void
    {
        // On a duplicated guard-ternary over a typed bag, PreferCoercionHelper
        // would say "hoist into T_*::coerce()" — the OPPOSITE of "use the native
        // accessor". The RootCauseMap edge defers it in-region.
        $this->assertContains(
            PreferCoercionHelperProphet::class,
            $this->prophet->supersedes(),
        );
    }

    private function wrap(string $body): string
    {
        return "<?php\n"
            . "namespace App;\n"
            . "use JesseGall\\CodeCommandments\\Tests\\Fixtures\\Backend\\FluentBag;\n"
            . "use JesseGall\\PhpTypes\\T_String;\n"
            . "class Handler {\n"
            . "  public function handle(FluentBag \$request) {\n"
            . "    {$body}\n"
            . "  }\n"
            . "}\n";
    }
}
