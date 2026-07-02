<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Vue\Oracle;

use JesseGall\CodeCommandments\Vue\Oracle\TscDiagnostics;
use PHPUnit\Framework\TestCase;

final class TscDiagnosticsTest extends TestCase
{
    public function test_it_reads_each_probes_resolved_type_and_local_name(): void
    {
        $output = <<<'TXT'
            __cc_probe_x.vue(9,7): error TS2322: Type 'number[]' is not assignable to type '__CcNo_pageSizes'.
            __cc_probe_x.vue(11,7): error TS2322: Type 'string | null' is not assignable to type '__CcNo_label'.
            TXT;

        $this->assertSame(
            ['pageSizes' => 'number[]', 'label' => 'string | null'],
            TscDiagnostics::types($output),
        );
    }

    public function test_it_ignores_unrelated_diagnostics_and_other_error_codes(): void
    {
        $output = <<<'TXT'
            src/Real.vue(3,1): error TS2304: Cannot find name 'foo'.
            __cc_probe_x.vue(9,7): error TS2322: Type 'MergedVariant[]' is not assignable to type '__CcNo_variants'.
            some noise that mentions Type 'X' but nothing else
            TXT;

        $this->assertSame(['variants' => 'MergedVariant[]'], TscDiagnostics::types($output));
    }

    public function test_a_resolved_type_containing_quotes_survives(): void
    {
        // A string-literal union resolves with inner quotes — sliced to the pivot, not the first quote.
        $output = "f.vue(9,7): error TS2322: Type '\"a\" | \"b\"' is not assignable to type '__CcNo_mode'.";

        $this->assertSame(['mode' => '"a" | "b"'], TscDiagnostics::types($output));
    }

    public function test_a_checker_unknown_or_any_is_dropped_not_asserted(): void
    {
        // vue-tsc itself failing to type it tells us nothing the AST didn't already know.
        $output = <<<'TXT'
            f.vue(9,7): error TS2322: Type 'unknown' is not assignable to type '__CcNo_a'.
            f.vue(11,7): error TS2322: Type 'any' is not assignable to type '__CcNo_b'.
            TXT;

        $this->assertSame([], TscDiagnostics::types($output));
    }
}
