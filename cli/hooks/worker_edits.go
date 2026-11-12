package hooks

import (
	"os"
	"path/filepath"
	"slices"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scope"
	"github.com/jessegall/code-commandments/cli/warm"
)

const (
	workerEditsFolder = "worker-edits"
	editedInSuffix    = ".checkout"
)

// WorkerEdits are the files each agent's own file_edited events named, by their path from that agent's own root: whose work a file is, which a merge, a rebase or a reset onto a newer base cannot change, because none of them is an edit.
type WorkerEdits struct {
	folder string
}

func WorkerEditsIn(data string) WorkerEdits {
	return WorkerEdits{filepath.Join(data, workerEditsFolder)}
}

func (e WorkerEdits) Keep(agent int, relative string) {
	if slices.Contains(e.Of(agent), relative) || os.MkdirAll(e.folder, 0o755) != nil {
		return
	}

	file, err := os.OpenFile(e.fileOf(agent), os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0o644)
	if err != nil {
		return
	}
	defer file.Close()

	_, _ = file.WriteString(relative + "\n")
}

func (e WorkerEdits) Of(agent int) []string {
	raw, err := os.ReadFile(e.fileOf(agent))
	if err != nil {
		return nil
	}

	named := strings.Split(strings.TrimSpace(string(raw)), "\n")
	slices.Sort(named)

	return slices.Compact(named)
}

func (e WorkerEdits) Clear(agent int) {
	_ = os.Remove(e.fileOf(agent))
}

func (e WorkerEdits) KeepIn(agent int, checkout, relative string) {
	e.Keep(agent, relative)

	if checkout != "" {
		_ = os.WriteFile(e.fileOf(agent)+editedInSuffix, []byte(checkout), 0o644)
	}
}

func (e WorkerEdits) EditorsIn(checkout string) []int {
	marks, _ := filepath.Glob(filepath.Join(e.folder, "*"+editedInSuffix))

	var editors []int

	for _, mark := range marks {
		raw, err := os.ReadFile(mark)
		if err != nil || string(raw) != checkout {
			continue
		}

		if agent, err := strconv.Atoi(strings.TrimSuffix(filepath.Base(mark), editedInSuffix)); err == nil {
			editors = append(editors, agent)
		}
	}

	return editors
}

func (e WorkerEdits) Approve(agent int, approved []string) {
	kept := slices.DeleteFunc(e.Of(agent), func(named string) bool { return slices.Contains(approved, named) })
	if len(kept) == 0 {
		e.Clear(agent)

		return
	}

	_ = os.WriteFile(e.fileOf(agent), []byte(strings.Join(kept, "\n")+"\n"), 0o644)
}

func (e WorkerEdits) fileOf(agent int) string {
	return filepath.Join(e.folder, strconv.Itoa(agent))
}

func keepEditOf(line map[string]any) {
	data := os.Getenv(pluginData)
	agent := asAgent(line["agent"])
	relative := relativeOfEdit(line)

	if data == "" || agent == 0 || relative == "" || !editsKeptFor(line) {
		return
	}

	WorkerEditsIn(data).KeepIn(agent, checkoutRootOf(line), relative)
}

func editsKeptFor(line map[string]any) bool {
	moment, known := Moment4Of(line)

	return known && liveEditsJudgedAt(MomentOf(moment)) && notForThisRole(MomentOf(moment)) == ""
}

func checkoutRootOf(line map[string]any) string {
	checkout, _ := line["checkout"].(map[string]any)

	return asText(checkout["root"])
}

// EditsAsked are the files the agents of one checkout edited, for a run an agent asked for its changes. In the project's own checkout a file is a change only until a commit or a change of branch takes it in, so it leaves the list then; a worker's worktree keeps every file until its work is handed in.
type EditsAsked struct {
	store    WorkerEdits
	editors  []int
	checkout string
	shared   bool
}

func editsAsked(request warm.Request, root string) EditsAsked {
	data := os.Getenv(pluginData)
	if data == "" || !request.Agent || !slices.Contains(request.Judge, "--changes") {
		return EditsAsked{}
	}

	checkout := absolutely(root, checkoutOf(root, request.In))
	store := WorkerEditsIn(data)

	return EditsAsked{store: store, editors: store.EditorsIn(checkout), checkout: checkout,
		shared: strings.TrimRight(git.ProjectRoot(checkout), "/") == checkout}
}

func (a EditsAsked) scope() scope.Edits {
	edits := scope.Edits{Held: len(a.editors) > 0}

	for _, agent := range a.editors {
		for _, relative := range a.standing(agent) {
			if file := filepath.Join(a.checkout, relative); isAFile(file) {
				edits.Files = append(edits.Files, file)
			}
		}
	}

	return edits
}

func (a EditsAsked) standing(agent int) []string {
	named := a.store.Of(agent)
	if !a.shared {
		return named
	}

	changed := git.StillChanged(a.checkout, named)
	a.store.Approve(agent, slices.DeleteFunc(named, func(relative string) bool { return slices.Contains(changed, relative) }))

	return changed
}

func (a EditsAsked) clearedByAPanic() {
	failure := recover()
	if failure == nil {
		return
	}

	for _, agent := range a.editors {
		a.store.Clear(agent)
	}

	panic(failure)
}

func (a EditsAsked) approve() {
	for _, agent := range a.editors {
		a.store.Approve(agent, a.store.Of(agent))
	}
}

func isAFile(path string) bool {
	info, err := os.Stat(path)

	return err == nil && !info.IsDir()
}

func relativeOfEdit(line map[string]any) string {
	fields := eventFields(line)
	file, _ := fields["file"].(map[string]any)

	return firstText(asText(file["relative"]), asText(fields["path"]))
}
