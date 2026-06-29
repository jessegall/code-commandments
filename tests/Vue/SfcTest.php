<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Vue;

use JesseGall\CodeCommandments\Vue\Element;
use JesseGall\CodeCommandments\Vue\Sfc;
use PHPUnit\Framework\TestCase;

final class SfcTest extends TestCase
{
    public function test_splits_top_level_blocks_in_document_order(): void
    {
        $sfc = Sfc::parse(<<<'VUE'
            <script setup lang="ts">
            const x = 1
            </script>

            <template>
              <div>hi</div>
            </template>

            <style scoped>.a{}</style>
            VUE);

        $this->assertSame(['script', 'template', 'style'], $sfc->order());

        $script = $sfc->block('script');
        $this->assertNotNull($script);
        $this->assertTrue($script->hasAttribute('setup'));
        $this->assertSame('ts', $script->attribute('lang'));
        $this->assertStringContainsString('const x = 1', $script->content);

        $this->assertTrue($sfc->block('style')?->hasAttribute('scoped'));
    }

    public function test_detects_script_after_template_via_order(): void
    {
        $sfc = Sfc::parse("<template><div/></template>\n<script setup></script>");

        $this->assertSame(['template', 'script'], $sfc->order());
    }

    public function test_tokenizes_nested_elements_and_keeps_directive_attributes(): void
    {
        $sfc = Sfc::parse(<<<'VUE'
            <template>
              <div :title="a > b" @click="go" v-if="ok">
                <MySwitch v-model="on" />
                <br>
                text {{ a < b }}
              </div>
            </template>
            VUE);

        $div = $this->find($sfc->template, 'div');
        $this->assertNotNull($div);
        $this->assertSame('a > b', $div->attribute(':title'));   // '>' inside the value did not end the tag
        $this->assertTrue($div->hasAttribute('@click'));
        $this->assertSame('ok', $div->attribute('v-if'));

        $switch = $this->find($sfc->template, 'MySwitch');
        $this->assertNotNull($switch);
        $this->assertSame([], $switch->children, 'self-closing component has no children');
        $this->assertSame('on', $switch->attribute('v-model'));

        $this->assertNotNull($this->find($sfc->template, 'br'), 'void element is parsed');
    }

    public function test_nested_template_does_not_end_the_sfc_block_early(): void
    {
        $sfc = Sfc::parse(<<<'VUE'
            <template>
              <ul>
                <template v-for="i in items">
                  <li>{{ i }}</li>
                </template>
              </ul>
            </template>
            <script setup>const items = []</script>
            VUE);

        $this->assertSame(['template', 'script'], $sfc->order());
        $this->assertNotNull($this->find($sfc->template, 'li'));
        $this->assertSame('i in items', $this->find($sfc->template, 'template')?->attribute('v-for'));
    }

    public function test_element_lines_map_back_to_the_vue_file(): void
    {
        $sfc = Sfc::parse("<script setup></script>\n<template>\n  <section>\n    <button/>\n  </section>\n</template>");

        $this->assertSame(3, $this->find($sfc->template, 'section')?->line);
        $this->assertSame(4, $this->find($sfc->template, 'button')?->line);
    }

    private function find(Element $node, string $tag): ?Element
    {
        foreach ($node->children as $child) {
            if ($child->tag === $tag) {
                return $child;
            }

            if (($found = $this->find($child, $tag)) !== null) {
                return $found;
            }
        }

        return null;
    }
}
