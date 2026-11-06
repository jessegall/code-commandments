package git_test

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/git"
)

const flag = "--no-optional-locks"

func TestAStatusReadNeverRewritesTheIndex(t *testing.T) {
	root := repository(t)
	stale(t, root)

	before := indexOf(t, root)

	git.Status(root)

	if !os.SameFile(before, indexOf(t, root)) {
		t.Fatal("reading the working tree rewrote .git/index, so it took the index lock a commit beside it needs")
	}
}

func TestGitRewritesTheIndexWithoutTheFlag(t *testing.T) {
	root := repository(t)
	stale(t, root)

	before := indexOf(t, root)

	if _, err := exec.Command("git", "-C", root, "status", "--porcelain").Output(); err != nil {
		t.Fatalf("git status: %v", err)
	}

	if os.SameFile(before, indexOf(t, root)) {
		t.Fatal("git left the index alone without the flag, so this package's reads are no longer what could take the lock")
	}
}

func TestEveryReadCarriesTheFlag(t *testing.T) {
	outside := t.TempDir()
	record := recordingGit(t)

	readers := map[string]func(){
		"Root":              func() { git.Root(outside) },
		"ProjectRoot":       func() { git.ProjectRoot(outside) },
		"Worktrees":         func() { git.Worktrees(outside) },
		"Head":              func() { git.Head(outside) },
		"CurrentBranch":     func() { git.CurrentBranch(outside) },
		"Status":            func() { git.Status(outside) },
		"ChangedVsHead":     func() { git.ChangedVsHead(outside) },
		"MergeBase":         func() { git.MergeBase(outside, "main") },
		"ChangedVsBranch":   func() { git.ChangedVsBranch(outside, "main") },
		"ChangedOn":         func() { git.ChangedOn(outside, "main", "HEAD") },
		"ChangedLinesOf":    func() { git.ChangedLinesOf(outside, "a.go") },
		"ChangedLinesSince": func() { git.ChangedLinesSince(outside, "main", "a.go") },
	}

	for name, read := range readers {
		t.Run(name, func(t *testing.T) {
			git.Forgets()

			calls := record(read)
			if len(calls) == 0 {
				t.Fatalf("%s asked git nothing, so this case proves nothing about it", name)
			}

			for _, call := range calls {
				if !strings.HasPrefix(call, flag+" ") {
					t.Errorf("%s ran git without %s: git %s", name, flag, call)
				}
			}
		})
	}
}

func TestNothingElseRunsGitItself(t *testing.T) {
	funnel := filepath.Join("git", "git.go")

	err := filepath.WalkDir("..", func(path string, entry os.DirEntry, err error) error {
		if err != nil || entry.IsDir() || !strings.HasSuffix(path, ".go") {
			return err
		}

		if strings.HasSuffix(path, funnel) || strings.HasSuffix(path, "_test.go") {
			return nil
		}

		source, err := os.ReadFile(path)
		if err != nil {
			return err
		}

		for number, line := range strings.Split(string(source), "\n") {
			if strings.Contains(line, `exec.Command("git"`) {
				t.Errorf("%s:%d runs git itself; ask the git package, which keeps every read off the index lock", path, number+1)
			}
		}

		return nil
	})
	if err != nil {
		t.Fatalf("reading the commands: %v", err)
	}
}

func repository(t *testing.T) string {
	t.Helper()

	root := t.TempDir()
	write(t, filepath.Join(root, "a.go"), "package a\n")

	run(t, root, "init")
	run(t, root, "config", "user.email", "tests@example.com")
	run(t, root, "config", "user.name", "Tests")
	run(t, root, "add", "a.go")
	run(t, root, "commit", "-m", "one")

	return root
}

func stale(t *testing.T, root string) {
	t.Helper()

	write(t, filepath.Join(root, "a.go"), "package a\n")
	run(t, root, "update-index", "--really-refresh")
	write(t, filepath.Join(root, "a.go"), "package a\n")
}

func recordingGit(t *testing.T) func(read func()) []string {
	t.Helper()

	dir := t.TempDir()
	calls := filepath.Join(dir, "calls")

	write(t, filepath.Join(dir, "git"), "#!/bin/sh\nprintf '%s\\n' \"$*\" >> \""+calls+"\"\n")

	if err := os.Chmod(filepath.Join(dir, "git"), 0o755); err != nil {
		t.Fatalf("our own git: %v", err)
	}

	t.Setenv("PATH", dir+string(os.PathListSeparator)+os.Getenv("PATH"))

	return func(read func()) []string {
		if err := os.Remove(calls); err != nil && !os.IsNotExist(err) {
			t.Fatalf("forgetting the last calls: %v", err)
		}

		read()

		written, err := os.ReadFile(calls)
		if err != nil {
			return nil
		}

		return strings.Split(strings.TrimRight(string(written), "\n"), "\n")
	}
}

func run(t *testing.T, root string, args ...string) {
	t.Helper()

	out, err := exec.Command("git", append([]string{"-C", root}, args...)...).CombinedOutput()
	if err != nil {
		t.Fatalf("git %s: %v: %s", strings.Join(args, " "), err, out)
	}
}

func write(t *testing.T, path, content string) {
	t.Helper()

	if err := os.WriteFile(path, []byte(content), 0o644); err != nil {
		t.Fatalf("writing %s: %v", path, err)
	}
}

func indexOf(t *testing.T, root string) os.FileInfo {
	t.Helper()

	info, err := os.Stat(filepath.Join(root, ".git", "index"))
	if err != nil {
		t.Fatalf("reading the index: %v", err)
	}

	return info
}
