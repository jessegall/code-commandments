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

// Rules is what every backend skill, sin and detector of the PHP tool states about itself.
type Rules struct {
	Skills    []SkillRule    `json:"skills"`
	Sins      []SinRule      `json:"sins"`
	Detectors []DetectorRule `json:"detectors"`
}

// SkillRule is what a PHP skill states; Class is its name below Skills\Backend, package folder included.
type SkillRule struct {
	Class                 string   `json:"class"`
	Slug                  string   `json:"slug"`
	Tier                  string   `json:"tier"`
	Order                 int      `json:"order"`
	Title                 string   `json:"title"`
	Trigger               string   `json:"trigger"`
	Intro                 string   `json:"intro"`
	Summary               string   `json:"summary"`
	Principle             string   `json:"principle"`
	ExamplesKeepDocblocks bool     `json:"examplesKeepDocblocks"`
	Languages             []string `json:"languages"`
	Related               []struct {
		Skill  string `json:"skill"`
		Reason string `json:"reason"`
	} `json:"related"`
	References []struct {
		Name  string `json:"name"`
		Title string `json:"title"`
		Body  string `json:"body"`
	} `json:"references"`
	Unpublished bool `json:"unpublished"`
}

// SinRule is what a PHP sin states; Class is its name below Sins\Backend, package folder included.
type SinRule struct {
	Class       string  `json:"class"`
	Name        string  `json:"name"`
	Skill       string  `json:"skill"`
	Description string  `json:"description"`
	Rule        string  `json:"rule"`
	Suggestion  *string `json:"suggestion"`
	Scaffolds   []struct {
		Path   string `json:"path"`
		Stub   string `json:"stub"`
		Target string `json:"target"`
	} `json:"scaffolds"`
	Requires *struct {
		Name      string `json:"name"`
		Ecosystem string `json:"ecosystem"`
	} `json:"requires"`
	Unpublished bool `json:"unpublished"`
}

// DetectorRule is what a PHP detector states; Class is its name below Detectors\Backend, package folder included.
type DetectorRule struct {
	Class        string   `json:"class"`
	Sin          string   `json:"sin"`
	Capabilities []string `json:"capabilities"`
	Unpublished  bool     `json:"unpublished"`
}

// Name is the detector's class name without its package folder.
func (d DetectorRule) Name() string {
	return d.Class[strings.LastIndex(d.Class, `\`)+1:]
}

var rules = sync.OnceValues(func() (Rules, error) {
	var loaded Rules
	content, err := os.ReadFile(filepath.Join(Testdata(), "definitions.json"))
	if err != nil {
		return loaded, err
	}

	return loaded, json.Unmarshal(content, &loaded)
})

// PhpRules is what the PHP tool's backend rules state, read from the committed dump.
func PhpRules(t testing.TB) Rules {
	t.Helper()
	loaded, err := rules()
	if err != nil {
		t.Fatalf("the committed rule definitions do not load (run go generate ./engine/php): %v", err)
	}

	return loaded
}

// SameFindings fails unless each detector flags exactly the shop nodes its PHP twin of the same name flags,
// each as often.
func SameFindings(t *testing.T, ported ...detectors.Detector) {
	t.Helper()
	codebase := Project(t)
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
	content, err := os.ReadFile(filepath.Join(Testdata(), "prose.json"))
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
