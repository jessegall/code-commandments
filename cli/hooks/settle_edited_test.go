package hooks

import (
	"os"
	"path/filepath"
	"testing"
)

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

		settlement := AnnouncedIn(data, Moment{Cwd: real}).Settle(real, []string{written}, nil)

		if len(settlement.Repented) != 1 {
			t.Errorf("edited as %s (%q): %d settled, want 1", spelling, written, len(settlement.Repented))
		}
	}
}

func TestAFileFixedByAShellCommandStillSettlesItsMarks(t *testing.T) {
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

	data := t.TempDir()
	record := `{"src/Card.vue":{"a1":"control-flow-on-element at src/Card.vue:56"}}`
	if err := os.WriteFile(filepath.Join(data, "sins.json"), []byte(record), 0o644); err != nil {
		t.Fatal(err)
	}

	settlement := AnnouncedIn(data, Moment{Cwd: real}).Settle(real, []string{filepath.Join(real, "src/Card.vue")}, nil)

	if len(settlement.Repented) != 1 {
		t.Fatalf("%d settled, want 1: the file was judged and holds the sin no longer", len(settlement.Repented))
	}
}
