package judge

import (
	"bytes"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/engine"
)

func judgedFromTheWholeTree(t *testing.T, folder, held string, args ...string) string {
	t.Helper()
	chdir(t, folder)

	command := Command{Reading: func(scan.Sources) (*engine.Codebase, error) {
		return scan.Walk([]string{held + "/src"}, source.Excluded{}).Load()
	}}

	var out, errs bytes.Buffer
	if _, err := command.Run(cli.InputOf("judge", args...), cli.Console{Out: &out, Err: &errs}); err != nil {
		t.Fatalf("%v\n%s%s", err, out.String(), errs.String())
	}

	return out.String() + errs.String()
}

func TestAJudgeGivenAFolderReportsOnlyThatFolderWhenTheWholeTreeAnswersIt(t *testing.T) {
	project := projectThatExcludes(t)
	writeInto(t, project, "src/given/inside.py", sinfulClass)

	said := judgedFromTheWholeTree(t, project, project, "src/given", "--no-checklist", "--parallel=1")

	if !strings.Contains(said, "inside.py") {
		t.Errorf("the sin inside the given folder was not named:\n%s", said)
	}
	if strings.Contains(said, "order.py") {
		t.Errorf("the run named a file outside the folder it was given:\n%s", said)
	}
}

func TestAWorktreesFolderKeepsTheFilesTheTreeHoldsUnderTheProject(t *testing.T) {
	project := projectThatExcludes(t)
	worktree := insideTheProject(t, project)
	writeInto(t, project, "src/given/inside.py", sinfulClass)
	writeInto(t, worktree, "src/given/inside.py", sinfulClass)

	said := judgedFromTheWholeTree(t, worktree, project, "src/given", "--no-checklist", "--parallel=1")

	if !strings.Contains(said, "inside.py") {
		t.Errorf("the sin the tree holds under the project's own path was dropped:\n%s", said)
	}
	if strings.Contains(said, "order.py") {
		t.Errorf("the run named a file outside the folder it was given:\n%s", said)
	}
}

func TestAJudgeGivenNoFolderReportsTheWholeTree(t *testing.T) {
	project := projectThatExcludes(t)
	writeInto(t, project, "src/given/inside.py", sinfulClass)

	said := judgedFromTheWholeTree(t, project, project, "--no-checklist", "--parallel=1")

	for _, named := range []string{"inside.py", "order.py"} {
		if !strings.Contains(said, named) {
			t.Errorf("a run with no folder left out %s:\n%s", named, said)
		}
	}
}

func TestALocationOutsideTheFolderKeepsItsWholePath(t *testing.T) {
	report := NewReport("/project/src", nil, nil, nil, Skipped{})

	if got := report.relative("/elsewhere/twin.py:3"); got != "/elsewhere/twin.py:3" {
		t.Errorf("a location outside the folder printed as %q", got)
	}
}
