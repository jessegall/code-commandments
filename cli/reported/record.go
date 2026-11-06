package reported

import (
	"encoding/json"
	"os"
	"sort"
	"strconv"
	"strings"
	"time"

	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/workspace"
)

const Filename = "reports.json"

const note = "The sins this project has reported to code-commandments. A judge reads a sin listed here as reported " +
	"rather than active, so deleting this file makes every one of them active again and invites a second report of " +
	"each. The flagged line is kept as a hash, never as text: this file is committed and the issues it names are public."

// Record is what a project has reported, by the identity of the sin each report was about.
type Record struct {
	Note    string         `json:"note"`
	Reports map[string]Row `json:"reports"`
}

// Row is one report: the rule whose finding it was about and the sin that rule finds, where the finding stood when it was filed, the issue it became, and what was last seen of that issue, so an answer is told once rather than at every look.
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
	Accepted   string `json:"accepted,omitempty"`
}

// Seen is what the last look at the issue found: whether it was still open, how many comments it had, and when it was looked at.
type Seen struct {
	State    string `json:"state,omitempty"`
	Comments int    `json:"comments,omitempty"`
	At       string `json:"at,omitempty"`
}

func Path(root string) string {
	return workspace.At(root, "").Shared(Filename)
}

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

func (r Record) Reported(rule, root, file string, line int) (Row, bool) {
	id, read := Identity(rule, root, file, line)
	if !read {
		return Row{}, false
	}

	held, reported := r.Reports[id]

	return held, reported
}

func (r Row) Where() string {
	said := "issue " + strconv.Itoa(r.Issue)
	if r.Repository != "" {
		said = "https://github.com/" + r.Repository + "/issues/" + strconv.Itoa(r.Issue)
	}

	if day, _, cut := strings.Cut(r.Filed, "T"); cut {
		return said + ", filed " + day
	}

	return said
}

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

func lock(root string) func() {
	return atomic.Hold(workspace.At(root, "").Shared(".reports.lock"))
}

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

func Accept(root string, issue int, why string) (Row, bool, error) {
	defer lock(root)()

	held, _ := Of(root)
	for id, row := range held.Reports {
		if row.Issue != issue {
			continue
		}
		row.Accepted = firstLine(why)
		held.Reports[id] = row

		return row, true, write(root, held)
	}

	return Row{}, false, nil
}

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

func write(root string, held Record) error {
	held.Note = note
	content, err := json.MarshalIndent(held, "", "  ")
	if err != nil {
		return err
	}

	return atomic.Write(Path(root), string(content)+"\n")
}

func relative(root, file string) string {
	if trimmed := strings.TrimPrefix(file, strings.TrimRight(root, "/")+"/"); trimmed != file {
		return trimmed
	}

	return file
}

func firstLine(reason string) string {
	if line, _, cut := strings.Cut(strings.TrimSpace(reason), "\n"); cut {
		return strings.TrimSpace(line)
	}

	return strings.TrimSpace(reason)
}
