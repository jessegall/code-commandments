<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Frontend;

use JesseGall\CodeCommandments\Detectors\Frontend\ControlFlowOnElementDetector;
use JesseGall\CodeCommandments\Scribes\Frontend\WrapControlFlowScribe;
use JesseGall\CodeCommandments\Vue\Codebase;
use PHPUnit\Framework\TestCase;

final class ControlFlowOnElementDetectorTest extends TestCase
{
    public function test_flags_control_flow_on_a_real_element(): void
    {
        $found = $this->find('<template><div><span v-if="open">x</span></div></template>');

        $this->assertCount(1, $found);
        $this->assertSame('span', $found[0]->tag);
    }

    public function test_flags_v_for_and_v_else_on_elements(): void
    {
        $found = $this->find(
            '<template><ul><li v-for="i in items" :key="i">{{ i }}</li></ul>'
            . '<p v-if="a">a</p><p v-else>b</p></template>',
        );

        $this->assertCount(3, $found); // the <li>, and both <p>s
    }

    public function test_leaves_control_flow_on_a_template_alone(): void
    {
        $found = $this->find('<template><template v-if="open"><div>x</div></template></template>');

        $this->assertCount(0, $found);
    }

    public function test_does_not_flag_v_show(): void
    {
        // v-show toggles display on a real element — it can't live on a <template>.
        $this->assertCount(0, $this->find('<template><div v-show="open">x</div></template>'));
    }

    public function test_scribe_wraps_the_element_in_a_template(): void
    {
        $rewrites = new WrapControlFlowScribe()->rewrite(
            $this->find('<template>\n  <div>\n    <span v-if="open" class="x">hi</span>\n  </div>\n</template>'),
        );
        $source = reset($rewrites);

        $this->assertStringContainsString('<template v-if="open">', $source);
        $this->assertStringContainsString('<span class="x">hi</span>', $source);
        $this->assertStringNotContainsString('<span v-if', $source);
    }

    public function test_scribe_carries_v_for_and_its_key(): void
    {
        $rewrites = new WrapControlFlowScribe()->rewrite(
            $this->find('<template>\n  <ul>\n    <li v-for="i in items" :key="i.id" class="row">{{ i.name }}</li>\n  </ul>\n</template>'),
        );
        $source = reset($rewrites);

        $this->assertStringContainsString('<template v-for="i in items" :key="i.id">', $source);
        $this->assertStringContainsString('<li class="row">{{ i.name }}</li>', $source);
    }

    /**
     * @return list<\JesseGall\CodeCommandments\Vue\ElementMatch>
     */
    private function find(string $template): array
    {
        return new ControlFlowOnElementDetector()->find(Codebase::fromString($template));
    }
}
