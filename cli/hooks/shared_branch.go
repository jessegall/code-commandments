package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"slices"
	"strconv"
	"strings"
	"sync"
	"time"
)

// rebasing are the flags that make a pull rewrite the branch.
var rebasing = []string{"--rebase", "-r"}

// quoting marks a command whose words may be quoted text; it is not judged, rather than misread.
var quoting = []string{"<<", "'", `"`}

// SharedBranchGate refuses a rebasing pull while other worktrees stand on the branch: the rebase rewrites
// the commits they are built on, and the duplicates it leaves look like nothing until a merge.
type SharedBranchGate struct{}

func (SharedBranchGate) Class() string { return "SharedBranchGate" }
func (SharedBranchGate) Summary() string {
	return "Refuses `git pull --rebase` while other worktrees stand on the branch — it rewrites the commits they are built on."
}
func (SharedBranchGate) Bindings() []Binding { return []Binding{{"PreToolUse", "Bash"}} }
func (SharedBranchGate) SpeaksToSubagents()  {}
func (SharedBranchGate) Gate()               {}

func (SharedBranchGate) Handle(event Event) Response {
	if event.Name() != "PreToolUse" || !event.IsTool("Bash") || !rewritesHistory(event.Command()) {
		return Silent()
	}

	others := otherWorktrees(event.Root)
	if len(others) == 0 {
		return Silent()
	}

	var standing []string
	for _, path := range others {
		standing = append(standing, "  • "+path)
	}

	return Blocking("Code Commandments — that rebases a branch " + strconv.Itoa(len(others)) + " other worktree(s) are standing on:\n\n" +
		strings.Join(standing, "\n") + "\n\n" +
		"A rebase rewrites the commits they are built on, so each ends up carrying commits this branch\n" +
		"no longer has — and the duplicates are byte-identical, so nothing looks wrong until a merge.\n" +
		"Use `git pull --ff-only`, or merge, and let them fast-forward.")
}

func rewritesHistory(command string) bool {
	for _, quote := range quoting {
		if strings.Contains(command, quote) {
			return false
		}
	}

	for _, part := range strings.Split(command, "&&") {
		words := strings.Fields(part)

		if len(words) == 0 || words[0] != "git" || !slices.Contains(words, "pull") {
			continue
		}

		for _, flag := range rebasing {
			if slices.Contains(words, flag) {
				return true
			}
		}
	}

	return false
}

// otherWorktrees are the worktrees of the repository at root other than root itself.
// standing keeps each root's worktrees, so the gate answers from what it knows when the journal waits
// milliseconds for it. Adding or removing one writes in the git folder that holds them, which is what the
// mark below watches: nothing else can change the answer.
var standing = struct {
	sync.Mutex
	listed map[string]listing
}{listed: map[string]listing{}}

// listing is one root's worktrees and the mark of the folders they are written in.
type listing struct {
	others []string
	marked string
}

func otherWorktrees(root string) []string {
	mark := worktreeMark(root)

	standing.Lock()
	known, seen := standing.listed[root]
	standing.Unlock()

	if seen && known.marked == mark {
		return known.others
	}

	others := readWorktrees(root)

	standing.Lock()
	standing.listed[root] = listing{others, mark}
	standing.Unlock()

	return others
}

// worktreeMark says when a worktree was last added or removed: the git directory and the folder it keeps them in,
// by their last change. A checkout whose `.git` is a file is a worktree itself, and that file's own change says
// the same.
func worktreeMark(root string) string {
	var mark strings.Builder
	for _, watched := range []string{filepath.Join(root, ".git"), filepath.Join(root, ".git", "worktrees")} {
		info, err := os.Stat(watched)
		if err != nil {
			continue
		}
		mark.WriteString(watched + ":" + info.ModTime().UTC().Format(time.RFC3339Nano) + "\n")
	}

	return mark.String()
}

// readWorktrees asks git, which is the slow answer the mark above keeps us from asking for twice.
func readWorktrees(root string) []string {
	listing, _ := exec.Command("git", "-C", root, "worktree", "list", "--porcelain").Output()
	self := realPath(root)

	var others []string

	for _, line := range strings.Split(string(listing), "\n") {
		if path, declared := strings.CutPrefix(line, "worktree "); declared && realPath(path) != self {
			others = append(others, path)
		}
	}

	return others
}

func realPath(path string) string {
	real, err := filepath.EvalSymlinks(path)
	if err != nil {
		return ""
	}

	return real
}
