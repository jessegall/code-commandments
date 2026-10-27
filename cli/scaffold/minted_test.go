package scaffold

import (
	"bytes"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	_ "github.com/jessegall/code-commandments/registry"
)

// Scaffolding writes new files into the reader's own project, and cli/scaffold had no test at all: the seven
// recorded parity cases ran the built binary to see what it wrote. What it must never do is write over something
// they already have, or write anywhere at all when asked only to show.

// minted runs the command inside the project and answers its exit code and what it said.
func minted(t *testing.T, project string, args ...string) (code int, said string) {
	t.Helper()
	was, err := os.Getwd()
	if err != nil {
		t.Fatal(err)
	}
	if err := os.Chdir(project); err != nil {
		t.Fatal(err)
	}
	defer os.Chdir(was)

	var out, errs bytes.Buffer
	code, err = Command{}.Run(cli.InputOf("scaffold", args...), cli.Console{Out: &out, Err: &errs})
	if err != nil {
		t.Fatalf("the command failed: %v", err)
	}

	return code, out.String() + errs.String()
}

// aPhpProject is a project whose composer.json maps a namespace to src/, which is what scaffolding needs to know
// where a helper goes and what to call it.
func aPhpProject(t *testing.T) string {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	if err := os.MkdirAll(filepath.Join(root, "src"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, "composer.json"), []byte(`{"autoload": {"psr-4": {"Shop\\": "src/"}}}`), 0o644); err != nil {
		t.Fatal(err)
	}

	return root
}

// filesUnder are every file in the tree, by path relative to it.
func filesUnder(t *testing.T, root string) []string {
	t.Helper()
	var found []string

	err := filepath.Walk(root, func(path string, info os.FileInfo, err error) error {
		if err != nil || info.IsDir() {
			return err
		}
		relative, err := filepath.Rel(root, path)
		if err != nil {
			return err
		}
		found = append(found, relative)

		return nil
	})
	if err != nil {
		t.Fatal(err)
	}

	return found
}

// TestAProjectWithNoPsr4RootIsToldRatherThanGuessedAt holds the refusal: a helper written where no namespace maps
// would be a class nothing can autoload, so the command says what it could not resolve and writes nothing.
func TestAProjectWithNoPsr4RootIsToldRatherThanGuessedAt(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, "README.md"), []byte("# nothing here\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	before := filesUnder(t, root)

	code, said := minted(t, root)

	if code != 2 {
		t.Errorf("exits %d, want 2", code)
	}
	if !strings.Contains(said, "composer.json") {
		t.Errorf("it says %q, which does not say what it could not resolve", said)
	}
	if after := filesUnder(t, root); len(after) != len(before) {
		t.Errorf("it wrote into a project it could not read: %v", after)
	}
}

// TestAPreviewWritesNothing holds --dry-run: the reader who asked to see what would be minted gets the code on
// screen and not a single file in their tree.
func TestAPreviewWritesNothing(t *testing.T) {
	project := aPhpProject(t)
	before := filesUnder(t, project)

	code, said := minted(t, project, "--dry-run")

	if code != 0 {
		t.Fatalf("exits %d, saying %q", code, said)
	}
	if !strings.Contains(said, "would create") {
		t.Errorf("a preview says %q, which does not say what it would create", said)
	}
	if after := filesUnder(t, project); len(after) != len(before) {
		t.Errorf("a preview wrote %d files where there were %d", len(after), len(before))
	}
}

// TestWhatTheProjectAlreadyHasIsNeverWrittenOver holds the one answer that protects the reader's own work: a helper
// already there is skipped and said, never replaced with the minted one.
func TestWhatTheProjectAlreadyHasIsNeverWrittenOver(t *testing.T) {
	project := aPhpProject(t)

	if code, said := minted(t, project); code != 0 {
		t.Fatalf("the first run exits %d, saying %q", code, said)
	}

	written := filesUnder(t, project)
	var one string
	for _, path := range written {
		if strings.HasPrefix(path, "src/") {
			one = path

			break
		}
	}
	if one == "" {
		t.Skip("this project mints nothing into src/, so there is nothing to write over")
	}

	mine := "<?php\n\n// mine, and not to be written over\n"
	if err := os.WriteFile(filepath.Join(project, one), []byte(mine), 0o644); err != nil {
		t.Fatal(err)
	}

	code, said := minted(t, project)

	if code != 0 {
		t.Fatalf("the second run exits %d, saying %q", code, said)
	}

	after, err := os.ReadFile(filepath.Join(project, one))
	if err != nil {
		t.Fatal(err)
	}
	if string(after) != mine {
		t.Errorf("%s was written over:\n%s", one, after)
	}
}

// TestASinNobodyHasMintsNothingAndSaysSo holds --sin naming a rule that is not there: nothing is written, and the
// reader is not left thinking a helper was minted for a name they mistyped.
func TestASinNobodyHasMintsNothingAndSaysSo(t *testing.T) {
	project := aPhpProject(t)
	before := filesUnder(t, project)

	code, said := minted(t, project, "--sin=zzz-matches-nothing")

	if code == 0 && len(filesUnder(t, project)) != len(before) {
		t.Error("a sin nobody has minted something")
	}
	if strings.TrimSpace(said) == "" {
		t.Error("it said nothing at all about a name that matched nothing")
	}
}
