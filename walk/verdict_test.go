package walk

import (
	"os"
	"os/exec"
	"path/filepath"
	"slices"
	"testing"
)

func project(t *testing.T, files ...string) string {
	t.Helper()

	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	for _, file := range files {
		whole := filepath.Join(root, file)
		if err := os.MkdirAll(filepath.Dir(whole), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(whole, []byte("x\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	return root
}

func linked(t *testing.T, from, to string) {
	t.Helper()

	if err := os.Symlink(to, from); err != nil {
		t.Fatal(err)
	}
}

func TestEveryFolderAWalkMustNotEnterIsRefusedWithItsReason(t *testing.T) {
	outside := project(t, "secret.py", "lib/hidden.py")
	root := project(t,
		"src/order.py",
		".venv-like/lib.py",
		"node_modules/pkg/index.js",
		"vendor/lib.php",
		"site-packages/mod.py",
		"__pycache__/order.pyc",
		"testdata/sample.py",
		"dist/bundle.js",
		"thing.egg-info/PKG-INFO",
		"env/pyvenv.cfg",
		"app/App.csproj",
		"app/bin/out.cs",
		"app/obj/gen.cs",
		"other/bin/tool.py",
		"web/package.json",
		"web/build/out.js",
		"nested/.git/HEAD",
		"cut/.git",
		"generated/made.py",
	)
	linked(t, filepath.Join(root, "linked"), filepath.Join(outside, "lib"))
	if err := os.WriteFile(filepath.Join(root, "cut", ".git"), []byte("gitdir: /repo/.git/worktrees/cut\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	for folder, want := range map[string]Reason{
		"src":            "",
		"linked":         Link,
		".venv-like":     Hidden,
		"node_modules":   Dependency,
		"vendor":         Dependency,
		"site-packages":  Dependency,
		"__pycache__":    Dependency,
		"testdata":       Dependency,
		"dist":           Dependency,
		"thing.egg-info": Dependency,
		"env":            Virtualenv,
		"app/bin":        Build,
		"app/obj":        Build,
		"other/bin":      "",
		"web/build":      Build,
		"nested":         "",
		"cut":            Worktree,
		"generated":      Excluding,
	} {
		if got := Verdict(filepath.Join(root, folder), Under(root, []string{"generated"})); got != want {
			t.Errorf("%s: the verdict is %q, want %q", folder, got, want)
		}
	}
}

func TestAWalkNeverReadsALinkedFileOrAnythingUnderALinkedFolder(t *testing.T) {
	outside := project(t, "secret.py", "lib/hidden.py")
	root := project(t, "src/order.py")
	linked(t, filepath.Join(root, "src", "linked.py"), filepath.Join(outside, "secret.py"))
	linked(t, filepath.Join(root, "src", "lib"), filepath.Join(outside, "lib"))

	files := Files(root, Excluded{}, func(string) bool { return true })

	if want := []string{filepath.Join(root, "src", "order.py")}; !slices.Equal(files, want) {
		t.Errorf("the walk read %v, want only %v", files, want)
	}

	for _, file := range []string{filepath.Join(root, "src", "linked.py"), filepath.Join(root, "src", "lib", "hidden.py")} {
		if Reaches(root, file, Excluded{}) {
			t.Errorf("%s is said to be reached, but it is a link or lies under one", file)
		}
	}
}

func TestAWorktreeIsNeverEnteredAndANestedCloneIsReadAndNamed(t *testing.T) {
	root := project(t, "src/order.py")
	inGit(t, root, "init", "-q", "-b", "main")
	inGit(t, root, "-c", "user.email=t@t", "-c", "user.name=t", "add", "-A")
	inGit(t, root, "-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", "the project")
	inGit(t, root, "worktree", "add", "-q", "-b", "a-worker", filepath.Join(root, "work"))

	clone := filepath.Join(root, "clone")
	inGit(t, root, "clone", "-q", root, clone)

	var read []string
	for _, file := range Files(root, Excluded{}, func(string) bool { return true }) {
		rel, _ := filepath.Rel(root, file)
		read = append(read, rel)
	}

	if slices.ContainsFunc(read, func(rel string) bool { return filepath.Dir(filepath.Dir(rel)) == "work" || filepath.Dir(rel) == "work" }) {
		t.Errorf("the walk read a worktree's files: %v", read)
	}

	if !slices.Contains(read, "clone/src/order.py") {
		t.Errorf("the nested clone's source was not read: %v", read)
	}

	if found := Repositories(root, Excluded{}); !slices.Contains(found, clone) {
		t.Errorf("the nested clone is not named as a repository of its own: %v", found)
	}
}

func TestALinkedRootIsWalkedAtItsTarget(t *testing.T) {
	target := project(t, "src/order.py")
	parent := project(t)
	root := filepath.Join(parent, "root")
	linked(t, root, target)

	if files := Files(root, Excluded{}, func(string) bool { return true }); len(files) != 1 {
		t.Errorf("a root given through a link read %v, want its one file", files)
	}
}

func inGit(t *testing.T, dir string, args ...string) {
	t.Helper()

	if out, err := exec.Command("git", append([]string{"-C", dir}, args...)...).CombinedOutput(); err != nil {
		t.Fatalf("git %v: %s", args, out)
	}
}
