// Package task is the work in front of a session: numbered tasks, one markdown file each, moved between the queue, active and history folders.
package task

import (
	"fmt"
	"os"
	"path/filepath"
	"regexp"
	"slices"
	"sort"
	"strconv"
	"strings"
	"time"

	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/folder"
	"github.com/jessegall/code-commandments/cli/layout"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// State is the folder a task sits in, which is its state.
type State string

const (
	Queued State = "queue"
	Active State = "active"
	Done   State = "history"
)

var Live = []State{Queued, Active}

func (s State) Entered() string {
	switch s {
	case Active:
		return "started"
	case Done:
		return "done"
	default:
		return "queued"
	}
}

func (s State) Mark() string {
	switch s {
	case Active:
		return "●"
	case Done:
		return "✓"
	default:
		return "○"
	}
}

// ID is a task's address: its number, then its subtask numbers (`002.1`).
type ID []int

const idWidth = 3

func ParseID(text string) (ID, bool) {
	var numbers ID

	for _, segment := range strings.Split(layout.Trim(text), ".") {
		number, err := strconv.Atoi(segment)

		if segment == "" || strings.Trim(segment, "0123456789") != "" || err != nil || number < 1 {
			return nil, false
		}

		numbers = append(numbers, number)
	}

	return numbers, true
}

func (id ID) Child(number int) ID {
	return append(slices.Clone(id), number)
}

func (id ID) IsChildOf(other ID) bool {
	return len(id) == len(other)+1 && slices.Equal(id[:len(other)], other)
}

func (id ID) Number() int {
	if len(id) == 0 {
		return 0
	}

	return id[len(id)-1]
}

func Compare(a, b ID) int {
	for depth := range min(len(a), len(b)) {
		if a[depth] != b[depth] {
			return a[depth] - b[depth]
		}
	}

	return len(a) - len(b)
}

func (id ID) Render() string {
	if len(id) == 0 {
		return ""
	}

	rendered := fmt.Sprintf("%0*d", idWidth, id[0])

	for _, number := range id[1:] {
		rendered += "." + strconv.Itoa(number)
	}

	return rendered
}

const (
	extension = ".md"
	separator = "-"
	slugWidth = 48
	stamp     = "2006-01-02 15:04"
	reason    = " — "
)

var notSlug = regexp.MustCompile(`[^a-z0-9]+`)

// Task is one task file.
type Task struct {
	ID    ID
	State State
	Path  string
	Title string
	Why   string
}

func At(path string, state State) (Task, bool) {
	name := strings.TrimSuffix(filepath.Base(path), extension)
	address, _, _ := strings.Cut(name, separator)
	id, parsed := ParseID(address)

	if !parsed {
		return Task{}, false
	}

	body := contentsOf(path)

	return Task{ID: id, State: state, Path: path, Title: titleIn(body, name), Why: whyIn(body)}, true
}

func Open(dir string, id ID, title, why string, now time.Time) (Task, bool) {
	path := dir + "/" + id.Render() + separator + slug(title) + extension
	blocks := []string{"# " + title}

	if why != "" {
		blocks = append(blocks, why)
	}

	blocks = append(blocks, "- "+Queued.Entered()+" "+now.UTC().Format(stamp))

	if atomic.Write(path, strings.Join(blocks, "\n\n")+"\n") != nil {
		return Task{}, false
	}

	return At(path, Queued)
}

func (t Task) Body() string {
	return contentsOf(t.Path)
}

func (t Task) Touched() int64 {
	return folder.Modified(t.Path)
}

func (t Task) Log(state State, why string, now time.Time) error {
	line := "- " + state.Entered() + " " + now.UTC().Format(stamp)

	if why != "" {
		line += reason + why
	}

	return atomic.Write(t.Path, strings.TrimRight(t.Body(), "\n")+"\n"+line+"\n")
}

func (t Task) Outcome() (string, bool) {
	outcome, found := "", false

	for _, line := range strings.Split(t.Body(), "\n") {
		if !strings.HasPrefix(line, "- ") {
			continue
		}

		_, said, has := strings.Cut(line, reason)
		outcome, found = layout.Trim(said), has
	}

	return outcome, found
}

func (t Task) Line() string {
	line := strings.Repeat("  ", len(t.ID)-1) + t.State.Mark() + " " + t.ID.Render() + "  " + t.Title

	if t.Why != "" {
		line += " — " + t.Why
	}

	return line
}

func slug(title string) string {
	slug := strings.Trim(notSlug.ReplaceAllString(strings.ToLower(title), separator), separator)

	if slug == "" {
		return "task"
	}

	return slug[:min(slugWidth, len(slug))]
}

func titleIn(body, name string) string {
	for _, line := range strings.Split(body, "\n") {
		if title, found := strings.CutPrefix(line, "# "); found {
			return layout.Trim(title)
		}
	}

	return name
}

func whyIn(body string) string {
	for _, line := range strings.Split(body, "\n") {
		line = layout.Trim(line)

		if line != "" && !strings.HasPrefix(line, "#") && !strings.HasPrefix(line, "-") {
			return line
		}
	}

	return ""
}

func contentsOf(path string) string {
	raw, _ := os.ReadFile(path)

	return string(raw)
}

// Tasks are the tasks kept under one folder.
type Tasks struct {
	Root string
}

func InSession(space workspace.Workspace) Tasks {
	return Tasks{space.Path("tasks")}
}

func (ts Tasks) folder(state State) string {
	return ts.Root + "/" + string(state)
}

func (ts Tasks) All() []Task {
	return ts.InState(Queued, Active, Done)
}

func (ts Tasks) InState(states ...State) []Task {
	var tasks []Task

	for _, state := range states {
		paths, _ := filepath.Glob(ts.folder(state) + "/*" + extension)

		for _, path := range paths {
			if task, read := At(path, state); read {
				tasks = append(tasks, task)
			}
		}
	}

	sort.SliceStable(tasks, func(i, j int) bool {
		return Compare(tasks[i].ID, tasks[j].ID) < 0
	})

	return tasks
}

func (ts Tasks) Find(id ID) (Task, bool) {
	for _, task := range ts.All() {
		if slices.Equal(task.ID, id) {
			return task, true
		}
	}

	return Task{}, false
}

func (ts Tasks) Add(under ID, title, why string, now time.Time) (Task, bool) {
	highest := 0

	for _, task := range ts.All() {
		if task.ID.IsChildOf(under) {
			highest = max(highest, task.ID.Number())
		}
	}

	return Open(ts.folder(Queued), under.Child(highest+1), title, why, now)
}

func (ts Tasks) Move(task Task, to State, why string, now time.Time) (Task, bool) {
	dir := ts.folder(to)

	if os.MkdirAll(dir, 0o777) != nil {
		return Task{}, false
	}

	path := dir + "/" + filepath.Base(task.Path)

	if os.Rename(task.Path, path) != nil {
		return Task{}, false
	}

	task.Path, task.State = path, to
	task.Log(to, why, now)

	return task, true
}
