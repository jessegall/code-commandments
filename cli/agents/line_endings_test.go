package agents

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

// TestAnInstructionsFileKeepsItsOwnLineEndings holds the one rule about a file the user owns: whatever its line
// endings are, they are still its own afterwards. A project written on Windows keeps CRLF, a project written
// anywhere else keeps LF, and neither ends up with a mixture, which is what writing our block in our own endings
// would make of it.
//
// The PHP tool flattened a project's CRLF to LF, which is why the parity recording of sync holds that file all in
// LF. This test is the right answer, so the behaviour cannot drift back to it.
func TestAnInstructionsFileKeepsItsOwnLineEndings(t *testing.T) {
	for _, held := range []struct {
		name     string
		eol      string
		document string
	}{
		{"carriage returns", "\r\n", "# Ours\r\n\r\nKeep this.\r\n"},
		{"newlines", "\n", "# Ours\n\nKeep this.\n"},
	} {
		t.Run(held.name, func(t *testing.T) {
			root := t.TempDir()
			path := filepath.Join(root, "AGENTS.md")
			if err := os.WriteFile(path, []byte(held.document), 0o644); err != nil {
				t.Fatal(err)
			}

			if err := InstructionsAt(path, root).Inject("test", "The disciplines in force here."); err != nil {
				t.Fatal(err)
			}

			written, err := os.ReadFile(path)
			if err != nil {
				t.Fatal(err)
			}
			text := string(written)

			if !strings.Contains(text, "Keep this.") {
				t.Error("what the project wrote is gone")
			}
			if !strings.Contains(text, "The disciplines in force here.") {
				t.Error("the block was not written")
			}

			crlf := strings.Count(text, "\r\n")
			lf := strings.Count(text, "\n") - crlf
			switch held.eol {
			case "\r\n":
				if lf != 0 {
					t.Errorf("%d lines end in a bare newline, so the file is mixed where the project wrote CRLF", lf)
				}
				if crlf == 0 {
					t.Error("the project's carriage returns are gone")
				}
			default:
				if crlf != 0 {
					t.Errorf("%d lines gained a carriage return the project never wrote", crlf)
				}
			}
		})
	}
}

// TestABlockAlreadyThereIsReplacedInTheFilesOwnEndings holds the same rule on the other path: a document that
// already carries the block has it REPLACED rather than appended, and the endings still survive.
func TestABlockAlreadyThereIsReplacedInTheFilesOwnEndings(t *testing.T) {
	root := t.TempDir()
	path := filepath.Join(root, "AGENTS.md")
	document := "# Ours\r\n\r\nKeep this.\r\n\r\n" + strings.ReplaceAll(
		"<!-- BEGIN: test (auto-generated, run `composer update`) -->\nold\n<!-- END: test -->\n", "\n", "\r\n")
	if err := os.WriteFile(path, []byte(document), 0o644); err != nil {
		t.Fatal(err)
	}

	if err := InstructionsAt(path, root).Inject("test", "new"); err != nil {
		t.Fatal(err)
	}

	written, err := os.ReadFile(path)
	if err != nil {
		t.Fatal(err)
	}
	text := string(written)

	if strings.Contains(text, "old") {
		t.Error("the block's old content is still there, so it was appended rather than replaced")
	}
	if lf := strings.Count(text, "\n") - strings.Count(text, "\r\n"); lf != 0 {
		t.Errorf("%d lines end in a bare newline, so replacing the block mixed the endings", lf)
	}
}
