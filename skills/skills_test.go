package skills

import (
	"io/fs"
	"os"
	"path/filepath"
	"testing"
)

func TestAnInstalledSkillsFolderIsReadInPlaceOfTheEmbeddedCopy(t *testing.T) {
	folder := t.TempDir()
	lesson := filepath.Join(folder, "commandments", "shared", "duplication", "SKILL.md")
	if err := os.MkdirAll(filepath.Dir(lesson), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(lesson, []byte("loose"), 0o644); err != nil {
		t.Fatal(err)
	}

	read, err := fs.ReadFile(In(folder), "commandments/shared/duplication/SKILL.md")
	if err != nil || string(read) != "loose" {
		t.Fatalf("the loose lesson was not read: %q, %v", read, err)
	}
}

func TestTheEmbeddedCopyServesWhereNoSkillsFolderIsInstalled(t *testing.T) {
	for _, folder := range []string{"", t.TempDir()} {
		if _, err := fs.Stat(In(folder), "commandments/shared/duplication/SKILL.md"); err != nil {
			t.Errorf("folder %q: the embedded lesson is not served: %v", folder, err)
		}
	}
}
