<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Frontend;

use JesseGall\CodeCommandments\Detectors\Frontend\LoopWithConditionDetector;
use JesseGall\CodeCommandments\Vue\Codebase;
use PHPUnit\Framework\TestCase;

final class LoopWithConditionDetectorTest extends TestCase
{
    public function test_flags_v_for_and_v_if_on_the_same_element(): void
    {
        $found = $this->find('<template><ul><li v-for="x in xs" v-if="x.ok" :key="x.id">{{ x.name }}</li></ul></template>');

        $this->assertCount(1, $found);
        $this->assertSame('li', $found[0]->tag);
    }

    public function test_flags_v_for_with_v_else_if(): void
    {
        $found = $this->find('<template><div v-for="row in rows" v-else-if="row.shown">{{ row.label }}</div></template>');

        $this->assertCount(1, $found);
        $this->assertSame('div', $found[0]->tag);
    }

    public function test_does_not_flag_the_template_wrapper_form(): void
    {
        // The CORRECT shape — the two directives live on different elements.
        $found = $this->find('<template><template v-for="x in xs" :key="x.id"><li v-if="x.ok">{{ x.name }}</li></template></template>');

        $this->assertSame([], $found);
    }

    public function test_does_not_flag_v_for_alone_or_v_if_alone(): void
    {
        $this->assertSame([], $this->find('<template><li v-for="x in xs" :key="x.id">{{ x.name }}</li></template>'));
        $this->assertSame([], $this->find('<template><li v-if="ready">{{ label }}</li></template>'));
    }

    public function test_does_not_flag_v_for_beside_v_show(): void
    {
        // `v-show` toggles display, it is not structural — only v-if/v-else-if is the trap.
        $this->assertSame([], $this->find('<template><li v-for="x in xs" v-show="x.ok" :key="x.id">{{ x.name }}</li></template>'));
    }

    /**
     * @return list<\JesseGall\CodeCommandments\Vue\ElementMatch>
     */
    private function find(string $template): array
    {
        return new LoopWithConditionDetector()->find(Codebase::fromString($template));
    }
}
