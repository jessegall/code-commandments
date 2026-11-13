// Package scope decides which files a run may report on or rewrite: the whole tree, the working tree's changes, a branch's changes, or the files a past checklist listed — always less what is frozen and what the project excluded.
package scope

import (
	"encoding/json"
	"errors"
	"maps"
	"os"
	"os/exec"
	"path/filepath"
	"regexp"
	"slices"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/walk"
)

const Latest = "latest"

const defaultBase = "main"

func BaseOf(path string) string {
	project, err := config.Load(path)
	if err != nil || strings.TrimSpace(project.Base) == "" {
		return defaultBase
	}

	return strings.TrimSpace(project.Base)
}

func Options() []help.Option {
	return []help.Option{
		{Spec: "--changes", Does: "only the lines changed in the working tree"},
		{Spec: "--changed-files", Does: "every file changed in the working tree, whole"},
		{Spec: "--branch[=BASE]", Does: "only files new/changed on this branch vs BASE (default: the project's `base`, else main)"},
		{Spec: "--branch=REPO:[BASE..]HEAD", Does: "only files HEAD changed since it left BASE (default: that repository's `base`, else main) in the repository REPO, a folder under the path with its own .git"},
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
	lines        map[string]git.ChangedLines
	restrictions []FileScope
}

func FromArgs(args []string, path string, space workspace.Workspace, excluded []string) (Scope, error) {
	picked, scoped, err := restrictTo(args, path, space, excluded)
	if err != nil {
		return Scope{}, err
	}

	return Scope{files: outsideTheExcluded(canonical(picked.files), path, excluded), scoped: scoped, by: picked.by,
		unseen: picked.unseen, lines: canonicalLines(picked.lines), restrictions: always(path, excluded)}, nil
}

// Edits are the files the agents of a checkout edited, for a run that asks for its changes when any were recorded.
type Edits struct {
	Files []string
	Held  bool
}

func (e Edits) Scope(args []string, path string, space workspace.Workspace, excluded []string) (Scope, error) {
	if !e.Held || !slices.Contains(args, "--changes") {
		return FromArgs(args, path, space, excluded)
	}

	files := map[string]bool{}
	for _, file := range e.Files {
		files[file] = true
	}

	return Scope{files: outsideTheExcluded(canonical(files), path, excluded), scoped: true, by: "the files edited since they were last approved",
		restrictions: always(path, excluded)}, nil
}

func outsideTheExcluded(files map[string]bool, path string, excluded []string) map[string]bool {
	if len(files) == 0 || path == "" || len(excluded) == 0 {
		return files
	}

	skipped := source.Under(path, excluded)
	kept := map[string]bool{}

	for file, held := range files {
		if held && !skipped.Covers(file) {
			kept[file] = true
		}
	}

	return kept
}

func (s Scope) ChosenBy() string {
	return s.by
}

func (s Scope) Unseen() []string {
	return s.unseen
}

// picked is what a scope flag chose: the files, what chose them, and the repositories under the path it never looked into.
type picked struct {
	files  map[string]bool
	by     string
	unseen []string
	lines  map[string]git.ChangedLines
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

	if base, given := branch(args, path); given {
		if repository, head, named := strings.Cut(base, ":"); named {
			chosen, err := nestedBranchChanges(repository, head, path)

			return chosen, true, err
		}
		files, err := branchChanges(base, path)

		return picked{files: files, by: "the files changed on this branch since " + base, unseen: nestedUnder(path, excluded)}, true, err
	}

	if slices.Contains(args, "--changed-files") {
		files, err := workingTreeChanges(path)

		return picked{files: files, by: "the files changed in the working tree", unseen: nestedUnder(path, excluded)}, true, err
	}

	if slices.Contains(args, "--changes") {
		files, err := workingTreeChanges(path)
		if err != nil {
			return picked{}, true, err
		}

		return picked{
			files:  files,
			by:     "the lines changed in the working tree",
			unseen: nestedUnder(path, excluded),
			lines:  changedLinesOf(git.Root(path), files),
		}, true, nil
	}

	return picked{}, false, nil
}

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

func nestedBranchChanges(name, spec, path string) (picked, error) {
	repository, err := nestedRepository(name, path)
	if err != nil {
		return picked{}, err
	}
	base, head, ranged := strings.Cut(spec, "..")
	if !ranged {
		base, head = BaseOf(repository), spec
	}
	changed, ok := git.ChangedOn(repository, base, head)
	if !ok {
		return picked{}, &Unavailable{"--branch=" + name + ":" + spec + ": " + base + " and " + head + " share no history in " + name + ", or one of them is unknown there."}
	}

	return picked{files: changed, by: "the files " + head + " changed since " + base + " in " + name + checkedOut(repository, head)}, nil
}

func nestedRepository(name, path string) (string, error) {
	repository := filepath.Join(path, name)
	if _, err := os.Stat(filepath.Join(repository, ".git")); err != nil {
		return "", &Unavailable{"No repository " + name + " under " + path + ": name a folder there that holds a .git of its own."}
	}

	return repository, nil
}

func checkedOut(repository, head string) string {
	if current := git.CurrentBranch(repository); current != head {
		return " (read as " + current + ", which is checked out there, has them)"
	}

	return ""
}

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

func Everything() Scope {
	return Scope{restrictions: always("", nil)}
}

func RestrictedTo(files []string) Scope {
	set := map[string]bool{}

	for _, file := range files {
		set[file] = true
	}

	return Scope{files: canonical(set), scoped: true, restrictions: always("", nil)}
}

func (s Scope) NarrowedTo(file string) Scope {
	real, err := walk.Resolved(file)
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

func (s Scope) And(restriction FileScope) Scope {
	s.restrictions = append(slices.Clone(s.restrictions), restriction)

	return s
}

func (s Scope) Includes(file string) bool {
	for _, restriction := range s.restrictions {
		if !restriction.Includes(file) {
			return false
		}
	}

	if !s.scoped {
		return true
	}

	real, err := walk.Resolved(file)

	return err == nil && s.files[real]
}

func (s Scope) Covers(file string, line int) bool {
	if !s.Includes(file) {
		return false
	}
	if len(s.lines) == 0 {
		return true
	}
	real, err := walk.Resolved(file)
	if err != nil {
		real = file
	}
	changed, named := s.lines[real]
	if !named {
		return true
	}

	return changed.Covers(line)
}

func (s Scope) CoversJudged(file string, line int) bool {
	allowed := s
	allowed.restrictions = slices.DeleteFunc(slices.Clone(s.restrictions), func(restriction FileScope) bool {
		_, byExclusion := restriction.(Excluded)

		return byExclusion
	})

	return allowed.Covers(file, line)
}

func canonicalLines(lines map[string]git.ChangedLines) map[string]git.ChangedLines {
	if len(lines) == 0 {
		return nil
	}
	canonical := map[string]git.ChangedLines{}
	for file, changed := range lines {
		real, err := walk.Resolved(file)
		if err != nil {
			real = file
		}
		canonical[real] = changed
	}

	return canonical
}

func (s Scope) Permits(files []string) bool {
	for _, file := range files {
		if _, err := os.Stat(file); err == nil && !s.Includes(file) {
			return false
		}
	}

	return true
}

func (s Scope) IsScoped() bool {
	return s.scoped
}

func (s Scope) IsEmpty() bool {
	return s.scoped && len(s.files) == 0
}

func (s Scope) Files() map[string]bool {
	return s.files
}

// Within is the folder a run was given, under each name it stands by: a file outside them is left out, whichever tree answered the run.
type Within struct {
	Folders []string
}

func (w Within) Includes(file string) bool {
	real, err := walk.Resolved(file)
	if err != nil {
		real = file
	}

	return slices.ContainsFunc(w.Folders, func(given string) bool {
		folder, err := walk.Resolved(given)
		if err != nil {
			folder = given
		}

		return real == folder || strings.HasPrefix(real, strings.TrimRight(folder, "/")+"/")
	})
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
		if real, err := walk.Resolved(file); err == nil {
			set[real] = true
		} else {
			set[file] = true
		}
	}

	return set
}

func Repent(args []string) (string, bool) {
	return optional(args, "--repent", Latest)
}

func branch(args []string, path string) (string, bool) {
	return optional(args, "--branch", BaseOf(path))
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

func changedLinesOf(root string, files map[string]bool) map[string]git.ChangedLines {
	since := git.WorkStart(root)

	changed := git.ChangedLinesSinceEach(root, since, slices.Collect(maps.Keys(files)))
	lines := map[string]git.ChangedLines{}
	for file := range files {
		lines[file] = changed(file)
	}

	return lines
}

func workingTreeChanges(path string) (map[string]bool, error) {
	root := git.Root(path)
	if root == "" {
		return nil, notARepository(path)
	}

	return git.ChangedSince(root, git.WorkStart(root)), nil
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

func IsUnavailable(err error) bool {
	var unavailable *Unavailable

	return errors.As(err, &unavailable)
}

// Path takes the files under one folder or file.
type Path struct {
	root string
}

func Under(root string) Path {
	real, err := walk.Resolved(root)
	if err != nil {
		real = root
	}

	return Path{strings.TrimRight(real, "/")}
}

func (p Path) Includes(file string) bool {
	real, err := walk.Resolved(file)
	if err != nil {
		real = file
	}

	return walk.Within(p.root, real)
}

// Excluded leaves out what the project excluded.
type Excluded struct {
	paths source.Excluded
}

func (e Excluded) Includes(file string) bool {
	return !e.paths.Covers(file)
}

// Frozen leaves out every frozen file, reading each file's mark once, safely from several goroutines.
type Frozen struct {
	seen sync.Map
}

func (f *Frozen) Includes(file string) bool {
	real, err := walk.Resolved(file)
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
