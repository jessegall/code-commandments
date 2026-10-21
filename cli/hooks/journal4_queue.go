package hooks

import (
	"bytes"
	"encoding/json"
	"os"
	"path/filepath"
)

// queueLines is the plugin's own queue inside the folder the journal gave it: one JSON object per line, appended,
// which the journal drains at each of the agent's hooks and hands out on that hook's answer. The journal truncates
// the file under a lock after every drain, so a line is written whole, appended, and never read back by us.
const queueLines = "queue.jsonl"

// Queue4 is that queue.
type Queue4 struct{ path string }

// Queue4In is the queue in the plugin's data folder; no queue when the journal named no folder, which is how an
// older journal says it drains none.
func Queue4In(folder string) (Queue4, bool) {
	if folder == "" {
		return Queue4{}, false
	}

	return Queue4{filepath.Join(folder, queueLines)}, true
}

// Path is the file the queue is written to.
func (q Queue4) Path() string {
	return q.path
}

// Tell writes what a judge worked out: the advice as context the agent's next answer carries, and every sin as the
// card it is raised under. A refusal is told as context too — the plugin holds no hook, so nothing of ours can
// stop a write, and the reason is worth more to the agent said than dropped.
func (q Queue4) Tell(answer JournalAnswer) error {
	var lines []map[string]any
	for _, said := range []*string{answer.Refuse, answer.Whisper} {
		if said != nil && *said != "" {
			lines = append(lines, map[string]any{"kind": "context", "text": *said})
		}
	}

	for _, raise := range answer.Raises {
		if raise.Key == "" {
			continue
		}
		lines = append(lines, map[string]any{"kind": "card", "card": map[string]any{"kind": raise.Event, "key": raise.Key, "label": raise.Brief}})
	}

	for _, sin := range answer.Repented {
		lines = append(lines, map[string]any{"kind": "card", "card": map[string]any{"kind": "sin-resolved", "key": sin.Key, "label": "Repented: " + sin.Shown}})
	}

	return q.append(lines)
}

// append writes each line whole, in the order given, so a drain that reads the file mid-write still reads only
// lines nothing is left of.
func (q Queue4) append(lines []map[string]any) error {
	if len(lines) == 0 {
		return nil
	}
	if err := os.MkdirAll(filepath.Dir(q.path), 0o755); err != nil {
		return err
	}

	file, err := os.OpenFile(q.path, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0o644)
	if err != nil {
		return err
	}
	defer file.Close()

	for _, line := range lines {
		var out bytes.Buffer
		encoder := json.NewEncoder(&out)
		encoder.SetEscapeHTML(false)
		if encoder.Encode(line) != nil {
			continue
		}
		if _, err := file.Write(out.Bytes()); err != nil {
			return err
		}
	}

	return nil
}
