package shop

import (
	"encoding/json"
	"fmt"
	"maps"
	"os"
	"path/filepath"
	"slices"
	"strings"
	"sync"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

// shipped is every backend detector the PHP tool shipped, by name: the detectors its findings speak for.
var shipped = sync.OnceValues(func() ([]string, error) {
	listed, err := os.ReadFile(filepath.Join(Oracle(), "detectors.txt"))
	if err != nil {
		return nil, err
	}

	return strings.Fields(string(listed)), nil
})

// SameFindings fails unless each detector the PHP tool shipped flags exactly the shop nodes its PHP twin of the same
// name flagged, each as often; a detector it never shipped is held by the fixture's markers alone.
func SameFindings(t *testing.T, ported ...detectors.Detector) {
	t.Helper()
	codebase := OracleProject(t)
	names, err := shipped()
	if err != nil {
		t.Fatal(err)
	}
	expected := map[string]map[string]int{}
	for _, answer := range Answers(t, "findings") {
		var detector string
		if err := json.Unmarshal(answer.Ask, &detector); err != nil {
			t.Fatal(err)
		}
		if expected[detector] == nil {
			expected[detector] = map[string]int{}
		}
		expected[detector][fmt.Sprintf("%s %v %s", answer.File, answer.Span, answer.Kind)]++
	}
	for _, detector := range ported {
		name := catalog.Name(detector)
		if !slices.Contains(names, name) {
			continue
		}
		found := map[string]int{}
		for _, finding := range detector.Find(codebase) {
			found[key(finding)]++
		}
		if maps.Equal(found, expected[name]) {
			continue
		}
		t.Errorf("%s flags the shop differently from PHP:\n%s", name, difference(expected[name], found))
	}
}

func key(finding engine.Match) string {
	node := finding.Node()

	return fmt.Sprintf("%s [%d %d] %s", strings.TrimPrefix(finding.File(), Root+"/"), node.Span.Start, node.Span.End, node.Kind)
}

func difference(php, golang map[string]int) string {
	var lines []string
	for _, finding := range slices.Sorted(maps.Keys(php)) {
		if golang[finding] < php[finding] {
			lines = append(lines, "  missed     "+finding)
		}
	}
	for _, finding := range slices.Sorted(maps.Keys(golang)) {
		if php[finding] < golang[finding] {
			lines = append(lines, "  unexpected "+finding)
		}
	}

	return strings.Join(lines, "\n")
}

// ProseReading is what the PHP tool reads in one comment text.
type ProseReading struct {
	Text       string   `json:"text"`
	History    bool     `json:"history"`
	Strawman   bool     `json:"strawman"`
	Words      []string `json:"words"`
	Code       bool     `json:"code"`
	Inline     bool     `json:"inline"`
	References []string `json:"references"`
	Paragraphs int      `json:"paragraphs"`
}

// MoodReading is what the PHP tool reads in one method name.
type MoodReading struct {
	Name        string `json:"name"`
	Question    bool   `json:"question"`
	ThirdPerson bool   `json:"thirdPerson"`
	Relational  bool   `json:"relational"`
}

type proseReadings struct {
	Texts []ProseReading `json:"texts"`
	Names []MoodReading  `json:"names"`
}

var readings = sync.OnceValues(func() (proseReadings, error) {
	var loaded proseReadings
	content, err := readZipped(filepath.Join("oracle", "prose.json.gz"))
	if err != nil {
		return loaded, err
	}

	return loaded, json.Unmarshal(content, &loaded)
})

// PhpProse is what the PHP tool reads in every comment of the shop and every probe sentence.
func PhpProse(t testing.TB) []ProseReading {
	t.Helper()
	loaded, err := readings()
	if err != nil {
		t.Fatalf("the committed prose readings do not load (run go generate ./engine/php): %v", err)
	}

	return loaded.Texts
}

// PhpMoods is what the PHP tool reads in every function name of the shop and every probe name.
func PhpMoods(t testing.TB) []MoodReading {
	t.Helper()
	loaded, err := readings()
	if err != nil {
		t.Fatalf("the committed prose readings do not load (run go generate ./engine/php): %v", err)
	}

	return loaded.Names
}
