<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\TaintedInputToSinkProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class TaintedInputToSinkProphetTest extends TestCase
{
    private TaintedInputToSinkProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new TaintedInputToSinkProphet;
    }

    public function test_flags_request_input_concatenated_into_raw_sql(): void
    {
        $j = $this->judge('\DB::statement("DELETE FROM logs WHERE id = " . $request->input("id"));');
        $this->assertTrue($j->hasWarnings());
        $this->assertStringContainsString('SECURITY', $j->warnings[0]->message);
    }

    public function test_flags_request_input_in_where_raw_and_exec(): void
    {
        $this->assertTrue($this->judge('$q->whereRaw("x = " . $request->x);')->hasWarnings());
        $this->assertTrue($this->judge('exec("convert " . $request->input("file"));')->hasWarnings());
        $this->assertTrue($this->judge('unserialize($request->input("payload"));')->hasWarnings());
    }

    public function test_does_not_flag_a_bound_parameter(): void
    {
        // request input in the BINDINGS array (arg 2) is safe — it is bound, not raw.
        $this->assertTrue($this->judge('$q->whereRaw("col = ?", [$request->input("id")]);')->isRighteous());
        $this->assertTrue($this->judge('\DB::statement("id = ?", [$request->input("id")]);')->isRighteous());
    }

    public function test_does_not_flag_when_cast_or_whitelisted(): void
    {
        $this->assertTrue($this->judge('\DB::statement("id = " . (int) $request->input("id"));')->isRighteous());
        $this->assertTrue($this->judge('\DB::statement(in_array($request->x, ["a","b"]) ? $request->x : "a");')->isRighteous());
    }

    public function test_does_not_flag_literal_sql_or_bound_where(): void
    {
        $this->assertTrue($this->judge('\DB::statement("DELETE FROM logs WHERE id = 1");')->isRighteous());
        $this->assertTrue($this->judge('$q->where("id", $request->input("id"));')->isRighteous(), 'non-raw where() binds the value');
    }

    public function test_is_correctness_tier_and_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertNotNull($this->prophet->advisory());
    }

    private function judge(string $body): Judgment
    {
        $code = "<?php\nnamespace App;\nclass C { public function m(\$request, \$q) { {$body} } }";

        return $this->prophet->judge('/tmp/x.php', $code);
    }
}
