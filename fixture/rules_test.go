package fixture_test

import (
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/fixture"
	"github.com/jessegall/code-commandments/fixture/fixturetest"
	_ "github.com/jessegall/code-commandments/registry"
	"github.com/jessegall/code-commandments/rules"
)

// project is one shared fixture project: its folder under tests/Fixtures, the engines its markers are written for,
// and how its findings read as scenarios.
type project struct {
	folder   string
	engines  []catalog.Engine
	resolver func(*engine.Codebase, fixture.Rule) ([]fixture.Scenario, error)
}

var projects = []project{
	{"backend", []catalog.Engine{catalog.PHP}, fixture.ScopeScenarios},
	{"frontend", []catalog.Engine{catalog.Vue, catalog.TypeScript}, fixture.FileScenarios},
	{"python", []catalog.Engine{catalog.Python}, fixture.FileScenarios},
	{"csharp", []catalog.Engine{catalog.CSharp}, fixture.FileScenarios},
	{"go", []catalog.Engine{catalog.Go}, fixture.FileScenarios},
}

// judgedIn is the fixture project read through the judge's own scan, and every shipped rule judging one of its
// engines, configured by the project's own configuration.
func judgedIn(t *testing.T, each project) (*engine.Codebase, []fixture.Rule, []fixture.Rule) {
	t.Helper()
	root, err := filepath.Abs(filepath.Join("..", "tests", "Fixtures", each.folder))
	if err != nil {
		t.Fatal(err)
	}
	var known []fixture.Rule
	for _, judged := range each.engines {
		for _, shipped := range rules.Of(judged) {
			known = append(known, shipped)
		}
	}
	// The project is read whole, as the judge reads it: a rule narrows to its own languages, and a relation it reads
	// from another language's code still finds that code.
	codebase, err := scan.Walk([]string{root}, source.Excluded{}).Load()
	if err != nil {
		t.Fatal(err)
	}
	// A fixture project is judged as the project it is, never as test fixtures of this checkout, so a rule that
	// leaves tests out still reads its marked code.
	codebase.Rooted(root)
	configured, err := config.Load(root)
	if err != nil {
		t.Fatal(err)
	}
	enabled, err := configured.Enabled(config.Everything)
	if err != nil {
		t.Fatal(err)
	}
	var proven []fixture.Rule
	for _, rule := range enabled {
		if slices.ContainsFunc(each.engines, func(judged catalog.Engine) bool { return slices.Contains(rules.EnginesOf(rule), judged) }) {
			proven = append(proven, rule)
		}
	}

	return codebase, proven, known
}

func TestEveryRuleFlagsExactlyWhatTheFixtureMarks(t *testing.T) {
	for _, each := range projects {
		t.Run(each.folder, func(t *testing.T) {
			codebase, proven, known := judgedIn(t, each)
			fixturetest.Prove(t, fixture.Fixture{Codebase: codebase, Detectors: proven, Known: known, Resolver: each.resolver})
		})
	}
}

func TestEveryMarkedSinHasARule(t *testing.T) {
	for _, each := range projects {
		t.Run(each.folder, func(t *testing.T) {
			codebase, _, known := judgedIn(t, each)
			var unknown []string
			for _, marker := range fixture.Markers(codebase) {
				named := slices.ContainsFunc(known, func(rule fixture.Rule) bool { return marker.Naming(rule) })
				if !named && marker.Tag == fixture.Sinful && !slices.Contains(unknown, marker.Name) {
					unknown = append(unknown, marker.Name)
				}
			}
			slices.Sort(unknown)
			for _, name := range unknown {
				t.Errorf("the fixture marks %s, which no shipped rule finds", name)
			}
		})
	}
}
