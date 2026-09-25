package frontend

import (
	"bufio"
	"compress/gzip"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"flag"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"sync"
	"testing"

	"github.com/jessegall/code-commandments/engine/php/shop"
)

// record asks PHP again for every answer the tests hold the scribes to, and commits what it says.
var record = flag.Bool("record", false, "ask the PHP tool again and record its answers")

// answerFile holds what PHP's frontend detector step rewrote for each project the tests give it; digestFile, the
// digest of the PHP sources those answers came from.
const (
	answerFile = "testdata/php-answers.jsonl.gz"
	digestFile = "testdata/php-answers.digest"
)

// answerSources are what the recorded answers come from: the PHP tool, and the lock that pins its dependencies.
var answerSources = []string{"src", "composer.lock"}

// answer is PHP's rewrite of one project by one detector's step, keyed by both.
type answer struct {
	Key      string            `json:"key"`
	Detector string            `json:"detector"`
	Rewrites map[string]string `json:"rewrites"`
}

var (
	answersOnce sync.Once
	answers     map[string]map[string]string
	recordedMu  sync.Mutex
	recorded    = map[string]answer{}
)

func TestMain(m *testing.M) {
	flag.Parse()
	code := m.Run()
	if *record && code == 0 {
		if err := writeAnswers(); err != nil {
			os.Stderr.WriteString(err.Error() + "\n")
			code = 1
		}
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

// phpAnswer is what PHP's step for the detector rewrites in the project: asked live when recording, else as recorded.
func phpAnswer(t *testing.T, detectorClass string, files map[string]string, dir string) map[string]string {
	t.Helper()
	key := answerKey(detectorClass, files)
	if *record {
		rewrites := phpRewrites(t, detectorClass, dir)
		recordedMu.Lock()
		recorded[key] = answer{Key: key, Detector: detectorClass, Rewrites: rewrites}
		recordedMu.Unlock()

		return rewrites
	}
	answersOnce.Do(loadAnswers)
	rewrites, ok := answers[key]
	if !ok {
		t.Fatalf("PHP's answer for this %s project is not recorded: run go generate ./scribes/frontend", detectorClass)
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

func writeAnswers() error {
	keys := make([]string, 0, len(recorded))
	for key := range recorded {
		keys = append(keys, key)
	}
	sort.Strings(keys)
	file, err := os.Create(answerFile)
	if err != nil {
		return err
	}
	defer file.Close()
	zipped := gzip.NewWriter(file)
	for _, key := range keys {
		line, err := json.Marshal(recorded[key])
		if err != nil {
			return err
		}
		zipped.Write(append(line, '\n'))
	}
	if err := zipped.Close(); err != nil {
		return err
	}
	digest, err := shop.DigestOf(answerSources...)
	if err != nil {
		return err
	}

	return os.WriteFile(digestFile, []byte(digest+"\n"), 0o644)
}

func TestTheRecordedPHPAnswersAreGeneratedFromTodaysSources(t *testing.T) {
	if *record {
		t.Skip("recording")
	}
	committed, err := os.ReadFile(digestFile)
	if err != nil {
		t.Fatal(err)
	}
	digest, err := shop.DigestOf(answerSources...)
	if err != nil {
		t.Fatal(err)
	}
	if strings.TrimSpace(string(committed)) != digest {
		t.Fatalf("the PHP tool changed since %s was recorded: run go generate ./scribes/frontend", filepath.Base(answerFile))
	}
}
