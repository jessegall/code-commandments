package backend

import (
	"bufio"
	"compress/gzip"
	"encoding/json"
	"os"
	"sync"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/scribes"
)

// casesFile holds every source PHP's backend scribe tests read, byte for byte, with the fix, whether it rewrote and
// how many sins its detector finds, as the PHP tool recorded them before it was removed; hintsFile, what its
// DataHintScribe rewrote in every hints project.
const (
	casesFile = "testdata/scribe-cases.jsonl.gz"
	hintsFile = "testdata/hint-answers.json.gz"
)

var (
	hintAnswersOnce sync.Once
	hintAnswers     map[string]map[string]string
)

func loadHintAnswers() {
	hintAnswers = map[string]map[string]string{}
	file, err := os.Open(hintsFile)
	if err != nil {
		return
	}
	defer file.Close()
	unzipped, err := gzip.NewReader(file)
	if err != nil {
		return
	}
	json.NewDecoder(unzipped).Decode(&hintAnswers)
}

// scribeCase is a source one of PHP's scribe tests read, and what the test's scribe made of it.
type recordedCase struct {
	Test     string `json:"test"`
	Detector string `json:"detector"`
	Input    string `json:"input"`
	Fixed    string `json:"fixed"`
	Rewrote  bool   `json:"rewrote"`
	Findings int    `json:"findings"`
}

// Every source PHP's backend scribe tests read is fixed here byte for byte as PHP fixes it, and its detector finds
// as many sins in it.
func TestEachScribeFixesEverySourcePHPsTestsReadAsPHPDoes(t *testing.T) {
	for _, recorded := range loadCases(t) {
		t.Run(recorded.Test, func(t *testing.T) {
			detector := backendDetector(t, recorded.Detector)
			scribe, ok := scribes.ScribeFor(detector)
			if !ok {
				t.Fatalf("no scribe fixes %s", recorded.Detector)
			}
			fixing := scribeCase{detector: detector, scribe: scribe}
			if found := len(fixing.findings(t, recorded.Input)); found != recorded.Findings {
				t.Errorf("%s finds %d sins, PHP %d", recorded.Detector, found, recorded.Findings)
			}
			rewrites := fixing.rewrites(t, recorded.Input)
			if rewrote := rewrites.Len() > 0; rewrote != recorded.Rewrote {
				t.Fatalf("rewrote %v, PHP %v", rewrote, recorded.Rewrote)
			}
			if fixed := fixing.fix(t, recorded.Input); fixed != recorded.Fixed {
				t.Errorf("fixed differs:\n--- go\n%s\n--- php\n%s", fixed, recorded.Fixed)
			}
		})
	}
}

func loadCases(t *testing.T) []recordedCase {
	t.Helper()
	file, err := os.Open(casesFile)
	if err != nil {
		t.Fatal(err)
	}
	defer file.Close()
	unzipped, err := gzip.NewReader(file)
	if err != nil {
		t.Fatal(err)
	}
	var cases []recordedCase
	scanner := bufio.NewScanner(unzipped)
	scanner.Buffer(nil, 64<<20)
	for scanner.Scan() {
		var recorded recordedCase
		if err := json.Unmarshal(scanner.Bytes(), &recorded); err != nil {
			t.Fatal(err)
		}
		cases = append(cases, recorded)
	}

	return cases
}

// backendDetector is the backend detector the catalog holds under the name.
func backendDetector(t *testing.T, name string) detectors.Detector {
	t.Helper()
	for _, detector := range detectors.Of(catalog.Backend) {
		if catalog.Name(detector) == name {
			return detector
		}
	}
	t.Fatalf("no backend detector %s", name)

	return nil
}
