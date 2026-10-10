// Package scope decides which files a run may report on or rewrite: the whole tree, the working tree's
// changes, a branch's changes, or the files a past checklist listed — always less what is frozen and what
// the project excluded. The whole path is still parsed; only the targets narrow.
package scope

import (
	"encoding/json"
	"errors"
	"os"
	"os/exec"
	"path/filepath"
	"regexp"
	"slices"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// Latest names the newest checklist.
const Latest = "latest"

// defaultBase is the branch --branch compares against when it names none.
const defaultBase = "main"

// Options are the scope flags, parsed here and documented by every command that adopts them.
func Options() []help.Option {
	return []help.Option{
		{Spec: "--changes", Does: "only files changed in the working tree (alias: --git)"},
		{Spec: "--branch[=BASE]", Does: "only files new/changed on this branch vs BASE (default: main)"},
		{Spec: "--branch=REPO:[BASE..]HEAD", Does: "only files HEAD changed since it left BASE (default: main) in the repository REPO, a folder under the path with its own .git"},
		{Spec: "--pr=REPO#N", Does: "only the files pull request N changes in the repository REPO, asked of GitHub with gh"},
		{Spec: "--repent[=ID]", Does: "only the files listed in a past run's checklist (bare: the latest)"},
	}
}

// FileScope answers whether one file is in.
type FileScope interface {
	Includes(path string) bool
}

// Scope is a set of target files (every file, when unscoped) less what its restrictions leave out.
type Scope struct {
	files        map[string]bool
	scoped       bool
	by           string
	unseen       []string
	restrictions []FileScope
}

// FromArgs reads the scope flags from a command's raw tail for a run over path, the project excluding the
// excluded paths. A scope that cannot be worked out (no repository, no base, no checklist) is an Unavailable.
func FromArgs(args []string, path string, space workspace.Workspace, excluded []string) (Scope, error) {
	picked, scoped, err := restrictTo(args, path, space, excluded)
	if err != nil {
		return Scope{}, err
	}

	return Scope{files: canonical(picked.files), scoped: scoped, by: picked.by, unseen: picked.unseen, restrictions: always(path, excluded)}, nil
}

// ChosenBy says what chose the files, as a reader reads it: "the files changed on this branch since main"; empty
// when unscoped.
func (s Scope) ChosenBy() string {
	return s.by
}

// Unseen are the repositories under the path that the choice never looked into: a workspace's own checkouts, whose
// changes a diff of the repository the path lies in cannot see.
func (s Scope) Unseen() []string {
	return s.unseen
}

// picked is what a scope flag chose: the files, what chose them, and the repositories under the path it never
// looked into.
type picked struct {
	files  map[string]bool
	by     string
	unseen []string
}

func restrictTo(args []string, path string, space workspace.Workspace, excluded []string) (picked, bool, error) {
	if id, given := Repent(args); given {
		files, err := checklist(id, space)

		return picked{files: files, by: "the files checklist " + id + " lists"}, true, err
	}

	if number, given := optional(args, "--pr", ""); given {
		chosen, err := pullRequestChanges(number, path)

		return chosen, true, err
	}

	if base, given := branch(args); given {
		if repository, head, named := strings.Cut(base, ":"); named {
			chosen, err := nestedBranchChanges(repository, head, path)

			return chosen, true, err
		}
		files, err := branchChanges(base, path)

		return picked{files: files, by: "the files changed on this branch since " + base, unseen: nestedUnder(path, excluded)}, true, err
	}

	if slices.Contains(args, "--changes") {
		files, err := workingTreeChanges(path)

		return picked{files: files, by: "the files changed in the working tree", unseen: nestedUnder(path, excluded)}, true, err
	}

	return picked{}, false, nil
}

// nestedUnder are the repositories checked out beneath path, named from it: what a diff of the repository path lies
// in never sees.
func nestedUnder(path string, excluded []string) []string {
	var named []string
	if absolute, err := filepath.Abs(path); err == nil {
		path = absolute
	}
	for _, repository := range source.Repositories(path, source.Under(path, excluded)) {
		named = append(named, source.Relative(path, repository))
	}

	return named
}

// nestedBranchChanges are the files head changed since it left its base in the repository named under path, the
// spec `[BASE..]HEAD` with main for a base it does not name.
func nestedBranchChanges(name, spec, path string) (picked, error) {
	repository, err := nestedRepository(name, path)
	if err != nil {
		return picked{}, err
	}
	base, head, ranged := strings.Cut(spec, "..")
	if !ranged {
		base, head = defaultBase, spec
	}
	changed, ok := git.ChangedOn(repository, base, head)
	if !ok {
		return picked{}, &Unavailable{"--branch=" + name + ":" + spec + ": " + base + " and " + head + " share no history in " + name + ", or one of them is unknown there."}
	}

	return picked{files: changed, by: "the files " + head + " changed since " + base + " in " + name + checkedOut(repository, head)}, nil
}

// nestedRepository is the folder under path the name gives, refused unless it holds a repository of its own.
func nestedRepository(name, path string) (string, error) {
	repository := filepath.Join(path, name)
	if _, err := os.Stat(filepath.Join(repository, ".git")); err != nil {
		return "", &Unavailable{"No repository " + name + " under " + path + ": name a folder there that holds a .git of its own."}
	}

	return repository, nil
}

// checkedOut is a note that the files are read as the repository has them, when it has another branch checked out
// than the one that chose them.
func checkedOut(repository, head string) string {
	if current := git.CurrentBranch(repository); current != head {
		return " (read as " + current + ", which is checked out there, has them)"
	}

	return ""
}

// pullRequestChanges are the files pull request `REPO#N` changes, asked of GitHub for the repository named under
// path.
func pullRequestChanges(spec, path string) (picked, error) {
	name, number, numbered := strings.Cut(spec, "#")
	if !numbered || name == "" || number == "" {
		return picked{}, &Unavailable{"--pr=" + spec + ": name the repository and the pull request, as --pr=trackmijn#161."}
	}
	repository, err := nestedRepository(name, path)
	if err != nil {
		return picked{}, err
	}
	files, head, err := pullRequest(repository, number)
	if err != nil {
		return picked{}, &Unavailable{"--pr=" + spec + ": " + err.Error()}
	}

	return picked{files: git.Listed(repository, files), by: "the files pull request " + spec + " (" + head + ") changes" + checkedOut(repository, head)}, nil
}

// pullRequest is the pull request's changed files, relative to its repository, and its head branch, as gh answers
// for the repository checked out at repository.
var pullRequest = func(repository, number string) ([]string, string, error) {
	command := exec.Command("gh", "pr", "view", number, "--json", "files,headRefName")
	command.Dir = repository
	out, err := command.Output()
	if err != nil {
		return nil, "", errors.New("gh could not read pull request " + number + ": " + err.Error())
	}
	var answer struct {
		Files []struct {
			Path string `json:"path"`
		} `json:"files"`
		HeadRefName string `json:"headRefName"`
	}
	if err := json.Unmarshal(out, &answer); err != nil {
		return nil, "", err
	}
	var files []string
	for _, file := range answer.Files {
		files = append(files, file.Path)
	}

	return files, answer.HeadRefName, nil
}

// Everything is every file, less what is frozen.
func Everything() Scope {
	return Scope{restrictions: always("", nil)}
}

// RestrictedTo is exactly these files, less what is frozen.
func RestrictedTo(files []string) Scope {
	set := map[string]bool{}

	for _, file := range files {
		set[file] = true
	}

	return Scope{files: canonical(set), scoped: true, restrictions: always("", nil)}
}

// NarrowedTo is the scope narrowed to the one file `judge <file>` names: it alone is a target, and only when the
// scope already took it in.
func (s Scope) NarrowedTo(file string) Scope {
	real, err := resolve(file)
	if err != nil {
		real = file
	}

	if s.scoped && !s.files[real] {
		s.files = map[string]bool{}

		return s
	}

	s.files, s.scoped = map[string]bool{real: true}, true

	return s
}

// And is this scope further narrowed by a restriction.
func (s Scope) And(restriction FileScope) Scope {
	s.restrictions = append(slices.Clone(s.restrictions), restriction)

	return s
}

// Includes says whether the file is a target.
func (s Scope) Includes(file string) bool {
	for _, restriction := range s.restrictions {
		if !restriction.Includes(file) {
			return false
		}
	}

	if !s.scoped {
		return true
	}

	real, err := resolve(file)

	return err == nil && s.files[real]
}

// Permits says whether every file that exists is a target: a cross-file fix touching one that is not is
// dropped whole.
func (s Scope) Permits(files []string) bool {
	for _, file := range files {
		if _, err := os.Stat(file); err == nil && !s.Includes(file) {
			return false
		}
	}

	return true
}

// IsScoped says whether the scope names its files rather than taking every one.
func (s Scope) IsScoped() bool {
	return s.scoped
}

// IsEmpty says whether the scope names no file at all.
func (s Scope) IsEmpty() bool {
	return s.scoped && len(s.files) == 0
}

// Files are the named target files, resolved; nil when unscoped.
func (s Scope) Files() map[string]bool {
	return s.files
}

func always(root string, excluded []string) []FileScope {
	restrictions := []FileScope{&Frozen{}}

	if root != "" {
		restrictions = append(restrictions, Excluded{source.Under(root, excluded)})
	}

	return restrictions
}

func canonical(files map[string]bool) map[string]bool {
	if files == nil {
		return nil
	}

	set := map[string]bool{}

	for file := range files {
		if real, err := resolve(file); err == nil {
			set[real] = true
		} else {
			set[file] = true
		}
	}

	return set
}

// Repent is the checklist --repent names, `latest` when bare, and whether the flag is given.
func Repent(args []string) (string, bool) {
	return optional(args, "--repent", Latest)
}

func branch(args []string) (string, bool) {
	return optional(args, "--branch", defaultBase)
}

func optional(args []string, flag, bare string) (string, bool) {
	for _, arg := range args {
		if arg == flag {
			return bare, true
		}

		if value, valued := strings.CutPrefix(arg, flag+"="); valued {
			return value, true
		}
	}

	return "", false
}

var checklistEntry = regexp.MustCompile("`([^`]+):\\d+`")

func checklist(id string, space workspace.Workspace) (map[string]bool, error) {
	path := space.ChecklistArchive(id)
	if id == Latest {
		path = space.Checklist()
	}

	raw, err := os.ReadFile(path)
	if err != nil {
		return nil, &Unavailable{"No checklist for --repent=" + id + " (looked for " + path + ")."}
	}

	files := map[string]bool{}

	for _, match := range checklistEntry.FindAllStringSubmatch(string(raw), -1) {
		files[match[1]] = true
	}

	return files, nil
}

func workingTreeChanges(path string) (map[string]bool, error) {
	root := git.Root(path)
	if root == "" {
		return nil, notARepository(path)
	}

	return git.ChangedVsHead(root), nil
}

func branchChanges(base, path string) (map[string]bool, error) {
	root := git.Root(path)
	if root == "" {
		return nil, notARepository(path)
	}

	changed, ok := git.ChangedVsBranch(root, base)
	if !ok {
		return nil, &Unavailable{"Base ref '" + base + "' not found: " + path}
	}

	return changed, nil
}

// Unavailable is a scope that cannot be worked out from where the run stands.
type Unavailable struct {
	Reason string
}

func (e *Unavailable) Error() string {
	return e.Reason
}

func notARepository(path string) error {
	return &Unavailable{"Not a git repository (or git unavailable): " + path}
}

// IsUnavailable says whether err is a scope that could not be worked out.
func IsUnavailable(err error) bool {
	var unavailable *Unavailable

	return errors.As(err, &unavailable)
}

// Path takes the files under one folder or file.
type Path struct {
	root string
}

// Under is the scope of the files under root.
func Under(root string) Path {
	real, err := resolve(root)
	if err != nil {
		real = root
	}

	return Path{strings.TrimRight(real, "/")}
}

// Includes says whether the file is root or under it.
func (p Path) Includes(file string) bool {
	real, err := resolve(file)
	if err != nil {
		real = file
	}

	return real == p.root || strings.HasPrefix(real, p.root+"/")
}

// Excluded leaves out what the project excluded.
type Excluded struct {
	paths source.Excluded
}

// Includes says whether the project did not exclude the file.
func (e Excluded) Includes(file string) bool {
	return !e.paths.Covers(file)
}

// Frozen leaves out every frozen file, reading each file's mark once, safely from several goroutines.
type Frozen struct {
	seen sync.Map
}

// Includes says whether the file is not frozen; a file not on disk carries no mark.
func (f *Frozen) Includes(file string) bool {
	real, err := resolve(file)
	if err != nil {
		return true
	}

	if frozen, known := f.seen.Load(real); known {
		return !frozen.(bool)
	}

	frozen := scan.IsFrozen(real)
	f.seen.Store(real, frozen)

	return !frozen
}

// resolve is the file's real, absolute path, as PHP's realpath answers it.
func resolve(file string) (string, error) {
	real, err := filepath.EvalSymlinks(file)
	if err != nil {
		return "", err
	}

	return filepath.Abs(real)
}
