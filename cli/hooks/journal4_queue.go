package hooks

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"sync/atomic"
	"time"

	atomicfile "github.com/jessegall/code-commandments/cli/atomic"
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

// toldLater leaves an answer a judging gives after its event where the plugin's script delivers it.
func toldLater(answer JournalAnswer, env string, agent int) {
	if queue, queued := Queue4In(os.Getenv(pluginData)); queued {
		_ = queue.Tell(answer, env, agent)
	}
}

func (q Queue4) Tell(answer JournalAnswer, env string, agent int) error {
	shape := answer.Fields()
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

	// the count is padded, so the answers of one millisecond sort in the order they were told
	name := fmt.Sprintf("%d-%d-%012d.json", time.Now().UnixMilli(), os.Getpid(), written.Add(1))

	return atomicfile.Write(filepath.Join(q.path, name), string(said)+"\n")
}
