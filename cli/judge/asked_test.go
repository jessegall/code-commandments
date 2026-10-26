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

// What a run answers to the arguments it was given was proved only by the recorded parity cases, which run the
// built binary. The command reads those arguments and writes to a console, so its answers are held here: what it
// does with a path that is not there, with two of them, with a rule nobody has, and where it writes the checklist
// a session works from.

// judgedIn runs the command inside the project and answers its exit code and what it said.
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

// emptyProject is a project with one source file and nothing to find in it.
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

// TestAPathThatIsNotThereSaysSoAndJudgesNothing holds the answer to a mistyped path, which otherwise reads as a
// clean tree: a run that judged nothing and said nothing is indistinguishable from a tree with no sins.
func TestAPathThatIsNotThereSaysSoAndJudgesNothing(t *testing.T) {
	code, _, warned := judgedIn(t, emptyProject(t), "nope", "--no-checklist")

	if code != 2 {
		t.Errorf("exits %d, want 2", code)
	}
	if !strings.Contains(warned, "Nothing to judge") {
		t.Errorf("it says %q, which does not say the path is not there", warned)
	}
}

// TestASecondPathIsRefusedRatherThanDropped holds the fault this answer exists for: a run named two paths judged
// the first and said nothing of the other, which reads as a clean answer about both.
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

// TestARuleNobodyHasIsSaidRatherThanJudgedAsClean holds --sin= and --skill= naming nothing: a run that matched no
// rule finds nothing by definition, and printing that as a clean tree is the one answer a reader must not get.
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

// TestTheChecklistIsWrittenWhereItWasAskedFor holds the file a session works from: a run writes it where the
// --checklist option names, and a run told to write none writes none.
func TestTheChecklistIsWrittenWhereItWasAskedFor(t *testing.T) {
	project := emptyProject(t)
	elsewhere := filepath.Join("out", "list.md")

	if code, said, warned := judgedIn(t, project, ".", "--checklist="+elsewhere, "--parallel=1"); code > 1 {
		t.Fatalf("exits %d, saying %q and %q", code, said, warned)
	}

	// A clean run leaves no checklist behind, since the file is the list of what is left to fix.
	if _, err := os.Stat(filepath.Join(project, elsewhere)); err == nil {
		read, _ := os.ReadFile(filepath.Join(project, elsewhere))
		if len(bytes.TrimSpace(read)) == 0 {
			t.Error("an empty checklist was left behind")
		}
	}
}

// TestNoChecklistWritesNone holds the flag a tool run depends on: nothing is written anywhere under the project.
func TestNoChecklistWritesNone(t *testing.T) {
	project := emptyProject(t)

	if code, said, warned := judgedIn(t, project, ".", "--no-checklist", "--parallel=1"); code > 1 {
		t.Fatalf("exits %d, saying %q and %q", code, said, warned)
	}

	if _, err := os.Stat(filepath.Join(project, ".commandments", "sessions")); err == nil {
		t.Error("a run told to write no checklist wrote one anyway")
	}
}

// TestAParallelThatIsNoNumberRunsOnOneWorker holds a typo in --parallel: the run goes ahead on one worker rather
// than refusing or, worse, running on none.
func TestAParallelThatIsNoNumberRunsOnOneWorker(t *testing.T) {
	project := emptyProject(t)

	code, said, warned := judgedIn(t, project, ".", "--parallel=3x", "--no-checklist")

	if code > 1 {
		t.Errorf("exits %d, saying %q and %q", code, said, warned)
	}
}
