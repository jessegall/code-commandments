package scope

import (
	"os"
	"os/exec"
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/cli/workspace"
)

// project is a repository with a committed main, then a branch that changes one file and adds another,
// and an uncommitted edit on top.
func project(t *testing.T) string {
	t.Helper()
	root, _ := filepath.EvalSymlinks(t.TempDir())

	write(t, root, "src/Kept.php", "<?php class Kept {}")
	write(t, root, "src/Branched.php", "<?php class Branched {}")
	write(t, root, "src/Edited.vue", "<template/>")
	write(t, root, "notes.md", "not source")
	run(t, root, "init", "-q", "-b", "main")
	run(t, root, "add", ".")
	run(t, root, "commit", "-q", "-m", "start")
	run(t, root, "checkout", "-q", "-b", "feature")
	write(t, root, "src/Branched.php", "<?php class Branched { }")
	write(t, root, "src/Added.ts", "export {}")
	run(t, root, "add", ".")
	run(t, root, "commit", "-q", "-m", "branch")
	write(t, root, "src/Edited.vue", "<template><div/></template>")
	write(t, root, "src/Untracked.py", "x = 1")
	write(t, root, "notes.md", "changed, still not source")

	return root
}

func TestEachFlagNarrowsToItsFiles(t *testing.T) {
	root := project(t)
	space := workspace.At(root, "s")
	write(t, root, ".commandments/sessions/"+workspace.KeyFor("s")+"/sins/sins.md", "- [ ] `src/Kept.php:3` — x\n- [ ] `src/Kept.php:9` — y\n")

	was, _ := os.Getwd()
	os.Chdir(root)
	defer os.Chdir(was)

	for flags, want := range map[string][]string{
		"--changes":         {"src/Edited.vue", "src/Untracked.py"},
		"--branch":          {"src/Added.ts", "src/Branched.php", "src/Edited.vue", "src/Untracked.py"},
		"--branch=feature":  {"src/Edited.vue", "src/Untracked.py"},
		"--repent":          {"src/Kept.php"},
		"--repent=nowhere?": nil,
	} {
		scope, err := FromArgs([]string{flags}, root, space, nil)

		if want == nil {
			if !IsUnavailable(err) {
				t.Errorf("%s: err %v", flags, err)
			}

			continue
		}

		if err != nil {
			t.Fatalf("%s: %v", flags, err)
		}

		if got := relative(root, scope.Files()); !slices.Equal(got, want) {
			t.Errorf("%s: %v", flags, got)
		}
	}
}

func TestNoFlagTakesEveryFileLessTheFrozenAndTheExcluded(t *testing.T) {
	root := project(t)
	write(t, root, "src/Frozen.php", "<?php\n#[Frozen]\nclass Frozen {}")
	write(t, root, "generated/G.php", "<?php class G {}")

	scope, err := FromArgs(nil, root, workspace.At(root, "s"), []string{"generated"})

	switch {
	case err != nil:
		t.Fatal(err)
	case scope.IsScoped():
		t.Error("scoped without a flag")
	case !scope.Includes(root + "/src/Kept.php"):
		t.Error("an ordinary file is out")
	case scope.Includes(root + "/src/Frozen.php"):
		t.Error("a frozen file is in")
	case scope.Includes(root + "/generated/G.php"):
		t.Error("an excluded file is in")
	}
}

func TestAScopeThatCannotBeWorkedOutSaysWhy(t *testing.T) {
	outside := t.TempDir()
	root := project(t)

	for _, c := range []struct {
		args []string
		path string
		want string
	}{
		{[]string{"--changes"}, outside, "Not a git repository (or git unavailable): " + outside},
		{[]string{"--branch=nope"}, root, "Base ref 'nope' not found: " + root},
		{[]string{"--repent=20260101"}, root, "No checklist for --repent=20260101 (looked for " + workspace.At(root, "s").ChecklistArchive("20260101") + ")."},
	} {
		if _, err := FromArgs(c.args, c.path, workspace.At(root, "s"), nil); err == nil || err.Error() != c.want {
			t.Errorf("%v: %v", c.args, err)
		}
	}
}

func TestACrossFileFixIsPermittedOnlyWhenEveryFileItTouchesIsATarget(t *testing.T) {
	root := project(t)
	scope := RestrictedTo([]string{root + "/src/Kept.php"})

	if !scope.Permits([]string{root + "/src/Kept.php", root + "/src/Gone.php"}) || scope.Permits([]string{root + "/src/Added.ts"}) {
		t.Error("permits")
	}
}

func relative(root string, files map[string]bool) []string {
	var paths []string

	for file := range files {
		path, _ := filepath.Rel(root, file)
		paths = append(paths, path)
	}

	slices.Sort(paths)

	return paths
}

func write(t *testing.T, root, file, contents string) {
	t.Helper()
	os.MkdirAll(filepath.Dir(filepath.Join(root, file)), 0o755)

	if err := os.WriteFile(filepath.Join(root, file), []byte(contents), 0o644); err != nil {
		t.Fatal(err)
	}
}

func run(t *testing.T, dir string, args ...string) {
	t.Helper()

	command := exec.Command("git", append([]string{"-C", dir, "-c", "user.email=t@t", "-c", "user.name=t"}, args...)...)

	if out, err := command.CombinedOutput(); err != nil {
		t.Fatalf("git %v: %v\n%s", args, err, out)
	}
}
