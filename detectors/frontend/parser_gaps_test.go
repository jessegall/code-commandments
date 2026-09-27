package frontend_test

import (
	"testing"

	"github.com/jessegall/code-commandments/detectors/frontend"
	"github.com/jessegall/code-commandments/engine/frontend/frontendtest"
)

// TestAnIterableTypedOnlyByInferenceIsKnownAsAnArray holds IndexAsKey to the iterable's type as the checker infers
// it: the PHP tool read a type only from a written annotation, so a list a composable returns went unjudged (koel's
// HookSlot.vue and Sidebar.vue).
func TestAnIterableTypedOnlyByInferenceIsKnownAsAnArray(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{
		"src/HookSlot.vue": "<template>\n  <component :is=\"component\" v-for=\"(component, index) in components\" :key=\"index\" />\n</template>\n\n" +
			"<script lang=\"ts\" setup>\nimport { useSlots } from './slots'\n\nconst components = useSlots()\n</script>\n",
		"src/slots.ts": "export function useSlots (): string[] {\n  return []\n}\n",
	})
	if found := (frontend.IndexAsKeyDetector{}).Find(codebase); len(found) != 1 || found[0].Line() != 2 {
		t.Errorf("the index key over an inferred array is found %d times", len(found))
	}
}
