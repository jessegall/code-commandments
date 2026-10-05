package hooks

import (
	"os"
	"path/filepath"
	"slices"
	"strconv"
	"strings"
	"time"

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

// tick is how far before a command's start a file's time may fall and still be its own: a filesystem stamps a write
// from a coarse clock, so a file written just after the precise start can read earlier: by a kernel tick, by more
// in a loaded virtual machine, by up to a second on HFS+, which keeps whole seconds.
const tick = time.Second

// stampsLegend says what is taken as a shell command starts.
var stampsLegend = &state.Legend{
	About:     "Code-commandments — when the session's last shell command started, and the changed judged files and their times then.",
	Variables: []state.Variable{{Name: "started_at", Meaning: "unix nanoseconds when the hook was handed the command; a changed file modified since is one it wrote"}},
	Defaults:  state.New(state.Int("started_at", 0)),
	List:      "a file path, a tab, then its modification time in nanoseconds",
	Safe:      "the next shell command's changes are not counted as the session's",
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

// Before takes a shell command's start: when the hook was handed it, and the changed files and their times. Under
// the journal this runs off the hook's path, often after a quick command has written, so the start time is what
// tells such a write from what came before.
func (a Authored) Before(started time.Time, root string) {
	var lines []string

	for file := range git.Status(root).Changed {
		lines = append(lines, file+"\t"+strconv.FormatInt(modified(file), 10))
	}

	a.stamps.Write(a.stamps.Read().With(state.Int("started_at", int(started.UnixNano()))).WithItems(lines))
}

// After records the changed files the shell command wrote: each modified since it started, or new or written again
// since its stamps were taken, which also catches a write that keeps an older time (tar, cp -p, touch -t).
func (a Authored) After(root string) {
	taken := a.stamps.Read()
	started := int64(taken.Int("started_at", 0))
	before := map[string]string{}

	for _, line := range taken.Items() {
		file, stamp, _ := strings.Cut(line, "\t")
		before[file] = stamp
	}

	var wrote []string

	for file := range git.Status(root).Changed {
		stamp := modified(file)
		if (started != 0 && stamp >= started-int64(tick)) || before[file] != strconv.FormatInt(stamp, 10) {
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
