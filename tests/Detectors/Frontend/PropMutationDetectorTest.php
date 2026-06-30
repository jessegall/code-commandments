<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Frontend;

use JesseGall\CodeCommandments\Detectors\Frontend\PropMutationDetector;
use JesseGall\CodeCommandments\Vue\Codebase;
use PHPUnit\Framework\TestCase;

final class PropMutationDetectorTest extends TestCase
{
    public function test_flags_v_model_bound_to_a_prop(): void
    {
        $found = $this->find(
            "<script setup lang=\"ts\">defineProps<{ open: boolean }>();</script>"
            . '<template><Dialog v-model:open="open"><p>x</p></Dialog></template>'
        );

        $this->assertCount(1, $found);
        $this->assertSame('Dialog', $found[0]->tag);
    }

    public function test_flags_an_assignment_to_a_prop_in_a_handler(): void
    {
        // The real bug: `@click="confirmingClose = true"` on a read-only prop — a silent no-op.
        $found = $this->find(
            "<script setup lang=\"ts\">defineProps<{ confirmingClose: boolean }>();</script>"
            . '<template><button @click="confirmingClose = true">Cancel</button></template>'
        );

        $this->assertCount(1, $found);
        $this->assertSame('button', $found[0]->tag);
    }

    public function test_does_not_flag_a_prop_shadowed_by_use_v_model(): void
    {
        // `const open = useVModel(props, 'open')` — the template `open` is the writable local,
        // not the prop. The exact false positive that ui Input/Textarea would otherwise raise.
        $this->assertSame([], $this->find(
            "<script setup lang=\"ts\">const props = defineProps<{ open: boolean }>();\nconst open = useVModel(props, 'open', emit);</script>"
            . '<template><Dialog v-model:open="open"><p>x</p></Dialog></template>'
        ));
    }

    public function test_does_not_flag_a_local_ref_or_define_model(): void
    {
        $this->assertSame([], $this->find(
            "<script setup lang=\"ts\">const open = ref(false);</script>"
            . '<template><Dialog v-model:open="open"><p>x</p></Dialog></template>'
        ));
        $this->assertSame([], $this->find(
            "<script setup lang=\"ts\">const value = defineModel<string>();</script>"
            . '<template><input v-model="value" /></template>'
        ));
    }

    public function test_does_not_flag_reading_a_prop_or_emitting_an_event(): void
    {
        $this->assertSame([], $this->find(
            "<script setup lang=\"ts\">defineProps<{ open: boolean }>();\nconst emit = defineEmits<{ 'update:open': [boolean] }>();</script>"
            . '<template><Dialog :open="open" @update:open="emit(\'update:open\', $event)"><p>x</p></Dialog></template>'
        ));
    }

    /**
     * @return list<\JesseGall\CodeCommandments\Vue\ElementMatch>
     */
    private function find(string $sfc): array
    {
        return new PropMutationDetector()->find(Codebase::fromString($sfc));
    }
}
