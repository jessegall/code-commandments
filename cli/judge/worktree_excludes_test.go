package judge

import (
	"bytes"
	"os"
	"os/exec"
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/engine"
)

const sinfulClass = `class Sinful:
    def total(self):
        """Says the total."""
        return 0
`

func TestAWorktreeReadsOnlyWhatItsCheckoutWouldInsideItsOwnFolder(t *testing.T) {
	for name, each := range map[string]struct {
		cutTo func(t *testing.T, project string) string
		asked bool
	}{
		"a worktree inside the project, run from its folder": {cutTo: insideTheProject},
		"a worktree inside the project, asked for by a path": {cutTo: insideTheProject, asked: true},
		"a worktree elsewhere, run from its folder":          {cutTo: elsewhereOnDisk},
		"a worktree elsewhere, asked for by a path":          {cutTo: elsewhereOnDisk, asked: true},
	} {
		t.Run(name, func(t *testing.T) {
			project := projectThatExcludes(t)
			worktree := each.cutTo(t, project)

			read, said := judgedAt(t, worktree, each.asked, "--no-checklist", "--parallel=1")

			if want := []string{"src/__init__.py", "src/order.py"}; !slices.Equal(withinFolder(t, read, worktree), want) {
				t.Errorf("the worktree read %v, want exactly %v", read, want)
			}

			if !strings.Contains(said, "order.py") {
				t.Errorf("the sin in the worktree's own source was not named:\n%s", said)
			}

			for _, never := range []string{"bundle.py", "made.py", "test_order.py", "only_in_the_project.py"} {
				if strings.Contains(said, never) {
					t.Errorf("the run named %s, which the project excludes or which lies outside the worktree:\n%s", never, said)
				}
			}
		})
	}
}

func TestAWorktreesChangesLeaveOutTheFoldersTheProjectExcludes(t *testing.T) {
	project := projectThatExcludes(t)
	worktree := insideTheProject(t, project)

	for _, changed := range []string{"src/order.py", "src/web/dist/bundle.py", "src/generated/made.py", "tests/test_order.py"} {
		writeInto(t, worktree, changed, sinfulClass+"\n\n"+strings.ReplaceAll(sinfulClass, "Sinful", "AlsoSinful"))
	}

	read, said := judgedAt(t, worktree, false, "--changes", "--no-checklist", "--parallel=1")

	for _, file := range withinFolder(t, read, worktree) {
		if strings.Contains(file, "/dist/") || strings.Contains(file, "generated") || strings.HasPrefix(file, "tests/") {
			t.Errorf("the changes run read %s, which the project excludes", file)
		}
	}

	if !strings.Contains(said, "order.py") {
		t.Errorf("the changed source was not judged:\n%s", said)
	}

	for _, never := range []string{"bundle.py", "made.py", "test_order.py"} {
		if strings.Contains(said, never) {
			t.Errorf("the changes run named %s, which the project excludes:\n%s", never, said)
		}
	}
}

func TestTheCheckoutItselfReadsWhatItsWorktreeDoesPlusItsOwnFiles(t *testing.T) {
	project := projectThatExcludes(t)

	read, _ := judgedAt(t, project, false, "--no-checklist", "--parallel=1")

	if want := []string{"src/__init__.py", "src/only_in_the_project.py", "src/order.py"}; !slices.Equal(withinFolder(t, read, project), want) {
		t.Errorf("the checkout read %v, want exactly %v", read, want)
	}
}

func projectThatExcludes(t *testing.T) string {
	t.Helper()

	project := sinfulProject(t)

	writeInto(t, project, ".commandments/config.json", `{"paths": ["src"], "exclude": ["**/dist", "src/generated", "tests"], "detectors": ["DocstringInMethodDetector"]}`)
	writeInto(t, project, "src/web/dist/bundle.py", sinfulClass)
	writeInto(t, project, "src/generated/made.py", sinfulClass)
	writeInto(t, project, "tests/test_order.py", sinfulClass)

	inGit(t, project, "init", "-q", "-b", "main")
	inGit(t, project, "add", "-A")
	inGit(t, project, "-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", "the project")

	writeInto(t, project, "src/only_in_the_project.py", sinfulClass)

	return project
}

func insideTheProject(t *testing.T, project string) string {
	t.Helper()

	worktree := filepath.Join(project, ".journal", "worktrees", "worker-1")
	inGit(t, project, "worktree", "add", "-q", "-b", "a-worker", worktree)

	return worktree
}

func elsewhereOnDisk(t *testing.T, project string) string {
	t.Helper()

	parent, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	worktree := filepath.Join(parent, "worker-1")
	inGit(t, project, "worktree", "add", "-q", "-b", "a-worker", worktree)

	return worktree
}

func judgedAt(t *testing.T, folder string, asked bool, args ...string) ([]string, string) {
	t.Helper()

	var read []string

	command := Command{Reading: func(sources scan.Sources) (*engine.Codebase, error) {
		read = sources.Files(source.Python)

		return sources.Load()
	}}

	if asked {
		command.In = folder

		elsewhere, err := filepath.EvalSymlinks(t.TempDir())
		if err != nil {
			t.Fatal(err)
		}

		chdir(t, elsewhere)
	} else {
		chdir(t, folder)
	}

	var out, errs bytes.Buffer

	if _, err := command.Run(cli.InputOf("judge", args...), cli.Console{Out: &out, Err: &errs}); err != nil {
		t.Fatalf("%v\n%s%s", err, out.String(), errs.String())
	}

	return read, out.String() + errs.String()
}

func withinFolder(t *testing.T, files []string, folder string) []string {
	t.Helper()

	var relative []string

	for _, file := range files {
		inside, under := strings.CutPrefix(file, folder+"/")
		if !under {
			t.Errorf("the run read %s, outside %s", file, folder)

			continue
		}

		relative = append(relative, inside)
	}

	slices.Sort(relative)

	return relative
}

func writeInto(t *testing.T, root, path, text string) {
	t.Helper()

	whole := filepath.Join(root, path)
	if err := os.MkdirAll(filepath.Dir(whole), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(whole, []byte(text), 0o644); err != nil {
		t.Fatal(err)
	}
}

func inGit(t *testing.T, root string, args ...string) {
	t.Helper()

	if out, err := exec.Command("git", append([]string{"-C", root}, args...)...).CombinedOutput(); err != nil {
		t.Fatalf("git %v: %s", args, out)
	}
}
