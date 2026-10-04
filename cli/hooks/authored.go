package hooks

import (
	"os"
	"path/filepath"
	"slices"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/state"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// authoredLegend says what the authored-files list is.
var authoredLegend = &state.Legend{
	About: "Code-commandments — the judged files this session changed, so the judge reminder counts only its own.",
	List:  "one absolute file path per line",
	Safe:  "the reminder stays silent until the session changes a judged file again",
}

// stampsLegend says what the stamps taken before a shell command are.
var stampsLegend = &state.Legend{
	About: "Code-commandments — the changed judged files and their times when the session's last shell command started.",
	List:  "a file path, a tab, then its modification time in nanoseconds",
	Safe:  "the next shell command's changes are not counted as the session's",
}

// Authored are the judged files a session changed since the tree was last clean.
type Authored struct {
	file   state.File
	stamps state.File
}

// AuthoredIn is the session's list of the files it changed.
func AuthoredIn(space workspace.Workspace) Authored {
	return Authored{state.At(space.Path(".authored-files"), authoredLegend), state.At(space.Path(".authored-stamps"), stampsLegend)}
}

// Wrote records a file a writer tool changed, a relative path read from root.
func (a Authored) Wrote(root, file string) {
	if !filepath.IsAbs(file) {
		file = filepath.Join(root, file)
	}

	if resolved, err := filepath.EvalSymlinks(file); err == nil {
		a.add([]string{resolved})
	}
}

// Before takes the times of the changed files as a shell command starts.
func (a Authored) Before(root string) {
	var lines []string

	for file := range git.Status(root).Changed {
		lines = append(lines, file+"\t"+strconv.FormatInt(modified(file), 10))
	}

	a.stamps.Write(a.stamps.Read().WithItems(lines))
}

// After records the changed files the shell command wrote: new since it started, or written again.
func (a Authored) After(root string) {
	before := map[string]string{}

	for _, line := range a.stamps.Read().Items() {
		file, stamp, _ := strings.Cut(line, "\t")
		before[file] = stamp
	}

	var wrote []string

	for file := range git.Status(root).Changed {
		if before[file] != strconv.FormatInt(modified(file), 10) {
			wrote = append(wrote, file)
		}
	}

	a.add(wrote)
}

// Among are the changed files the session wrote itself.
func (a Authored) Among(changed map[string]bool) []string {
	return slices.DeleteFunc(a.file.Read().Items(), func(file string) bool { return !changed[file] })
}

// Clear forgets every file, once the tree is clean again.
func (a Authored) Clear() {
	a.file.Delete()
}

func (a Authored) add(files []string) {
	kept := a.file.Read().Items()
	grown := len(kept)

	for _, file := range files {
		if !slices.Contains(kept, file) {
			kept = append(kept, file)
		}
	}

	if len(kept) > grown {
		a.file.Write(a.file.Read().WithItems(kept))
	}
}

func modified(file string) int64 {
	info, err := os.Stat(file)
	if err != nil {
		return 0
	}

	return info.ModTime().UnixNano()
}
