package frontend_test

import (
	"testing"

	"github.com/jessegall/code-commandments/detectors/frontend"
	"github.com/jessegall/code-commandments/engine/frontend/frontendtest"
)

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

func TestAnIndexedAccessToAnObjectTypeIsNoArray(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{
		"src/Form.vue": "<template>\n  <template v-for=\"(label, value) in urlTypes\" :key=\"value\">\n    <li>{{ label }}</li>\n  </template>\n</template>\n\n" +
			"<script lang=\"ts\" setup>\ntype Page = { urlTypes: { [key: string]: string } }\n\ndefineProps<{ urlTypes: Page['urlTypes'] }>()\n</script>\n",
	})
	if found := (frontend.IndexAsKeyDetector{}).Find(codebase); len(found) > 0 {
		t.Errorf("a key over an object is read as an index at %s", found[0].Location())
	}
}
