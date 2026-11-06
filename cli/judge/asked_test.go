package judge

import (
	"bytes"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	_ "github.com/jessegall/code-commandments/registry"
)

func judgedIn(t *testing.T, project string, args ...string) (code int, said string, warned string) {
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
	code, err = Command{}.Run(cli.InputOf("judge", args...), cli.Console{Out: &out, Err: &errs})
	if err != nil {
		t.Fatalf("the run failed: %v", err)
	}

	return code, out.String(), errs.String()
}

func emptyProject(t *testing.T) string {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, "cart.php"), []byte("<?php\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	return root
}

func TestAPathThatIsNotThereSaysSoAndJudgesNothing(t *testing.T) {
	code, _, warned := judgedIn(t, emptyProject(t), "nope", "--no-checklist")

	if code != 2 {
		t.Errorf("exits %d, want 2", code)
	}
	if !strings.Contains(warned, "Nothing to judge") {
		t.Errorf("it says %q, which does not say the path is not there", warned)
	}
}

func TestASecondPathIsRefusedRatherThanDropped(t *testing.T) {
	project := emptyProject(t)

	code, _, warned := judgedIn(t, project, ".", "elsewhere", "--no-checklist")

	if code != 2 {
		t.Errorf("exits %d, want 2", code)
	}
	if !strings.Contains(warned, "elsewhere") {
		t.Errorf("it says %q, which does not name the path that would never be judged", warned)
	}
}

func TestARuleNobodyHasIsSaidRatherThanJudgedAsClean(t *testing.T) {
	project := emptyProject(t)

	for name, flag := range map[string]string{
		"a sin nobody has":   "--sin=zzz-matches-nothing",
		"a skill nobody has": "--skill=zzz-matches-nothing",
	} {
		t.Run(name, func(t *testing.T) {
			code, said, warned := judgedIn(t, project, ".", flag, "--no-checklist")

			if code == 0 {
				t.Errorf("exits 0, as if the tree were clean:\n%s", said)
			}
			if !strings.Contains(warned, "No detector matched") {
				t.Errorf("it says %q, which does not say no rule was matched", warned)
			}
		})
	}
}

func TestTheChecklistIsWrittenWhereItWasAskedFor(t *testing.T) {
	project := emptyProject(t)
	elsewhere := filepath.Join("out", "list.md")

	if code, said, warned := judgedIn(t, project, ".", "--checklist="+elsewhere, "--parallel=1"); code > 1 {
		t.Fatalf("exits %d, saying %q and %q", code, said, warned)
	}

	if _, err := os.Stat(filepath.Join(project, elsewhere)); err == nil {
		read, _ := os.ReadFile(filepath.Join(project, elsewhere))
		if len(bytes.TrimSpace(read)) == 0 {
			t.Error("an empty checklist was left behind")
		}
	}
}

func TestNoChecklistWritesNone(t *testing.T) {
	project := emptyProject(t)

	if code, said, warned := judgedIn(t, project, ".", "--no-checklist", "--parallel=1"); code > 1 {
		t.Fatalf("exits %d, saying %q and %q", code, said, warned)
	}

	if _, err := os.Stat(filepath.Join(project, ".commandments", "sessions")); err == nil {
		t.Error("a run told to write no checklist wrote one anyway")
	}
}

func TestAParallelThatIsNoNumberRunsOnOneWorker(t *testing.T) {
	project := emptyProject(t)

	code, said, warned := judgedIn(t, project, ".", "--parallel=3x", "--no-checklist")

	if code > 1 {
		t.Errorf("exits %d, saying %q and %q", code, said, warned)
	}
}
