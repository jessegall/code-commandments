package catalog_test

import (
	"os"
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
)

type Rule interface{ Rule() }

type Zebra struct{}

func (Zebra) Rule() {}

type Apple struct{}

func (Apple) Rule() {}

type Draft struct{}

func (Draft) Rule()        {}
func (Draft) Unpublished() {}

func names(rules []Rule) []string {
	var named []string
	for _, rule := range rules {
		named = append(named, catalog.Name(rule))
	}

	return named
}

func TestACatalogListsPublishedRulesByEngineThenName(t *testing.T) {
	var rules catalog.Catalog[Rule]
	rules.Register(catalog.Python, Apple{})
	rules.Register(catalog.Backend, Zebra{})
	rules.Register(catalog.Backend, Draft{})

	if got := names(rules.All()); !slices.Equal(got, []string{"Zebra", "Apple"}) {
		t.Fatalf("got %v", got)
	}
	if got := names(rules.Of(catalog.Backend)); !slices.Equal(got, []string{"Zebra"}) {
		t.Fatalf("an unpublished rule is left out, got %v", got)
	}
	if engine, ok := rules.EngineOf(Apple{}); !ok || engine != catalog.Python {
		t.Fatalf("got %s", engine)
	}
	if _, ok := rules.EngineOf(Draft{}); ok {
		t.Fatal("an unpublished rule has no engine to judge")
	}
}

func TestARuleRegisteredTwicePanics(t *testing.T) {
	var rules catalog.Catalog[Rule]
	rules.Register(catalog.Backend, Apple{})
	defer func() {
		if recover() == nil {
			t.Fatal("registering Apple twice did not panic")
		}
	}()
	rules.Register(catalog.Frontend, Apple{})
}

func TestNameIsTheShortTypeName(t *testing.T) {
	if catalog.Name(Apple{}) != "Apple" || catalog.Name(&Apple{}) != "Apple" {
		t.Fatal("a rule is named by its type, pointer or not")
	}
	if catalog.Normalise("Wrapping-Without_Cause 2") != "wrappingwithoutcause2" {
		t.Fatal("normalising keeps lower-case letters and digits")
	}
}

func TestEnrolmentImportsEveryRuleFolderOnly(t *testing.T) {
	root := t.TempDir()
	write := func(path string) {
		full := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(full), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(full, []byte("package x\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	write("sins/sin.go")
	write("sins/backend/array_bag.go")
	write("detectors/backend/laravel/facade.go")
	write("detectors/backend/testdata/toy.go")
	write("detectors/_draft/draft.go")
	write("skill/backend/absence/SKILL.md")
	write("skill/python/flow/flow_test.go")
	write("published/published.go")
	write("published/spatie/spatie.go")

	source, err := catalog.Enrolment(root)
	if err != nil {
		t.Fatal(err)
	}

	for _, want := range []string{`"github.com/jessegall/code-commandments/detectors/backend/laravel"`, `"github.com/jessegall/code-commandments/sins/backend"`, `"github.com/jessegall/code-commandments/published/spatie"`} {
		if !strings.Contains(string(source), want) {
			t.Fatalf("%s is not imported:\n%s", want, source)
		}
	}
	if strings.Count(string(source), "_ \"") != 3 {
		t.Fatalf("only the three rule packages are imported:\n%s", source)
	}
}
