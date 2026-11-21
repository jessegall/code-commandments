package typescript_test

import (
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine/frontend/frontendtest"
	"github.com/jessegall/code-commandments/rules"
)

// shipped is the shipped rule judging TypeScript whose sin has the name.
func shipped(t *testing.T, name string) detectors.Detector {
	t.Helper()
	for _, each := range rules.Of(catalog.TypeScript) {
		if each.Sin().Definition().Name == name {
			return each
		}
	}
	t.Fatalf("no shipped rule %s judges TypeScript", name)

	return nil
}

func TestADeclarationAfterAnImportWithNoSemicolonIsRead(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{"src/blob.ts": "import { Tex } from './tex'\n\nexport class Blob {\n  private texture!: Tex\n\n  destroy () {\n    this.texture?.dispose()\n  }\n}\n"})
	found := shipped(t, "defended-certain-field").Find(codebase)
	if len(found) != 1 || found[0].Line() != 7 {
		t.Errorf("the defended field is found %d times", len(found))
	}
}

func render(stubs string) string {
	return "import { ref } from 'vue'\nimport { Key } from './key'\n\nexport const renderComponent = (h: any, Component: any) => {\n  const options = {\n    global: {\n      provide: {\n        [<symbol>Key]: ref(1),\n      },\n      stubs: {" + stubs + "},\n    },\n  }\n  return h.render(Component, options)\n}\n"
}

func TestAnAngleBracketAssertionKeepsWhatFollowsIt(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{
		"src/main-content.ts": render("\n        AllSongsScreen: h.stub('all-songs-screen'),\n        AlbumListScreen: h.stub('album-list-screen'),\n        ArtistListScreen: h.stub('artist-list-screen'),\n      "),
		"src/play-button.ts":  render(""),
	})
	if found := shipped(t, "duplicate-function").Find(codebase); len(found) > 0 {
		t.Errorf("two different helpers are read as duplicates at %s", found[0].Location())
	}
	same := frontendtest.FromSource(t, map[string]string{"src/a.ts": render(""), "src/b.ts": render("")})
	if found := shipped(t, "duplicate-function").Find(same); len(found) != 2 {
		t.Errorf("two identical helpers are found %d times, not as the duplicates they are", len(found))
	}
}

func TestAGenericFunctionWrittenTwiceIsADuplicate(t *testing.T) {
	resolve := "export function resolve<T> (value: T | (() => T), fallback: T): T {\n  if (value === undefined) {\n    return fallback\n  }\n  const resolved = typeof value === 'function' ? (value as () => T)() : value\n  return resolved ?? fallback\n}\n"
	codebase := frontendtest.FromSource(t, map[string]string{"src/builder.ts": resolve, "src/item.ts": resolve})
	if found := shipped(t, "duplicate-function").Find(codebase); len(found) != 2 {
		t.Errorf("the generic function written twice is found %d times", len(found))
	}
}

func submit(route string, onError string) string {
	return "export function submit (): void {\n  submitting.value = true\n\n  router.post(route('" + route + "'), form, {\n    preserveScroll: true,\n    onError (e) {\n" + onError + "    },\n    onSuccess () {\n      open.value = false\n      submitting.value = false\n    },\n  }\n  return h.render(Component, options)\n}\n"
}

func TestAMethodInAnObjectLiteralIsReadWhole(t *testing.T) {
	handled := "      errors.value = e as Record<string, string>\n      submitting.value = false\n"
	codebase := frontendtest.FromSource(t, map[string]string{"src/print.ts": submit("print-agents.store", handled), "src/relay.ts": submit("relay-agents.store", handled)})
	if found := shipped(t, "near-duplicate-function").Find(codebase); len(found) != 2 {
		t.Errorf("two handlers that differ in a route name are found %d times", len(found))
	}
	other := frontendtest.FromSource(t, map[string]string{"src/print.ts": submit("store", handled), "src/relay.ts": submit("store", "      submitting.value = false\n")})
	if found := shipped(t, "duplicate-function").Find(other); len(found) > 0 {
		t.Errorf("two handlers whose error handling differs are read as duplicates at %s", found[0].Location())
	}
}
