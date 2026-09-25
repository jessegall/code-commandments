package frontend

import (
	"encoding/json"
	"os"
	"path/filepath"
	"sort"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	_ "github.com/jessegall/code-commandments/detectors/frontend"
)

// extractors are the detectors whose sins ExtractComponent fixes, by name.
var extractors = []string{"DuplicateElementDetector", "DeepNestedDetector", "DeepDataReachDetector", "CompoundInlineComponentDetector"}

// enrolled is the frontend detector the catalog holds under the name.
func enrolled(t *testing.T, name string) detectors.Detector {
	t.Helper()
	for _, detector := range detectors.Of(catalog.Frontend) {
		if catalog.Name(detector) == name {
			return detector
		}
	}
	t.Fatalf("no frontend detector %s", name)

	return nil
}

// Every project PHP's ExtractComponentScribeTest builds, recorded by testdata/record/record.sh, goes through each
// extraction step here and in PHP, and the two must write the same files byte for byte.
func TestExtractComponentRewritesEachRecordedProjectAsThePHPToolDoes(t *testing.T) {
	raw, err := os.ReadFile(filepath.Join("testdata", "extraction-projects.json"))
	if err != nil {
		t.Fatal(err)
	}
	var recorded map[string][]map[string]string
	if err := json.Unmarshal(raw, &recorded); err != nil {
		t.Fatal(err)
	}
	names := make([]string, 0, len(recorded))
	for name := range recorded {
		names = append(names, name)
	}
	sort.Strings(names)
	for _, name := range names {
		for index, files := range recorded[name] {
			for _, extractor := range extractors {
				t.Run(name+"/"+extractor+"/"+string(rune('a'+index)), func(t *testing.T) {
					sameAsPHP(t, enrolled(t, extractor), extractor, files)
				})
			}
		}
	}
}
