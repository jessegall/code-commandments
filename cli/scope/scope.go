// Package scope decides which files a run may report on or rewrite: the whole tree, the working tree's
// changes, a branch's changes, or the files a past checklist listed — always less what is frozen and what
// the project excluded. The whole path is still parsed; only the targets narrow.
package scope

import (
	"errors"
	"os"
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
	restrictions []FileScope
}

// FromArgs reads the scope flags from a command's raw tail for a run over path, the project excluding the
// excluded paths. A scope that cannot be worked out (no repository, no base, no checklist) is an Unavailable.
func FromArgs(args []string, path string, space workspace.Workspace, excluded []string) (Scope, error) {
	files, scoped, err := restrictTo(args, path, space)
	if err != nil {
		return Scope{}, err
	}

	return Scope{files: canonical(files), scoped: scoped, restrictions: always(path, excluded)}, nil
}

func restrictTo(args []string, path string, space workspace.Workspace) (map[string]bool, bool, error) {
	if id, given := Repent(args); given {
		files, err := checklist(id, space)

		return files, true, err
	}

	if base, given := branch(args); given {
		files, err := branchChanges(base, path)

		return files, true, err
	}

	if slices.Contains(args, "--changes") {
		files, err := workingTreeChanges(path)

		return files, true, err
	}

	return nil, false, nil
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
