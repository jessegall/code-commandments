package reported

import (
	"encoding/json"
	"os"
	"sort"
	"strings"
	"time"

	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// Filename is the record's name under the project's own folder. It is TRACKED, beside config.json: a report only
// the machine that filed it knows about is worse than none, because the next session reads the sin as active and
// files the same report again, so cli/sync un-ignores it by this name.
const Filename = "reports.json"

// note is written into the record itself, since a reader meeting the file deserves to know what it is and what
// deleting it costs.
const note = "The sins this project has reported to code-commandments. A judge reads a sin listed here as reported " +
	"rather than active, so deleting this file makes every one of them active again and invites a second report of " +
	"each. The flagged line is kept as a hash, never as text: this file is committed and the issues it names are public."

// Record is what a project has reported, by the identity of the sin each report was about.
type Record struct {
	Note    string         `json:"note"`
	Reports map[string]Row `json:"reports"`
}

// Row is one report: the rule whose finding it was about and the sin that rule finds, where the finding stood
// when it was filed, the issue it became, and what was last seen of that issue, so an answer is told once rather
// than at every look. The rule is what the row is keyed by; the sin is for whoever reads the record.
type Row struct {
	Detector   string `json:"detector"`
	Sin        string `json:"sin,omitempty"`
	File       string `json:"file"`
	Line       int    `json:"line"`
	Reason     string `json:"reason"`
	Repository string `json:"repository"`
	Issue      int    `json:"issue"`
	Filed      string `json:"filed"`
	Seen       Seen   `json:"seen"`
}

// Seen is what the last look at the issue found: whether it was still open, how many comments it had, and when it
// was looked at. A row whose issue has not moved since is nothing to tell anyone about again.
type Seen struct {
	State    string `json:"state,omitempty"`
	Comments int    `json:"comments,omitempty"`
	At       string `json:"at,omitempty"`
}

// Path is where the record lives under root.
func Path(root string) string {
	return workspace.At(root, "").Shared(Filename)
}

// Of is the record the project at root keeps. A file that is not there, or that no longer reads as one, is an
// empty record: a judge warns about it, a hook says nothing, and neither may refuse to run over it.
func Of(root string) (held Record, read bool) {
	content, err := os.ReadFile(Path(root))
	if err != nil {
		return Record{Note: note, Reports: map[string]Row{}}, true
	}

	if json.Unmarshal(content, &held) != nil || held.Reports == nil {
		return Record{Note: note, Reports: map[string]Row{}}, false
	}

	held.Note = note

	return held, true
}

// Reported is the report filed about this rule's finding at this place, where there is one.
func (r Record) Reported(rule, root, file string, line int) (Row, bool) {
	id, read := Identity(rule, root, file, line)
	if !read {
		return Row{}, false
	}

	held, reported := r.Reports[id]

	return held, reported
}

// Rows are the reports the record holds, the newest first, so a reader meets what is still being answered before
// what was filed long ago.
func (r Record) Rows() []Row {
	rows := make([]Row, 0, len(r.Reports))
	for _, row := range r.Reports {
		rows = append(rows, row)
	}
	sort.Slice(rows, func(one, other int) bool {
		if rows[one].Filed != rows[other].Filed {
			return rows[one].Filed > rows[other].Filed
		}

		return rows[one].Issue > rows[other].Issue
	})

	return rows
}

// lock is the file a run holds while it changes the record, so two reports filed at once cannot each write the
// record the other read. It is the same advisory hold a sync takes, over this record's own file.
func lock(root string) func() {
	return atomic.Hold(workspace.At(root, "").Shared(".reports.lock"))
}

// Add records a report about the sin at this place. It refuses a place whose own line cannot be read, since every
// such sin of that rule in that file would key alike, and it refuses a second report of a sin already reported,
// naming the issue that has it: an update would forget an issue that may still be open.
func Add(root string, row Row) (Row, error) {
	defer lock(root)()

	id, read := Identity(row.Detector, root, row.File, row.Line)
	if !read {
		return Row{}, &Unreadable{File: row.File, Line: row.Line}
	}

	held, _ := Of(root)
	if already, reported := held.Reports[id]; reported {
		return already, &Already{Row: already}
	}

	row.File = relative(root, row.File)
	row.Reason = firstLine(row.Reason)
	if row.Filed == "" {
		row.Filed = time.Now().UTC().Format(time.RFC3339)
	}
	held.Reports[id] = row

	return row, write(root, held)
}

// Forget drops the report of an issue, so the sin it was about is judged as any other again; ok is false when no
// row names that issue.
func Forget(root string, issue int) (Row, bool, error) {
	defer lock(root)()

	held, _ := Of(root)
	for id, row := range held.Reports {
		if row.Issue != issue {
			continue
		}
		delete(held.Reports, id)

		return row, true, write(root, held)
	}

	return Row{}, false, nil
}

// Saw records what a look at the issue found, so the next look can tell whether it has moved.
func Saw(root string, issue int, seen Seen) error {
	defer lock(root)()

	held, _ := Of(root)
	for id, row := range held.Reports {
		if row.Issue != issue {
			continue
		}
		row.Seen = seen
		held.Reports[id] = row

		return write(root, held)
	}

	return nil
}

// write puts the record back whole, as the record is read whole.
func write(root string, held Record) error {
	held.Note = note
	content, err := json.MarshalIndent(held, "", "  ")
	if err != nil {
		return err
	}

	return atomic.Write(Path(root), string(content)+"\n")
}

// relative is the file as the project names it, so two checkouts read one record alike.
func relative(root, file string) string {
	if trimmed := strings.TrimPrefix(file, strings.TrimRight(root, "/")+"/"); trimmed != file {
		return trimmed
	}

	return file
}

// firstLine is a reason's first line, which is all a row keeps of it: the issue holds the rest.
func firstLine(reason string) string {
	if line, _, cut := strings.Cut(strings.TrimSpace(reason), "\n"); cut {
		return strings.TrimSpace(line)
	}

	return strings.TrimSpace(reason)
}
