package hooks

import (
	"encoding/json"
	"os"
	"path/filepath"
	"strconv"
	"sync/atomic"
	"time"
)

// toldFolder holds the answers a judging gives after its event, in the binary's own fields; the plugin's script shapes
// each into the journal's queue, which the journal drains at the agent's next hook.
const toldFolder = "told"

var written atomic.Uint64

// Queue4 is where the answers a judging gives after its event wait for the plugin's script.
type Queue4 struct{ path string }

func Queue4In(folder string) (Queue4, bool) {
	if folder == "" {
		return Queue4{}, false
	}

	return Queue4{filepath.Join(folder, toldFolder)}, true
}

func (q Queue4) Path() string {
	return q.path
}

func (q Queue4) Tell(answer JournalAnswer, env string, agent int) error {
	shape := told(answer).Fields()
	if len(shape) == 0 {
		return nil
	}
	if env != "" {
		shape["env"] = env
	}
	if agent != 0 {
		shape["agent"] = agent
	}

	said, err := json.Marshal(shape)
	if err != nil {
		return err
	}
	if err := os.MkdirAll(q.path, 0o755); err != nil {
		return err
	}

	name := filepath.Join(q.path, strconv.FormatInt(time.Now().UnixMilli(), 10)+"-"+strconv.Itoa(os.Getpid())+"-"+strconv.FormatUint(written.Add(1), 10))
	if err := os.WriteFile(name+".tmp", append(said, '\n'), 0o644); err != nil {
		return err
	}

	return os.Rename(name+".tmp", name+".json")
}

func told(answer JournalAnswer) JournalAnswer {
	if answer.Refuse == nil {
		return answer
	}

	words := *answer.Refuse
	if answer.Whisper != nil && *answer.Whisper != words {
		words += "\n\n" + *answer.Whisper
	}

	answer.Refuse, answer.Whisper = nil, &words

	return answer
}
