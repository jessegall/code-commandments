package git

import (
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"sync"
)

// remembered holds the answers git gives that change only when a checkout's HEAD moves, against the state of
// the files that say where HEAD is. Asking git costs a process each time, and the warm path asks on every edit
// and twice over on every judging: a burst of edits paid a process per edit for an answer that had not changed.
//
// Against the FILES and never a clock. A cache that expires guesses: too short and it buys nothing, too long and
// it answers about a branch nobody is on. A checkout's HEAD is a file, and so is the ref it names, so what makes
// the answer stale is exactly what the mark of those two says.
var remembered sync.Map

// asked counts the git processes this package has started, for a test to hold the caching to.
var asked struct {
	sync.Mutex
	count int
}

// counted records one git process.
func counted() {
	asked.Lock()
	asked.count++
	asked.Unlock()
}

// Asked is how many git processes this package has started. It exists for the tests that hold the caching: a
// cache nothing counts is a cache nobody can prove.
func Asked() int {
	asked.Lock()
	defer asked.Unlock()

	return asked.count
}

// recalling answers from what git last said for that key, where nothing that decides the answer has moved since,
// and asks git otherwise.
func recalling(key string, state func() string, ask func() string) string {
	now := state()

	if held, known := remembered.Load(key); known {
		if was := held.([2]string); was[0] == now {
			return was[1]
		}
	}

	said := ask()
	remembered.Store(key, [2]string{now, said})

	return said
}

// headState is what says where a checkout's HEAD is: its own HEAD file, the ref that file names, and the packed
// refs. A commit made, a branch switched or a rebase moved rewrites one of those, and nothing else can change
// which branch a checkout is on or where two of them last met.
//
// Read from DISK and never from git. A state that asked git would cost the very process the cache is here to
// save, and would cost it twice.
func headState(root string) string {
	own := filepath.Join(root, ".git")
	if gitdir := gitDirOf(root); gitdir != "" {
		own = gitdir
	}

	head := filepath.Join(own, "HEAD")
	state := markOf(head)

	// A worktree's own refs are the repository's, which lie beside the common directory: <project>/.git for a
	// worktree's <project>/.git/worktrees/<n>.
	common := own
	if filepath.Base(filepath.Dir(own)) == worktrees {
		common = filepath.Dir(filepath.Dir(own))
	}

	state += "\x00" + markOf(filepath.Join(common, "packed-refs"))

	// The ref HEAD names, for a commit made on the branch it is already on: HEAD itself does not move then, the
	// ref does. Its name is in the HEAD file, which we have just read the mark of.
	raw, err := os.ReadFile(head)
	if err != nil {
		return state
	}

	if ref, named := strings.CutPrefix(strings.TrimSpace(string(raw)), "ref: "); named {
		return state + "\x00" + markOf(filepath.Join(common, strings.TrimSpace(ref)))
	}

	// A detached HEAD holds the commit itself, so the HEAD file's own mark is the whole state.
	return state
}

// markOf is what the file is now: its size and the moment it was written, which is what the filesystem answers
// without reading it. Empty for a file that is not there.
func markOf(path string) string {
	info, err := os.Stat(path)
	if err != nil {
		return ""
	}

	return info.ModTime().UTC().String() + "\x00" + strconv.FormatInt(info.Size(), 10)
}
