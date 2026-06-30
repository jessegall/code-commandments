<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Rewriting\Frontend;

use JesseGall\CodeCommandments\Cli\Rewriting\Frontend\SwitchCaseScribe;
use JesseGall\CodeCommandments\Detectors\Frontend\SwitchCaseDetector;
use JesseGall\CodeCommandments\Vue\Codebase;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the scribe end to end on REAL files: write `.vue` to a temp dir, run the
 * rewrite, apply it to disk, assert the result, and (in tearDown) delete it all.
 */
final class SwitchCaseScribeTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/vue_scribe_' . uniqid();
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function test_rewrites_a_chain_into_switchcase_on_disk(): void
    {
        $file = $this->write('Card.vue', <<<'VUE'
            <script setup lang="ts">
            const status = 'active';
            </script>

            <template>
              <div class="card">
                <Badge v-if="status === 'active'" tone="green">Active</Badge>
                <Badge v-else-if="status === 'pending'" tone="amber">Pending</Badge>
                <Badge v-else tone="grey">Unknown</Badge>
              </div>
            </template>
            VUE);

        $this->apply();
        $result = file_get_contents($file);

        $this->assertStringContainsString('<SwitchCase :value="status">', $result);
        $this->assertStringContainsString('<template #active><Badge tone="green">Active</Badge></template>', $result);
        $this->assertStringContainsString('<template #pending><Badge tone="amber">Pending</Badge></template>', $result);
        $this->assertStringContainsString('<template #default><Badge tone="grey">Unknown</Badge></template>', $result);

        // The structural directives are gone — and the sin no longer fires.
        $this->assertStringNotContainsString('v-if', $result);
        $this->assertStringNotContainsString('v-else', $result);
        $this->assertCount(0, new SwitchCaseDetector()->find(Codebase::scan($this->dir)));
    }

    public function test_is_idempotent(): void
    {
        $this->write('Card.vue', <<<'VUE'
            <template>
              <p v-if="tab === 'one'">1</p>
              <p v-else-if="tab === 'two'">2</p>
            </template>
            VUE);

        $this->apply();
        $once = file_get_contents($this->dir . '/Card.vue');

        $this->assertSame([], new SwitchCaseScribe()->rewrites(Codebase::scan($this->dir)), 'a second pass finds nothing to fix');
        $this->apply();
        $this->assertSame($once, file_get_contents($this->dir . '/Card.vue'));
    }

    public function test_leaves_a_genuine_conditional_untouched(): void
    {
        $original = "<template>\n  <div v-if=\"open\">x</div>\n  <div v-else>y</div>\n</template>\n";
        $this->write('Plain.vue', $original);

        $this->assertSame([], new SwitchCaseScribe()->rewrites(Codebase::scan($this->dir)));
        $this->assertSame($original, file_get_contents($this->dir . '/Plain.vue'));
    }

    private function write(string $name, string $content): string
    {
        $path = $this->dir . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }

    private function apply(): void
    {
        foreach (new SwitchCaseScribe()->rewrites(Codebase::scan($this->dir)) as $path => $content) {
            file_put_contents($path, $content);
        }
    }
}
