package hooks

import (
	"os"
	"path/filepath"
	"slices"
	"strings"
	"testing"
)

// TestASinOfAFileThatIsGoneIsRepented holds a shell deletion to what an edit would say: every sin announced for a
// file that no longer exists is repented, and a file still there keeps what was announced for it.
func TestASinOfAFileThatIsGoneIsRepented(t *testing.T) {
	root, data := t.TempDir(), t.TempDir()
	if err := os.MkdirAll(filepath.Join(root, "src"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, "src/Here.php"), []byte("<?php\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	record := `{"src/Gone.php":{"a1":"array-bag at src/Gone.php:3"},"src/Here.php":{"b2":"array-bag at src/Here.php:2"}}`
	if err := os.WriteFile(filepath.Join(data, "sins.json"), []byte(record), 0o644); err != nil {
		t.Fatal(err)
	}

	found, repented := AnnouncedIn(data).Settle(root, nil, nil)
	if len(found) != 0 || !slices.Equal(repented, []string{"array-bag at src/Gone.php:3"}) {
		t.Errorf("found %v, repented %v", found, repented)
	}

	kept, _ := os.ReadFile(filepath.Join(data, "sins.json"))
	if strings.Contains(string(kept), "Gone.php") || !strings.Contains(string(kept), "src/Here.php") {
		t.Errorf("the record kept %s", kept)
	}
}
