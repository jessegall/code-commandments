package scan

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/source"
)

// TestAGeneratedFileIsLeftUnreadAndTheRestIsWalked holds the walk to leaving out a file of more source than a tree
// can carry, a minified bundle, while every other file is still walked.
func TestAGeneratedFileIsLeftUnreadAndTheRestIsWalked(t *testing.T) {
	root := t.TempDir()
	if err := os.WriteFile(filepath.Join(root, "bundle.ts"), []byte(strings.Repeat("export const a = 1;\n", largestSource/19)), 0o644); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, "page.ts"), []byte("export const page = 1;\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	sources := Walk([]string{root}, source.Excluded{})

	if got := sources.Count(source.TypeScript); got != 1 {
		t.Errorf("the walk keeps %d TypeScript files", got)
	}
	if len(sources.tooLarge) != 1 || filepath.Base(sources.tooLarge[0]) != "bundle.ts" {
		t.Errorf("the walk leaves out %v", sources.tooLarge)
	}
}
