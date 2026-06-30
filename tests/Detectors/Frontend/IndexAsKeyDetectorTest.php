<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Frontend;

use JesseGall\CodeCommandments\Detectors\Frontend\IndexAsKeyDetector;
use JesseGall\CodeCommandments\Vue\Codebase;
use PHPUnit\Framework\TestCase;

final class IndexAsKeyDetectorTest extends TestCase
{
    public function test_flags_a_two_alias_index_over_an_array_iterable(): void
    {
        $found = $this->find(
            "<script setup lang=\"ts\">defineProps<{ items: Item[] }>();</script>"
            . '<template><template v-for="(item, index) in items" :key="index"><li>{{ item.name }}</li></template></template>'
        );

        $this->assertCount(1, $found);
    }

    public function test_flags_the_index_of_a_three_alias_object_for(): void
    {
        // `(value, key, index)` — the THIRD alias is always the numeric index, no type needed.
        $found = $this->find('<template><div v-for="(value, name, index) in config" :key="index">{{ value }}</div></template>');

        $this->assertCount(1, $found);
        $this->assertSame('div', $found[0]->tag);
    }

    public function test_does_not_flag_a_two_alias_key_over_an_object_iterable(): void
    {
        // The 2nd alias of `(value, key) in object` is the property KEY — keying by it is
        // correct. Without a provable array type, we must not flag it. (The false positive
        // that calibration caught: `(_, name) in $slots :key="name"`.)
        $this->assertSame([], $this->find('<template><template v-for="(value, key) in $slots" :key="key"><span>{{ value }}</span></template></template>'));
        $this->assertSame([], $this->find(
            "<script setup lang=\"ts\">defineProps<{ choices: Record<string, string> }>();</script>"
            . '<template><option v-for="(label, key) in choices" :key="key">{{ label }}</option></template>'
        ));
    }

    public function test_does_not_flag_a_stable_identity_key(): void
    {
        $this->assertSame([], $this->find(
            "<script setup lang=\"ts\">defineProps<{ items: Item[] }>();</script>"
            . '<template><li v-for="(item, index) in items" :key="item.id">{{ item.name }}</li></template>'
        ));
    }

    public function test_does_not_flag_a_composite_key_built_from_the_index(): void
    {
        // A composite expression is not a bare index — `asChain()` is null, so it's left alone.
        $this->assertSame([], $this->find(
            "<script setup lang=\"ts\">defineProps<{ items: Item[] }>();</script>"
            . '<template><li v-for="(item, index) in items" :key="`${item.id}-${index}`">{{ item.name }}</li></template>'
        ));
    }

    /**
     * @return list<\JesseGall\CodeCommandments\Vue\ElementMatch>
     */
    private function find(string $sfc): array
    {
        return new IndexAsKeyDetector()->find(Codebase::fromString($sfc));
    }
}
