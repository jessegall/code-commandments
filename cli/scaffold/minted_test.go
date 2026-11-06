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
