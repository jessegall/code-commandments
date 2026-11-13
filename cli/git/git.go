// Package git answers what the tool asks of a repository: where it starts, which checkout is the main one, what changed in the working tree or on a branch, and which lines of a file changed.
package git

import (
	"errors"
	"fmt"
	"io/fs"
	"os"
	"os/exec"
	"path/filepath"
	"regexp"
	"slices"
	"sort"
	"strconv"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/walk"
)

const (
	gitdirPrefix = "gitdir:"
	headHeader   = "# branch.oid "
	noCommit     = "(initial)"
	worktrees    = "worktrees"
)

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
	dir = walk.Real(dir)

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

func BelongsTo(root, project string) bool {
	root, project = walk.Real(root), walk.Real(project)

	if root == project {
		return true
	}

	gitdir := gitDirOf(root)
	if gitdir == "" || filepath.Base(filepath.Dir(gitdir)) != worktrees {
		return false
	}

	return walk.Within(project, gitdir)
}

func ProjectRoot(path string) string {
	if said, told := journalSaysOf(path); told {
		return said.project
	}

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

	return walk.Real(gitdir)
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

func Worktrees(root string) []string {
	var found []string

	for _, line := range strings.Split(run(root, "worktree", "list", "--porcelain"), "\n") {
		path, isWorktree := strings.CutPrefix(line, "worktree ")

		if isWorktree && walk.Real(path) != walk.Real(root) {
			found = append(found, path)
		}
	}

	return found
}

func Head(root string) string {
	return ask(root, "rev-parse", "HEAD")
}

func CurrentBranch(root string) string {
	return recalling("branch\x00"+root, func() string { return headState(root) }, func() string {
		return ask(root, "rev-parse", "--abbrev-ref", "HEAD")
	})
}

// WorkingTree is what one status call says: the commit checked out and every judged file that differs from it, tracked or not.
type WorkingTree struct {
	Head    string
	Changed map[string]bool
}

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

func field(entry string, count, at int) string {
	fields := strings.SplitN(entry, " ", count)

	if at < len(fields) {
		return fields[at]
	}

	return ""
}

func MergeBase(root, base string) string {
	if incoming := beingMergedIn(root); incoming != "" {
		return incoming
	}

	project := ProjectRoot(root)

	return recalling("merge-base\x00"+root+"\x00"+base, func() string { return headState(root) + "\x00" + headState(project) },
		func() string { return ask(root, "merge-base", base, "HEAD") })
}

func beingMergedIn(root string) string {
	own := filepath.Join(root, ".git")
	if gitdir := gitDirOf(root); gitdir != "" {
		own = gitdir
	}

	raw, err := os.ReadFile(filepath.Join(own, "MERGE_HEAD"))
	if err != nil {
		return ""
	}

	if fields := strings.Fields(string(raw)); len(fields) > 0 {
		return fields[0]
	}

	return ""
}

func ChangedVsBranch(root, base string) (changed map[string]bool, ok bool) {
	mergeBase := MergeBase(root, base)
	if mergeBase == "" {
		return nil, false
	}

	return ChangedSince(root, mergeBase), true
}

func ChangedSince(root, commit string) map[string]bool {
	if commit == headCommit {
		return ChangedVsHead(root)
	}

	tracked := run(root, "diff", "--name-only", "--diff-filter=d", commit)
	untracked := run(root, "ls-files", "--others", "--exclude-standard")

	return pathSet(root, tracked+"\n"+untracked)
}

func WorkStart(root string) string {
	if said, told := journalSaysOf(root); told {
		if said.isTheProject() {
			return headCommit
		}

		if said.base != "" {
			return said.base
		}
	}

	project := ProjectRoot(root)
	if project == "" || walk.Real(project) == walk.Real(root) {
		return headCommit
	}

	branch := CurrentBranch(project)
	if branch == "" || branch == headCommit {
		return headCommit
	}

	if cut := MergeBase(root, branch); cut != "" {
		return cut
	}

	return headCommit
}

const headCommit = "HEAD"

func DeletedVsBranch(root, base string) (deleted []string, ok bool) {
	mergeBase := MergeBase(root, base)
	if mergeBase == "" {
		return nil, false
	}

	return deletedSince(root, mergeBase), true
}

func deletedSince(root, commit string) []string {
	var deleted []string

	for _, relative := range anyLineBreak.Split(run(root, "diff", "--name-only", "--diff-filter=D", commit), -1) {
		relative = trim(relative)

		if relative == "" || !source.Judges(relative) {
			continue
		}

		deleted = append(deleted, relative)
	}

	return deleted
}

func DiffersFromTip(root, branch string) (changed map[string]bool, deleted []string, ok bool) {
	if MergeBase(root, branch) == "" {
		return nil, nil, false
	}

	return ChangedSince(root, branch), deletedSince(root, branch), true
}

func ChangedOn(root, base, head string) (changed map[string]bool, ok bool) {
	if head == CurrentBranch(root) {
		return ChangedVsBranch(root, base)
	}
	mergeBase := ask(root, "merge-base", base, head)
	if mergeBase == "" {
		return nil, false
	}

	return pathSet(root, run(root, "diff", "--name-only", "--diff-filter=d", mergeBase, head)), true
}

func Holds(root, commit string) bool {
	if commit == "" {
		return false
	}

	return succeeded(root, "cat-file", "-e", commit+"^{commit}")
}

func IsAncestor(root, older, newer string) bool {
	if older == "" || newer == "" {
		return false
	}

	return succeeded(root, "merge-base", "--is-ancestor", older, newer)
}

func succeeded(dir string, args ...string) bool {
	return reading(dir, args...).Run() == nil
}

func ChangedBetween(root, before, after string) []string {
	if before == "" || after == "" {
		return nil
	}

	var named []string
	for name := range pathSet(root, run(root, "diff", "--name-only", "--diff-filter=d", before, after)) {
		named = append(named, name)
	}

	sort.Strings(named)

	return named
}

func Listed(root string, relative []string) map[string]bool {
	return pathSet(root, strings.Join(relative, "\n"))
}

func StillChanged(root string, relative []string) []string {
	changed := ChangedVsHead(root)

	return slices.DeleteFunc(slices.Clone(relative), func(one string) bool {
		for path := range Listed(root, []string{one}) {
			return !changed[path]
		}

		return true
	})
}

// Tracked is every file git tracks under the folders, named relative to the checkout.
func Tracked(root string, folders ...string) ([]string, error) {
	listed, err := reading(root, append([]string{"ls-files", "-z", "--"}, folders...)...).Output()
	if err != nil {
		return nil, fmt.Errorf("git ls-files: %w", err)
	}

	return strings.Split(strings.TrimRight(string(listed), "\x00"), "\x00"), nil
}

func ChangedLinesOf(root, file string) ChangedLines {
	return ChangedLinesSince(root, "HEAD", file)
}

func ChangedLinesSince(root, commit, file string) ChangedLines {
	if tracked, known := tracking(root, file); known && !tracked {
		return Everywhere()
	}

	return FromDiff(run(root, "diff", "-U0", commit, "--", file))
}

func ChangedLinesBetween(root, before, after, file string) ChangedLines {
	return FromDiff(run(root, "diff", "-U0", before, after, "--", file))
}

// ChangedLinesSinceEach answers each named file's changed lines since the commit from one listing of what git tracks
// and one diff of them all, asked at the first question; a file not named is asked on its own.
func ChangedLinesSinceEach(root, commit string, files []string) func(file string) ChangedLines {
	var once sync.Once
	var named changedEach
	return func(file string) ChangedLines {
		once.Do(func() {
			named = diffedEach(root, files, commit)
			named.everywhereUntracked(root)
		})
		if lines, held := named.of(file); held {
			return lines
		}

		return ChangedLinesSince(root, commit, file)
	}
}

// ChangedLinesBetweenEach answers each named file's lines changed between two commits from one diff of them all.
func ChangedLinesBetweenEach(root, before, after string, files []string) func(file string) ChangedLines {
	var once sync.Once
	var named changedEach
	return func(file string) ChangedLines {
		once.Do(func() { named = diffedEach(root, files, before, after) })
		if lines, held := named.of(file); held {
			return lines
		}

		return ChangedLinesBetween(root, before, after, file)
	}
}

// changedEach is the changed lines of each file a diff named, by its path relative to the checkout.
type changedEach struct {
	relative map[string]string
	lines    map[string]ChangedLines
}

func (c changedEach) of(file string) (ChangedLines, bool) {
	relative, named := c.relative[file]
	if !named {
		return ChangedLines{}, false
	}

	return c.lines[relative], true
}

// everywhereUntracked marks every named file git does not track as changed everywhere; when git cannot say, each
// stands as tracked.
func (c changedEach) everywhereUntracked(root string) {
	listed, err := reading(root, "ls-files", "-z").Output()
	if err != nil {
		return
	}
	tracked := map[string]bool{}
	for _, relative := range strings.Split(string(listed), "\x00") {
		tracked[relative] = true
	}
	for _, relative := range c.relative {
		if !tracked[relative] {
			c.lines[relative] = Everywhere()
		}
	}
}

// diffedEach asks git for one diff of the checkout, whose cost follows what changed, and cuts it into the hunks of each
// named file.
func diffedEach(root string, files []string, revisions ...string) changedEach {
	named := changedEach{relative: map[string]string{}, lines: map[string]ChangedLines{}}
	for _, file := range files {
		if relative, inside := walk.Inside(root, file); inside {
			named.relative[file] = filepath.ToSlash(relative)
		}
	}
	if len(named.relative) == 0 {
		return named
	}
	arguments := append([]string{"-c", "core.quotePath=false", "diff", "-U0", "--no-renames", "--relative", "--src-prefix=a/", "--dst-prefix=b/"}, revisions...)
	diff, _ := reading(root, arguments...).Output()

	hunks := map[string]*strings.Builder{}
	current := ""
	for _, line := range strings.Split(string(diff), "\n") {
		switch {
		case strings.HasPrefix(line, "+++ "):
			// git ends the name with a tab when it holds a space
			current = strings.TrimSuffix(strings.TrimPrefix(strings.TrimPrefix(line, "+++ "), "b/"), "\t")
			if hunks[current] == nil {
				hunks[current] = &strings.Builder{}
			}
		case strings.HasPrefix(line, "@@ ") && hunks[current] != nil:
			hunks[current].WriteString(line + "\n")
		}
	}
	for relative, hunk := range hunks {
		named.lines[relative] = FromDiff(hunk.String())
	}

	return named
}

var anyLineBreak = regexp.MustCompile(`\r\n|[\n\r\x0B\f]`)

func pathSet(root, lines string) map[string]bool {
	top := walk.Real(root)
	set := map[string]bool{}

	for _, relative := range anyLineBreak.Split(lines, -1) {
		relative = trim(relative)

		if relative == "" || !source.Judges(relative) {
			continue
		}

		if info, err := os.Lstat(filepath.Join(root, relative)); err != nil || info.Mode()&fs.ModeSymlink != 0 {
			continue
		}

		absolute, err := walk.Resolved(filepath.Join(root, relative))
		if err != nil {
			continue
		}

		if source.Reaches(top, absolute, source.Excluded{}) {
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

func Everywhere() ChangedLines {
	return ChangedLines{whole: true}
}

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

		ranges = append(ranges, [2]int{start, start + count - 1})
	}

	return ChangedLines{ranges: ranges}
}

func AtLines(lines []int) ChangedLines {
	var ranges [][2]int
	for _, line := range lines {
		ranges = append(ranges, [2]int{line, line})
	}

	return ChangedLines{ranges: ranges}
}

func (c ChangedLines) None() bool {
	return !c.whole && len(c.ranges) == 0
}

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

func ask(dir string, args ...string) string {
	return trim(run(dir, args...))
}

func wrote(dir string, args ...string) (string, error) {
	out, err := writing(dir, args...).CombinedOutput()

	return strings.TrimSpace(string(out)), err
}

func run(dir string, args ...string) string {
	out, err := reading(dir, args...).Output()
	if err != nil && len(out) == 0 {
		return ""
	}

	return string(out)
}

// reading starts a git that only reads: it takes no optional lock, so it never blocks the checkout's own git.
func reading(dir string, args ...string) *exec.Cmd {
	return started(append([]string{"--no-optional-locks", "-C", dir}, args...))
}

// writing starts a git that may change the checkout.
func writing(dir string, args ...string) *exec.Cmd {
	return started(append([]string{"-C", dir}, args...))
}

// started is the one place this package starts git, counted once a start.
func started(arguments []string) *exec.Cmd {
	counted()

	return exec.Command("git", arguments...)
}

// literal names the paths a command reads from its input, each taken as written, never as a pattern.
func literal(command *exec.Cmd, env, paths []string) *exec.Cmd {
	command.Env = append(append(os.Environ(), "GIT_LITERAL_PATHSPECS=1"), env...)
	if paths != nil {
		command.Stdin = strings.NewReader(strings.Join(paths, "\x00") + "\x00")
	}

	return command
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

func Tracks(root, path string) bool {
	tracked, _ := tracking(root, path)

	return tracked
}

func tracking(root, path string) (tracked, known bool) {
	err := reading(root, "ls-files", "--error-unmatch", "--", path).Run()

	var untracked *exec.ExitError
	if errors.As(err, &untracked) && untracked.ExitCode() == 1 {
		return false, true
	}

	return err == nil, err == nil
}

func literally(dir string, env, paths []string, args ...string) (string, error) {
	out, err := literal(writing(dir, args...), env, paths).CombinedOutput()

	return strings.TrimSpace(string(out)), err
}
