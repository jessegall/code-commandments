package hooks

import (
	"os"
	"path/filepath"
	"testing"
)

// TestAFileEditedUnderAnotherSpellingStillSettlesItsMarks holds the settling to the file rather than to the
// spelling the harness happened to report. A mark is announced under the file as the engine read it — absolute,
// with its links resolved — while the moment names the file as the tool wrote it, which may be relative to the
// folder the moment happened in or absolute through a link. Compared as given, the two never match, so a sin
// fixed in that file keeps its mark raised for ever.
func TestAFileEditedUnderAnotherSpellingStillSettlesItsMarks(t *testing.T) {
	root := t.TempDir()
	real, err := filepath.EvalSymlinks(root)
	if err != nil {
		t.Fatal(err)
	}
	if err := os.MkdirAll(filepath.Join(real, "src"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(real, "src/Card.vue"), []byte("<template />\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	for spelling, written := range map[string]string{
		"relative to the moment's folder": "src/Card.vue",
		"absolute through the link":       filepath.Join(root, "src/Card.vue"),
		"absolute and resolved":           filepath.Join(real, "src/Card.vue"),
	} {
		data := t.TempDir()
		record := `{"src/Card.vue":{"a1":"control-flow-on-element at src/Card.vue:56"}}`
		if err := os.WriteFile(filepath.Join(data, "sins.json"), []byte(record), 0o644); err != nil {
			t.Fatal(err)
		}

		settlement := AnnouncedIn(data, Moment{Cwd: real}).Settle(real, &written, nil)

		if len(settlement.Repented) != 1 {
			t.Errorf("edited as %s (%q): %d settled, want 1", spelling, written, len(settlement.Repented))
		}
	}
}
