// Package git answers what the tool asks of a repository: where it starts, which checkout is the main
// one, what changed in the working tree or on a branch, and which lines of a file changed. Where the
// filesystem can answer, it is read; git is asked only for what a walk cannot know.
package git

import (
	"os"
	"os/exec"
	"path/filepath"
	"regexp"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/cli/source"
)

const (
	gitdirPrefix = "gitdir:"
	headHeader   = "# branch.oid "
	noCommit     = "(initial)"
	worktrees    = "worktrees"
)

// Root is the folder holding the `.git` of the repository path is in, walked up from path; empty when it
// is in none.
func Root(path string) string {
	dir := path

	if info, err := os.Stat(path); err != nil || !info.IsDir() {
		dir = filepath.Dir(path)
	}

	if root := walkUp(dir); root != "" {
		return root
	}

	return ask(dir, "rev-parse", "--show-toplevel")
}

func walkUp(dir string) string {
	dir = real(dir)

	for {
		if _, err := os.Lstat(filepath.Join(dir, ".git")); err == nil {
			return dir
		}

		parent := filepath.Dir(dir)
		if parent == dir {
			return ""
		}

		dir = parent
	}
}

// BelongsTo says whether root is project or a checkout of it: inside it, or a worktree whose git
// directory is inside it.
func BelongsTo(root, project string) bool {
	root, project = real(root), real(project)

	if within(root, project) {
		return true
	}

	gitdir := gitDirOf(root)

	return gitdir != "" && within(gitdir, project)
}

// ProjectRoot is the main checkout of the repository path is in, so every worktree of one project shares
// one answer; empty when path is in no repository.
func ProjectRoot(path string) string {
	top := Root(path)

	if top == "" {
		return askProjectRoot(path)
	}

	if main := mainWorktreeOf(top); main != "" {
		return main
	}

	return askProjectRoot(path)
}

func mainWorktreeOf(top string) string {
	if info, err := os.Stat(filepath.Join(top, ".git")); err == nil && info.IsDir() {
		return top
	}

	gitdir := gitDirOf(top)

	if gitdir == "" || filepath.Base(filepath.Dir(gitdir)) != worktrees {
		return ""
	}

	return filepath.Dir(filepath.Dir(filepath.Dir(gitdir)))
}

// gitDirOf is the git directory a worktree's `.git` file names, or empty when root has no such file.
func gitDirOf(root string) string {
	raw, err := os.ReadFile(filepath.Join(root, ".git"))
	if err != nil {
		return ""
	}

	gitdir, named := strings.CutPrefix(trim(string(raw)), gitdirPrefix)
	if !named {
		return ""
	}

	gitdir = trim(gitdir)

	if !strings.HasPrefix(gitdir, "/") {
		gitdir = root + "/" + gitdir
	}

	return real(gitdir)
}

func askProjectRoot(path string) string {
	common := ask(path, "rev-parse", "--git-common-dir")

	switch {
	case common == "":
		return ""
	case strings.HasPrefix(common, "/"):
		return filepath.Dir(common)
	default:
		return ask(path, "rev-parse", "--show-toplevel")
	}
}

// Worktrees are the other checkouts of the repository at root.
func Worktrees(root string) []string {
	var found []string

	for _, line := range strings.Split(run(root, "worktree", "list", "--porcelain"), "\n") {
		path, isWorktree := strings.CutPrefix(line, "worktree ")

		if isWorktree && real(path) != real(root) {
			found = append(found, path)
		}
	}

	return found
}

// Head is the commit checked out at root, or empty before the first commit.
func Head(root string) string {
	return ask(root, "rev-parse", "HEAD")
}

// CurrentBranch is the branch checked out at root.
func CurrentBranch(root string) string {
	return ask(root, "rev-parse", "--abbrev-ref", "HEAD")
}

// WorkingTree is what one status call says: the commit checked out and every judged file that differs
// from it, tracked or not.
type WorkingTree struct {
	Head    string
	Changed map[string]bool
}

// Status reads the working tree at root in one call.
func Status(root string) WorkingTree {
	status := run(root, "status", "--porcelain=v2", "--branch", "--no-ahead-behind", "--no-renames", "--untracked-files=all", "-z")
	head := ""
	var paths []string

	for _, entry := range strings.Split(status, "\x00") {
		if oid, isHead := strings.CutPrefix(entry, headHeader); isHead {
			head = oid

			continue
		}

		paths = append(paths, statusPath(entry))
	}

	if head == noCommit {
		head = ""
	}

	return WorkingTree{Head: head, Changed: pathSet(root, strings.Join(paths, "\n"))}
}

// ChangedVsHead are the judged files that differ from the commit checked out.
func ChangedVsHead(root string) map[string]bool {
	return Status(root).Changed
}

func statusPath(entry string) string {
	switch {
	case strings.HasPrefix(entry, "1"):
		return field(entry, 9, 8)
	case strings.HasPrefix(entry, "u"):
		return field(entry, 11, 10)
	case strings.HasPrefix(entry, "?"):
		return entry[min(2, len(entry)):]
	default:
		return ""
	}
}

// field is the at-th of the first count space-separated fields, the last one keeping its spaces.
func field(entry string, count, at int) string {
	fields := strings.SplitN(entry, " ", count)

	if at < len(fields) {
		return fields[at]
	}

	return ""
}

// ChangedVsBranch are the judged files new or changed on this branch since it left base, committed or
// not; ok is false when base shares no history with HEAD.
func ChangedVsBranch(root, base string) (changed map[string]bool, ok bool) {
	mergeBase := ask(root, "merge-base", base, "HEAD")
	if mergeBase == "" {
		return nil, false
	}

	tracked := run(root, "diff", "--name-only", "--diff-filter=d", mergeBase)
	untracked := run(root, "ls-files", "--others", "--exclude-standard")

	return pathSet(root, tracked+"\n"+untracked), true
}

// ChangedLinesOf are the lines of file that differ from HEAD; every line, for a file git does not track.
func ChangedLinesOf(root, file string) ChangedLines {
	if ask(root, "ls-files", "--", file) == "" {
		return Everywhere()
	}

	return FromDiff(run(root, "diff", "-U0", "HEAD", "--", file))
}

var anyLineBreak = regexp.MustCompile(`\r\n|[\n\r\x0B\f]`)

// pathSet is the absolute, resolved judged files among git's relative lines, leaving out any a walk of
// the tree would never reach.
func pathSet(root, lines string) map[string]bool {
	top := real(root)
	set := map[string]bool{}

	for _, relative := range anyLineBreak.Split(lines, -1) {
		relative = trim(relative)

		if relative == "" || !source.Judges(relative) {
			continue
		}

		absolute, err := filepath.EvalSymlinks(filepath.Join(root, relative))
		if err != nil {
			continue
		}

		if absolute, _ = filepath.Abs(absolute); source.Reaches(top, absolute, source.Excluded{}) {
			set[absolute] = true
		}
	}

	return set
}

// ChangedLines are the lines of a file a diff touched.
type ChangedLines struct {
	whole  bool
	ranges [][2]int
}

// Everywhere covers every line: a file with no history is all new.
func Everywhere() ChangedLines {
	return ChangedLines{whole: true}
}

// FromDiff reads the added side of each hunk header of a zero-context diff.
func FromDiff(diff string) ChangedLines {
	var ranges [][2]int

	for _, line := range strings.Split(diff, "\n") {
		if !strings.HasPrefix(line, "@@ ") {
			continue
		}

		fields := strings.Split(line, " ")
		added := strings.Split(strings.TrimLeft(fields[min(2, len(fields)-1)], "+"), ",")
		start := leadingInt(added[0])
		count := 1

		if len(added) == 2 {
			count = leadingInt(added[1])
		}

		ranges = append(ranges, [2]int{start, start + count})
	}

	return ChangedLines{ranges: ranges}
}

// Covers says whether the line changed.
func (c ChangedLines) Covers(line int) bool {
	if c.whole {
		return true
	}

	for _, span := range c.ranges {
		if line >= span[0] && line <= span[1] {
			return true
		}
	}

	return false
}

func within(path, parent string) bool {
	return path == parent || strings.HasPrefix(path, strings.TrimRight(parent, "/")+"/")
}

// ask runs git and answers its trimmed output, empty when it fails.
func ask(dir string, args ...string) string {
	return trim(run(dir, args...))
}

// run runs git in dir and answers its output, empty when it fails.
func run(dir string, args ...string) string {
	out, err := exec.Command("git", append([]string{"-C", dir}, args...)...).Output()
	if err != nil && len(out) == 0 {
		return ""
	}

	return string(out)
}

func real(path string) string {
	resolved, err := filepath.EvalSymlinks(path)
	if err != nil {
		return path
	}

	if absolute, err := filepath.Abs(resolved); err == nil {
		return absolute
	}

	return resolved
}

func trim(text string) string {
	return strings.Trim(text, " \t\n\r\x00\x0B")
}

func leadingInt(text string) int {
	end := 0

	for end < len(text) && text[end] >= '0' && text[end] <= '9' {
		end++
	}

	number, _ := strconv.Atoi(text[:end])

	return number
}
