package hooks

import (
	"os"
	"path/filepath"
	"slices"
	"strconv"
	"strings"
)

const workerEditsFolder = "worker-edits"

// WorkerEdits are the files each agent's own file_edited events named, by their path from that agent's own root: whose work a file is, which a merge, a rebase or a reset onto a newer base cannot change, because none of them is an edit.
type WorkerEdits struct {
	folder string
}

func WorkerEditsIn(data string) WorkerEdits {
	return WorkerEdits{filepath.Join(data, workerEditsFolder)}
}

func (e WorkerEdits) Keep(agent int, relative string) {
	if os.MkdirAll(e.folder, 0o755) != nil {
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

func (e WorkerEdits) fileOf(agent int) string {
	return filepath.Join(e.folder, strconv.Itoa(agent))
}

func keepEditOf(line map[string]any) {
	data := os.Getenv(pluginData)
	agent := asAgent(line["agent"])
	relative := relativeOfEdit(line)

	if data == "" || agent == 0 || relative == "" {
		return
	}

	WorkerEditsIn(data).Keep(agent, relative)
}

func relativeOfEdit(line map[string]any) string {
	fields := eventFields(line)
	file, _ := fields["file"].(map[string]any)

	return firstText(asText(file["relative"]), asText(fields["path"]))
}
