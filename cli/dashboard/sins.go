// Package dashboard is what the agent journal's plugin page shows of the tool: the findings of the last
// judge runs, kept file by file, and the Sins dashboard drawn from them.
package dashboard

import (
	"encoding/json"
	"os"
	"path/filepath"
	"sort"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/sins"
)

const (
	// Name is the dashboard's id in the plugin's manifest.
	Name = "sins"

	// File is where the dashboard is written, under the state folder.
	File = "dashboards/" + Name + ".json"

	findingsFile = "dashboards/findings.json"
	top          = 20
)

// Stored is one finding as the store keeps it.
type Stored struct {
	Sin   string `json:"sin"`
	Skill string `json:"skill"`
	Path  string `json:"path"`
	File  string `json:"file"`
	Line  int    `json:"line"`
	Scope string `json:"scope"`
}

// StoredOf is a finding as the store keeps it, its file relative to the project root.
func StoredOf(finding engine.Finding, root string) Stored {
	path := finding.File

	if real, err := filepath.EvalSymlinks(finding.File); err == nil {
		if absolute, err := filepath.Abs(real); err == nil {
			path = absolute
		}
	}

	file := strings.TrimPrefix(path, strings.TrimRight(root, "/")+"/")
	line, _ := strconv.Atoi(finding.Location[strings.LastIndex(finding.Location, ":")+1:])

	return Stored{finding.Sin, finding.Skill, path, file, line, finding.Scope}
}

// Record keeps the run's findings: every finding in a file this run judged is replaced, the rest kept, and
// the dashboard is drawn again. judged is nil for an unscoped run, which replaces everything.
func Record(space workspace.Workspace, findings []engine.Finding, judged map[string]bool) error {
	var all []Stored

	if judged != nil {
		for _, stored := range storedIn(space) {
			if !judged[stored.Path] {
				all = append(all, stored)
			}
		}
	}

	for _, finding := range findings {
		all = append(all, StoredOf(finding, space.Root()))
	}

	if all == nil {
		all = []Stored{}
	}

	store, err := Pretty(all, false)
	if err != nil {
		return err
	}

	if err := atomic.Write(space.Cache(findingsFile), store); err != nil {
		return err
	}

	dashboard, err := Pretty(Render(all), true)
	if err != nil {
		return err
	}

	return atomic.Write(space.Cache(File), dashboard)
}

func storedIn(space workspace.Workspace) []Stored {
	raw, err := os.ReadFile(space.Cache(findingsFile))
	if err != nil {
		return nil
	}

	var stored []Stored
	json.Unmarshal(raw, &stored)

	return stored
}

// Opening is the dashboard page that opens on a stored finding.
func Opening(finding Stored) string {
	return Name + "/" + fileOf(finding.Sin, finding.File)
}

// group is one sin's findings, file by file, in the order they were first found.
type group struct {
	sin   string
	files []string
	found map[string][]Stored
}

func (g group) count() int {
	count := 0

	for _, found := range g.found {
		count += len(found)
	}

	return count
}

// Render draws the dashboard: an overview of the most common sins, a page per sin, and a page per sin in
// each file.
func Render(findings []Stored) *Object {
	groups := bySin(findings)
	pages := NewObject("overview", NewObject("title", "Overview", "view", overview(findings, groups)))

	for _, g := range groups {
		pages.Set("sin/"+g.sin, NewObject("title", g.sin, "view", sinPage(g)))

		for _, file := range g.files {
			pages.Set(fileOf(g.sin, file), NewObject("title", g.sin+" in "+filepath.Base(file), "view", filePage(g.sin, file, g.found[file])))
		}
	}

	return NewObject("title", "Code Commandments", "start", "overview", "pages", pages)
}

func bySin(findings []Stored) []group {
	var groups []group
	at := map[string]int{}

	for _, finding := range findings {
		i, seen := at[finding.Sin]

		if !seen {
			i = len(groups)
			at[finding.Sin] = i
			groups = append(groups, group{sin: finding.Sin, found: map[string][]Stored{}})
		}

		if _, known := groups[i].found[finding.File]; !known {
			groups[i].files = append(groups[i].files, finding.File)
		}

		groups[i].found[finding.File] = append(groups[i].found[finding.File], finding)
	}

	sort.SliceStable(groups, func(i, j int) bool {
		return groups[i].count() > groups[j].count()
	})

	return groups
}

func overview(findings []Stored, groups []group) *Object {
	total := len(findings)
	tone, filesTone := "danger", "warn"

	if total == 0 {
		tone, filesTone = "good", "good"
	}

	tiles := NewObject("type", "row", "gap", 12, "children", []any{
		NewObject("type", "stat", "label", "Sins", "value", strconv.Itoa(total), "tone", tone, "note", strconv.Itoa(len(groups))+" different rules"),
		NewObject("type", "stat", "label", "Files", "value", strconv.Itoa(distinct(findings, func(s Stored) string { return s.File })), "tone", filesTone),
		NewObject("type", "stat", "label", "Skills", "value", strconv.Itoa(distinct(findings, func(s Stored) string { return s.Skill })), "note", "the skills that teach the fixes"),
	})

	if total == 0 {
		return NewObject("type", "stack", "gap", 16, "children", []any{tiles, NewObject("type", "text", "body", "No sins in the last judged files.")})
	}

	bars := []any{}

	for _, g := range groups[:min(top, len(groups))] {
		files := strconv.Itoa(len(g.files)) + " files"
		if len(g.files) == 1 {
			files = "1 file"
		}

		bars = append(bars, NewObject("label", g.sin, "value", g.count(), "note", files, "tone", "danger", "open", "sin/"+g.sin))
	}

	return NewObject("type", "stack", "gap", 16, "children", []any{
		tiles,
		NewObject("type", "bars", "title", "Most common sins", "unit", "sins", "items", bars),
	})
}

func sinPage(g group) *Object {
	files := append([]string(nil), g.files...)

	sort.SliceStable(files, func(i, j int) bool {
		return len(g.found[files[i]]) > len(g.found[files[j]])
	})

	rows := []any{}

	for _, file := range files {
		rows = append(rows, NewObject("cells", []string{file, strconv.Itoa(len(g.found[file]))}, "open", fileOf(g.sin, file)))
	}

	children := append([]any{NewObject("type", "heading", "text", g.sin, "level", 1)}, explanation(g.sin)...)

	return NewObject("type", "stack", "gap", 16, "children", append(children, NewObject("type", "table", "columns", []string{"File", "Sins"}, "rows", rows)))
}

func filePage(sin, file string, found []Stored) *Object {
	found = append([]Stored(nil), found...)

	sort.SliceStable(found, func(i, j int) bool {
		return found[i].Line < found[j].Line
	})

	places := []any{}

	for _, finding := range found {
		places = append(places, NewObject("type", "file", "path", file, "line", finding.Line, "label", "line "+strconv.Itoa(finding.Line)+" — "+finding.Scope))
	}

	title := strconv.Itoa(len(found)) + " places"
	if len(found) == 1 {
		title = "Where"
	}

	children := []any{
		NewObject("type", "heading", "text", sin+" in "+file, "level", 1),
		NewObject("type", "card", "title", title, "children", places),
	}

	return NewObject("type", "stack", "gap", 16, "children", append(children, explanation(sin)...))
}

func explanation(name string) []any {
	for _, sin := range sins.All() {
		rule := sin.Definition()

		if rule.Name != name {
			continue
		}

		facts := []any{
			NewObject("type", "fact", "label", "What it is", "body", rule.Description),
			NewObject("type", "fact", "label", "The rule", "body", rule.Rule),
		}

		if rule.Suggestion != "" {
			facts = append(facts, NewObject("type", "fact", "label", "How to fix it", "body", rule.Suggestion))
		}

		return []any{
			NewObject("type", "card", "title", "About this rule", "children", facts),
			NewObject("type", "code", "text", "commandments info "+name),
		}
	}

	return []any{NewObject("type", "text", "body", "A rule of this project's own — its description lives in `.commandments/custom/`.")}
}

func distinct(findings []Stored, key func(Stored) string) int {
	seen := map[string]bool{}

	for _, finding := range findings {
		seen[key(finding)] = true
	}

	return len(seen)
}

func fileOf(sin, file string) string {
	return "sin/" + sin + "/" + file
}
