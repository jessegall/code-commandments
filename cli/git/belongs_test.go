package git

import (
	"os"
	"os/exec"
	"path/filepath"
	"testing"
)

func project(t *testing.T) string {
	t.Helper()

	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	wroteFile(t, filepath.Join(root, "src", "made.py"), "class Made:\n    pass\n")

	for _, command := range [][]string{
		{"init", "-q", "-b", "main"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "add", "-A"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", "the project"},
	} {
		if out, err := exec.Command("git", append([]string{"-C", root}, command...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", command, out)
		}
	}

	return root
}

func wroteFile(t *testing.T, file, text string) {
	t.Helper()

	if err := os.MkdirAll(filepath.Dir(file), 0o755); err != nil {
		t.Fatal(err)
	}

	if err := os.WriteFile(file, []byte(text), 0o644); err != nil {
		t.Fatal(err)
	}
}

func TestAWorktreeOfTheProjectBelongsToIt(t *testing.T) {
	root := project(t)
	worktree := filepath.Join(root, ".journal", "worktrees", "development", "worker-1")

	if out, err := exec.Command("git", "-C", root, "worktree", "add", "-q", "-b", "feature/one", worktree).CombinedOutput(); err != nil {
		t.Fatalf("git worktree add: %s", out)
	}

	if !BelongsTo(worktree, root) {
		t.Error("a worktree cut inside the project is not read as one of its checkouts")
	}

	beside := filepath.Join(filepath.Dir(root), filepath.Base(root)+"-worker-2")
	if out, err := exec.Command("git", "-C", root, "worktree", "add", "-q", "-b", "feature/two", beside).CombinedOutput(); err != nil {
		t.Fatalf("git worktree add: %s", out)
	}

	if !BelongsTo(beside, root) {
		t.Error("a worktree cut beside the project is not read as one of its checkouts")
	}

	if ProjectRoot(filepath.Join(beside, "src", "made.py")) != root {
		t.Errorf("the project of a file in the worktree beside the project is %s", ProjectRoot(beside))
	}
}

func TestARepositoryOfItsOwnInsideTheProjectIsNotOneOfItsCheckouts(t *testing.T) {
	root := project(t)
	nested := filepath.Join(root, "vendor", "other")

	wroteFile(t, filepath.Join(nested, "src", "other.py"), "class Other:\n    pass\n")

	for _, command := range [][]string{
		{"init", "-q", "-b", "main"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "add", "-A"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", "its own"},
	} {
		if out, err := exec.Command("git", append([]string{"-C", nested}, command...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", command, out)
		}
	}

	if Root(filepath.Join(nested, "src", "other.py")) != nested {
		t.Fatalf("git says the nested repository's own file is in %s", Root(nested))
	}

	if BelongsTo(nested, root) {
		t.Error("a repository of its own inside the project is read as one of its checkouts, so a lane would be keyed to the wrong project")
	}
}

func TestASubmoduleIsNotOneOfTheProjectsCheckouts(t *testing.T) {
	root := project(t)
	other := project(t)

	added := exec.Command("git", "-C", root, "-c", "protocol.file.allow=always",
		"-c", "user.email=t@t", "-c", "user.name=t", "submodule", "add", "-q", other, "lib/other")
	if out, err := added.CombinedOutput(); err != nil {
		t.Skipf("this git will not add a local submodule: %s", out)
	}

	inside := filepath.Join(root, "lib", "other")

	gitdir := gitDirOf(inside)
	if gitdir == "" {
		t.Fatalf("the submodule's .git does not name a gitdir at all")
	}

	if filepath.Base(filepath.Dir(gitdir)) != "modules" {
		t.Fatalf("the submodule's gitdir is %s, which is not in the parent's modules folder", gitdir)
	}

	if BelongsTo(inside, root) {
		t.Error("a submodule is read as one of the project's checkouts, so a lane would be keyed to the wrong project")
	}
}
