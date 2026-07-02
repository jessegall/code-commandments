<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Vue\Oracle;

use JesseGall\CodeCommandments\Vue\Oracle\TypeProbe;
use JesseGall\CodeCommandments\Vue\Sfc;
use PHPUnit\Framework\TestCase;

final class TypeProbeTest extends TestCase
{
    private const string COMPONENT = <<<'VUE'
        <script setup lang="ts">
        const pageSizes = [50, 100, 200];
        const label = computed(() => schema.value.label);
        </script>
        <template><div>{{ label }}</div></template>
        VUE;

    public function test_it_appends_a_name_encoding_probe_inside_script_setup(): void
    {
        $probe = new TypeProbe(Sfc::parse(self::COMPONENT), ['pageSizes', 'label'])->source();

        $this->assertNotNull($probe);
        // Each probe assigns the local to an impossible type whose NAME encodes the local.
        $this->assertStringContainsString("const __cc_pageSizes: __CcNo_pageSizes = pageSizes;", $probe);
        $this->assertStringContainsString("const __cc_label: __CcNo_label = label;", $probe);
        // Injected INSIDE the script block — before `</script>`, where the locals are in scope.
        $this->assertStringEndsWith("</template>", trim($probe));
        $this->assertLessThan(strpos($probe, '</script>'), strpos($probe, 'const __cc_pageSizes'));
    }

    public function test_it_preserves_the_original_source_verbatim_around_the_probe(): void
    {
        $probe = new TypeProbe(Sfc::parse(self::COMPONENT), ['pageSizes'])->source();

        $this->assertStringContainsString('const pageSizes = [50, 100, 200];', (string) $probe);
        $this->assertStringContainsString('<template><div>{{ label }}</div></template>', (string) $probe);
    }

    public function test_there_is_nothing_to_probe_without_names_or_a_setup_block(): void
    {
        $this->assertNull(new TypeProbe(Sfc::parse(self::COMPONENT), [])->source());

        $plain = "<script lang=\"ts\">export default {};</script>\n<template><div /></template>";
        $this->assertNull(new TypeProbe(Sfc::parse($plain), ['x'])->source());
    }
}
