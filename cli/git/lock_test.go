package git_test

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/git"
)

// flag is the option that keeps a read off the repository's index lock.
const flag = "--no-optional-locks"

// TestAStatusReadNeverRewritesTheIndex is the whole point of the flag, proven against real git: a read of a
// working tree whose index is stale leaves .git/index exactly as it was. Rewriting it takes .git/index.lock,
// and these reads come from a hook, beside a session running git itself — a status read taking that lock made
// `git commit` in the same checkout fail with "Unable to create index.lock", four commits in a row.
func TestAStatusReadNeverRewritesTheIndex(t *testing.T) {
	root := repository(t)
	stale(t, root)

	before := indexOf(t, root)

	git.Status(root)

	if !os.SameFile(before, indexOf(t, root)) {
		t.Fatal("reading the working tree rewrote .git/index, so it took the index lock a commit beside it needs")
	}
}

// TestGitRewritesTheIndexWithoutTheFlag is the control: the same read without the flag DOES rewrite the index,
// so the test above is proving something. A failure here says git stopped refreshing on a read and the guard is
// no longer what keeps us off the lock.
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

// TestEveryReadCarriesTheFlag holds every reader to it, not only the status one: each is run against a git of
// our own that records how it was called. A reader that reaches git another way is how the lock came back.
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

// TestNothingElseRunsGitItself keeps the one funnel the only way to git: a command or a hook that runs it
// itself carries no flag, so it takes the lock whatever this package does. A test is left out: it builds the
// repository it reads, and nobody is committing beside it.
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

// repository is a git repository holding one committed file.
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

// stale makes the index out of date with the working tree, which is what a read would refresh: the file's
// content is the same, so only its stamp tells git to look again.
func stale(t *testing.T, root string) {
	t.Helper()

	write(t, filepath.Join(root, "a.go"), "package a\n")
	run(t, root, "update-index", "--really-refresh")
	write(t, filepath.Join(root, "a.go"), "package a\n")
}

// recordingGit puts a git of our own first on the path and answers a function that runs one read and gives
// back how git was called while it ran, one line per call.
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
