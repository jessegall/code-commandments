package source

import (
	"os"
	"path/filepath"
	"testing"
)

// A language's files are found by every extension it is written in, so a TypeScript project of .tsx and .js files
// reads as TypeScript, not only one of .ts files.
func TestALanguagesFilesAreFoundByEveryExtensionItIsWrittenIn(t *testing.T) {
	root := t.TempDir()
	for _, name := range []string{"cart.tsx", "prices.js", "checkout.vue", "readme.md"} {
		if err := os.WriteFile(filepath.Join(root, name), []byte("x\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	if found := FilesIn(root, TypeScript, Excluded{}); len(found) != 2 {
		t.Errorf("TypeScript's files are %v, not cart.tsx and prices.js", found)
	}
}
