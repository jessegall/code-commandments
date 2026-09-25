package backend

import (
	"bufio"
	"compress/gzip"
	"encoding/json"
	"flag"
	"os"
	"os/exec"
	"strings"
	"sync"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine/php/shop"
	"github.com/jessegall/code-commandments/scribes"
)

// record runs PHP's backend scribe tests again, recording every source they read and what PHP makes of it, and asks
// PHP's DataHintScribe again for every hints project.
var record = flag.Bool("record", false, "run PHP's scribe tests again and record every case")

// casesFile holds every source PHP's backend scribe tests read, byte for byte, with the fix, whether it rewrote and
// how many sins its detector finds; digestFile, the digest of what they were recorded from.
const (
	casesFile  = "testdata/scribe-cases.jsonl.gz"
	hintsFile  = "testdata/hint-answers.json.gz"
	digestFile = "testdata/scribe-cases.digest"
)

var (
	hintAnswersOnce sync.Once
	hintAnswers     map[string]map[string]string
	recordedHintsMu sync.Mutex
	recordedHints   = map[string]map[string]string{}
)

func TestMain(m *testing.M) {
	flag.Parse()
	code := m.Run()
	if *record && code == 0 {
		if err := writeHintAnswers(); err != nil {
			os.Stderr.WriteString(err.Error() + "\n")
			code = 1
		}
	}
	os.Exit(code)
}

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

func writeHintAnswers() error {
	encoded, err := json.MarshalIndent(recordedHints, "", " ")
	if err != nil {
		return err
	}
	file, err := os.Create(hintsFile)
	if err != nil {
		return err
	}
	defer file.Close()
	zipped := gzip.NewWriter(file)
	zipped.Write(encoded)

	return zipped.Close()
}

// caseSources are what the cases come from: the PHP tool, its manifest, the tests that read them, and the recorder.
var caseSources = []string{
	"src", "composer.json", "tests/Scribes/Backend",
	"tests/Detectors/Backend/Spatie/DataCollectionTypeDetectorTest.php",
	"tests/Detectors/Backend/Spatie/HookMissingComputedDetectorTest.php",
	"tests/Detectors/Backend/Spatie/RedundantEnumUnwrapDetectorTest.php",
	"scribes/backend/testdata/record",
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

func TestTheRecordedScribeCasesAreGeneratedFromTodaysSources(t *testing.T) {
	if *record {
		recordCases(t)

		return
	}
	committed, err := os.ReadFile(digestFile)
	if err != nil {
		t.Fatal(err)
	}
	digest, err := shop.DigestOf(caseSources...)
	if err != nil {
		t.Fatal(err)
	}
	if strings.TrimSpace(string(committed)) != digest {
		t.Fatal("the PHP tool or its scribe tests changed since the cases were recorded: run go generate ./scribes/backend")
	}
}

func recordCases(t *testing.T) {
	if out, err := exec.Command("bash", "testdata/record/record.sh").CombinedOutput(); err != nil {
		t.Fatalf("%v: %s", err, out)
	}
	digest, err := shop.DigestOf(caseSources...)
	if err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(digestFile, []byte(digest+"\n"), 0o644); err != nil {
		t.Fatal(err)
	}
}

// Every source PHP's backend scribe tests read is fixed here byte for byte as PHP fixes it, and its detector finds
// as many sins in it.
func TestEachScribeFixesEverySourcePHPsTestsReadAsPHPDoes(t *testing.T) {
	if *record {
		t.Skip("recording")
	}
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
