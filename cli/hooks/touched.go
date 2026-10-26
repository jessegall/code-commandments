package hooks

import (
	"os"
	"slices"
	"sort"
	"strings"
	"time"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/state"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// touchedLegend says what the touched-sources mark is.
var touchedLegend = &state.Legend{
	About:     "Code-commandments — where the `edits` watcher has looked up to.",
	Variables: []state.Variable{{Name: "marked_at", Meaning: "the unix time of the last look; a judged file newer than this is one a tool has changed since"}},
	Defaults:  state.New(state.Int("marked_at", 0)),
}

// Touched are the judged source files a tool has changed since the watcher last looked, for an edit that
// does not name its file, as a shell command does.
type Touched struct {
	file    state.File
	root    string
	project config.Config
}

// TouchedIn is the watcher of the session in the project at root.
func TouchedIn(space workspace.Workspace, root string, project config.Config) Touched {
	return Touched{state.At(space.Path(".touched-sources"), touchedLegend), root, project}
}

// Claim are the files changed since the last look, newest first and at most limit, with whether any were left
// over. It does NOT mark the look: a file this check does not judge has to still be waiting for the next one, so
// the caller marks seen only once nothing is left. The first look of a session marks only: there is nothing yet
// to compare with.
func (t Touched) Claim(limit int) (claimed []string, more bool) {
	since := t.file.Read().Int("marked_at", 0)

	if since == 0 {
		t.MarkSeen()

		return nil, false
	}

	type stamped struct {
		path  string
		mtime int64
	}

	var touched []stamped
	home := strings.TrimRight(t.root, "/")

	// A folder one rule judges despite the exclusion is walked like a declared path, so an edit a shell made
	// inside it is seen at all; which rules may mark it is RulesFor's answer, not this one's.
	judged := t.project.JudgedDespiteExclusion()
	excluded := source.Under(home, t.project.Excluded).Judging(judged)

	for _, relative := range append(slices.Clone(t.project.Paths), judged...) {
		dir := home + "/" + strings.Trim(relative, "/")
		if relative == "." {
			dir = home
		}

		for _, path := range source.Sources(dir, excluded) {
			info, err := os.Stat(path)

			// At or after, never strictly after: an mtime has one-second resolution, so a file written in the
			// second the mark was set would otherwise be missed for ever.
			if err == nil && info.ModTime().Unix() >= int64(since) {
				touched = append(touched, stamped{path, info.ModTime().Unix()})
			}
		}
	}

	sort.SliceStable(touched, func(i, j int) bool { return touched[i].mtime > touched[j].mtime })

	for i := 0; i < len(touched) && i < limit; i++ {
		claimed = append(claimed, touched[i].path)
	}

	return claimed, len(touched) > len(claimed)
}

// MarkSeen marks this moment as looked at.
func (t Touched) MarkSeen() {
	t.file.Write(t.file.Read().With(state.Int("marked_at", int(time.Now().Unix()))))
}

// reportedLegend says what the reported-findings list is.
var reportedLegend = &state.Legend{
	About: "Code-commandments — the findings the per-edit check has already named this session, so each is said once.",
	List:  "one finding per line: the file, a tab, then the finding as it was named",
	Safe:  "the findings still standing are named once more",
}

// Reported are the findings the per-edit check has named this session.
type Reported struct {
	file state.File
}

// ReportedIn is the session's list of named findings.
func ReportedIn(space workspace.Workspace) Reported {
	return Reported{state.At(space.Path(".reported-findings"), reportedLegend)}
}

// Unseen are the findings of the file, by skill, that the list does not hold yet; the list keeps the file's
// findings as they stand now, so a finding fixed and made again is named again.
func (r Reported) Unseen(file string, found map[string][]string, skills []string) map[string][]string {
	kept := r.file.Read().Items()
	before := map[string]bool{}
	var others []string

	for _, item := range kept {
		if fileOf(item) == file {
			before[item] = true
		} else {
			others = append(others, item)
		}
	}

	for _, skill := range skills {
		for _, finding := range found[skill] {
			others = append(others, file+"\t"+finding)
		}
	}

	r.file.Write(r.file.Read().WithItems(others))

	unseen := map[string][]string{}

	for _, skill := range skills {
		for _, finding := range found[skill] {
			if !before[file+"\t"+finding] {
				unseen[skill] = append(unseen[skill], finding)
			}
		}
	}

	return unseen
}

func fileOf(item string) string {
	file, _, _ := strings.Cut(item, "\t")

	return file
}
