package frontend

import (
	"bufio"
	"compress/gzip"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"os"
	"sort"
	"sync"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	engine "github.com/jessegall/code-commandments/engine/frontend"
	"github.com/jessegall/code-commandments/scribes"
)

// answerFile holds what PHP's frontend detector step rewrote for each project the tests give it, as the PHP tool
// recorded it before it was removed.
const answerFile = "testdata/php-answers.jsonl.gz"

// answer is PHP's rewrite of one project by one detector's step, keyed by both.
type answer struct {
	Key      string            `json:"key"`
	Detector string            `json:"detector"`
	Rewrites map[string]string `json:"rewrites"`
}

var (
	answersOnce sync.Once
	answers     map[string]map[string]string
)

var (
	bridgeOnce   sync.Once
	bridgeServer *bridge.Server
	bridgeErr    error
)

// served is a scanner over the one frontend bridge the package's tests share, started on first use.
func served(t *testing.T) *scribes.Scanner {
	t.Helper()
	bridgeOnce.Do(func() {
		command, err := engine.Here().Command()
		if err != nil {
			bridgeErr = err

			return
		}
		bridgeServer, bridgeErr = bridge.Serve(command)
	})
	if bridgeErr != nil {
		t.Fatal(bridgeErr)
	}

	return scribes.Over(bridgeServer, engine.Over)
}

func TestMain(m *testing.M) {
	code := m.Run()
	if bridgeServer != nil {
		bridgeServer.Close()
	}
	os.Exit(code)
}

// answerKey names a project for one detector: the detector, and every file's path and text.
func answerKey(detectorClass string, files map[string]string) string {
	paths := make([]string, 0, len(files))
	for path := range files {
		paths = append(paths, path)
	}
	sort.Strings(paths)
	hash := sha256.New()
	hash.Write([]byte(detectorClass + "\x00"))
	for _, path := range paths {
		hash.Write([]byte(path + "\x00" + files[path] + "\x00"))
	}

	return hex.EncodeToString(hash.Sum(nil))
}

// phpAnswer is what PHP's step for the detector rewrote in the project, as recorded.
func phpAnswer(t *testing.T, detectorClass string, files map[string]string) map[string]string {
	t.Helper()
	answersOnce.Do(loadAnswers)
	rewrites, ok := answers[answerKey(detectorClass, files)]
	if !ok {
		t.Fatalf("PHP's answer for this %s project is not recorded", detectorClass)
	}

	return rewrites
}

func loadAnswers() {
	answers = map[string]map[string]string{}
	file, err := os.Open(answerFile)
	if err != nil {
		return
	}
	defer file.Close()
	unzipped, err := gzip.NewReader(file)
	if err != nil {
		return
	}
	scanner := bufio.NewScanner(unzipped)
	scanner.Buffer(nil, 64<<20)
	for scanner.Scan() {
		var line answer
		if json.Unmarshal(scanner.Bytes(), &line) == nil {
			answers[line.Key] = line.Rewrites
		}
	}
}

