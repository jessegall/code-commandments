package walk

import (
	"crypto/sha256"
	"os"
	"path/filepath"
	"testing"
)

// A file is read for its print once however many ask, and again once it changed.
func TestAFileIsPrintedOnceUntilItChanges(t *testing.T) {
	file := filepath.Join(t.TempDir(), "cart.py")
	if err := os.WriteFile(file, []byte("total = 1\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	first, err := Print(file)
	if err != nil || first != sha256.Sum256([]byte("total = 1\n")) {
		t.Fatalf("the first print of cart.py is %x, %v", first, err)
	}

	if err := os.Chmod(file, 0o000); err != nil {
		t.Fatal(err)
	}
	if again, err := Print(file); err != nil || again != first {
		t.Errorf("an unchanged cart.py was read again for its print: %x, %v", again, err)
	}

	if err := os.Chmod(file, 0o644); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(file, []byte("total = 22\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	if now, _ := Print(file); now != sha256.Sum256([]byte("total = 22\n")) {
		t.Errorf("a changed cart.py printed %x, not the print of what it holds now", now)
	}
}
