package typescript_test

import (
	"testing"

	"github.com/jessegall/code-commandments/detectors/typescript"
	"github.com/jessegall/code-commandments/engine/frontend/frontendtest"
)

// TestADeclarationAfterAnImportWithNoSemicolonIsRead holds the tool to reading a class written straight after an
// import that ends without a semicolon, as TypeScript does: the PHP tool's parser swallowed it (koel's NoiseBlob.ts).
func TestADeclarationAfterAnImportWithNoSemicolonIsRead(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{"src/blob.ts": "import { Tex } from './tex'\n\nexport class Blob {\n  private texture!: Tex\n\n  destroy () {\n    this.texture?.dispose()\n  }\n}\n"})
	found := typescript.DefendedCertainFieldDetector{}.Find(codebase)
	if len(found) != 1 || found[0].Line() != 7 {
		t.Errorf("the defended field is found %d times", len(found))
	}
}

// render is a test helper as koel writes them, providing a symbol key through an angle-bracket assertion; stubs are
// what it renders beside the component.
func render(stubs string) string {
	return "import { ref } from 'vue'\nimport { Key } from './key'\n\nexport const renderComponent = (h: any, Component: any) => {\n  return h.render(Component, {\n    global: {\n      provide: {\n        [<symbol>Key]: ref(1),\n      },\n      stubs: {" + stubs + "},\n    },\n  })\n}\n"
}

// TestAnAngleBracketAssertionKeepsWhatFollowsIt holds the tool to reading an expression past `<symbol>Key` as
// TypeScript does: the PHP tool's parser lost the rest, and two different helpers read to it as duplicates.
func TestAnAngleBracketAssertionKeepsWhatFollowsIt(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{
		"src/main-content.spec.ts": render("\n        AllSongsScreen: h.stub('all-songs-screen'),\n        AlbumListScreen: h.stub('album-list-screen'),\n        ArtistListScreen: h.stub('artist-list-screen'),\n      "),
		"src/play-button.spec.ts":  render(""),
	})
	if found := (typescript.DuplicateFunctionDetector{}).Find(codebase); len(found) > 0 {
		t.Errorf("two different helpers are read as duplicates at %s", found[0].Location())
	}
	same := frontendtest.FromSource(t, map[string]string{"src/a.spec.ts": render(""), "src/b.spec.ts": render("")})
	if found := (typescript.DuplicateFunctionDetector{}).Find(same); len(found) != 2 {
		t.Errorf("two identical helpers are found %d times, not as the duplicates they are", len(found))
	}
}

// TestAGenericFunctionWrittenTwiceIsADuplicate holds the tool to reading a function with a type parameter as the one
// function it is: the PHP tool missed smart-farmers-pos's resolve<T>, written the same in two components.
func TestAGenericFunctionWrittenTwiceIsADuplicate(t *testing.T) {
	resolve := "export function resolve<T> (value: T | (() => T)): T {\n  return typeof value === 'function' ? (value as () => T)() : value\n}\n"
	codebase := frontendtest.FromSource(t, map[string]string{"src/builder.ts": resolve, "src/item.ts": resolve})
	if found := (typescript.DuplicateFunctionDetector{}).Find(codebase); len(found) != 2 {
		t.Errorf("the generic function written twice is found %d times", len(found))
	}
}
