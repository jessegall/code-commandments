package hooks

import (
	"encoding/json"
	"os"
	"path/filepath"
	"strconv"
	"sync/atomic"
	"time"
)

// queueFolder is the plugin's own queue inside the folder the journal gave it: one file per answer it has to make
// later. The journal drains every plugin's queue at each of the agent's hooks, oldest file first, and deletes what
// it read, so a judge that takes a minute writes its findings here and they reach the agent at its next tool use.
const queueFolder = "queue"

// written counts the files this process has written, so two in the same millisecond still sort in the order they
// were written.
var written atomic.Uint64

// Queue4 is that queue.
type Queue4 struct{ path string }

// Queue4In is the queue in the plugin's data folder; no queue when the journal named no folder, which is how a
// journal that drains none says so.
func Queue4In(folder string) (Queue4, bool) {
	if folder == "" {
		return Queue4{}, false
	}

	return Queue4{filepath.Join(folder, queueFolder)}, true
}

// Path is the folder the queue is kept in.
func (q Queue4) Path() string {
	return q.path
}

// Tell writes what was worked out after the answer went back: one file, the answer's own shape with the environment
// it is for, so the journal hands the words to that environment's agent at its next hook. A refusal is told as
// words too — nothing here can stop a tool that has already run, and the reason is worth more said than dropped.
func (q Queue4) Tell(answer JournalAnswer, env string) error {
	shape := told(answer).Shape4()
	if len(shape) <= 1 {
		return nil
	}
	if env != "" {
		shape["env"] = env
	}

	said, err := json.Marshal(shape)
	if err != nil {
		return err
	}
	if err := os.MkdirAll(q.path, 0o755); err != nil {
		return err
	}

	// The journal reads whole files and deletes them, so a file is named only once it is complete: it is written
	// under a name the drain ignores and renamed into place, which no reader can catch half-written.
	name := filepath.Join(q.path, strconv.FormatInt(time.Now().UnixMilli(), 10)+"-"+strconv.FormatUint(written.Add(1), 10))
	if err := os.WriteFile(name+".tmp", append(said, '\n'), 0o644); err != nil {
		return err
	}

	return os.Rename(name+".tmp", name+".json")
}

// told is the answer with its refusal turned into words. The journal cannot refuse a tool that has already run, so a
// refusal here would be recorded as unanswered and its reason lost, and the reason is the whole of what it had to
// say.
func told(answer JournalAnswer) JournalAnswer {
	if answer.Refuse == nil {
		return answer
	}

	words := *answer.Refuse
	if answer.Whisper != nil {
		words += "\n\n" + *answer.Whisper
	}

	answer.Refuse, answer.Whisper = nil, &words

	return answer
}
