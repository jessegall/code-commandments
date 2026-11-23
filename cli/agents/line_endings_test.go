package agents

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

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

			if err := InstructionsAt(path, root).Hold("The disciplines in force here.", "test"); err != nil {
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
				t.Error("the line was not written")
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

func TestABlockAlreadyThereIsReplacedInTheFilesOwnEndings(t *testing.T) {
	root := t.TempDir()
	path := filepath.Join(root, "AGENTS.md")
	document := "# Ours\r\n\r\nKeep this.\r\n\r\n" + strings.ReplaceAll(
		"<!-- BEGIN: test (auto-generated, run `composer update`) -->\nold\n<!-- END: test -->\n", "\n", "\r\n")
	if err := os.WriteFile(path, []byte(document), 0o644); err != nil {
		t.Fatal(err)
	}

	if err := InstructionsAt(path, root).Hold("new", "test"); err != nil {
		t.Fatal(err)
	}

	written, err := os.ReadFile(path)
	if err != nil {
		t.Fatal(err)
	}
	text := string(written)

	if strings.Contains(text, "old") {
		t.Error("the former block's content is still there, so the line was added beside it")
	}
	if lf := strings.Count(text, "\n") - strings.Count(text, "\r\n"); lf != 0 {
		t.Errorf("%d lines end in a bare newline, so replacing the former block mixed the endings", lf)
	}
}
