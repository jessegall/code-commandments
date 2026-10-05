package scan

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/source"
)

// TestAFileIsJudgedAsItsFolderWouldBe holds a file's judged path to the one a scan of its folder gives it, so a rule
// reading `**/routes/**` finds the same file whether it was tried on the file or on its folder.
func TestAFileIsJudgedAsItsFolderWouldBe(t *testing.T) {
	routes := filepath.Join(t.TempDir(), "routes")
	if err := os.MkdirAll(routes, 0o755); err != nil {
		t.Fatal(err)
	}
	file := filepath.Join(routes, "api.php")
	if err := os.WriteFile(file, []byte("<?php\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	for _, scanned := range []string{routes, file} {
		codebase, err := Walk([]string{scanned}, source.Excluded{}).Load()
		if err != nil {
			t.Fatal(err)
		}
		if got := codebase.Files()[0].Judged(); got != "routes/api.php" {
			t.Errorf("a scan of %s judges the file as %q", filepath.Base(scanned), got)
		}
	}
}
