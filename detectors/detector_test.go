package detectors_test

import (
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/sins"
	"github.com/jessegall/code-commandments/skills"
)

type Exceptions struct{}

func (Exceptions) Definition() skills.Definition {
	return skills.Definition{Slug: "backend/exceptions", Tier: skills.KeepInMind, Summary: "fail named"}
}

type WrappingWithoutCause struct{}

func (WrappingWithoutCause) Definition() sins.Definition {
	return sins.Definition{Name: "wrapping-without-cause", Skill: Exceptions{}}
}

type WrappingWithoutCauseDetector struct{}

func (WrappingWithoutCauseDetector) Sin() sins.Sin { return WrappingWithoutCause{} }

func (WrappingWithoutCauseDetector) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		WhereNew().
		Where(engine.Match.IsWithinLoop).
		Get()
}

type HalfBuiltDetector struct{ WrappingWithoutCauseDetector }

func (HalfBuiltDetector) Unpublished() {}

func init() {
	skills.Register(catalog.Backend, Exceptions{})
	sins.Register(catalog.Backend, WrappingWithoutCause{})
	detectors.Register(catalog.Backend, WrappingWithoutCauseDetector{})
	detectors.Register(catalog.Backend, HalfBuiltDetector{})
}

func TestADetectorIsFoundByItsNameLeniently(t *testing.T) {
	for _, name := range []string{"WrappingWithoutCauseDetector", "WrappingWithoutCause", "wrappingwithoutcause"} {
		if _, ok := detectors.Named(name); !ok {
			t.Fatalf("%s names no detector", name)
		}
	}
	if _, ok := detectors.Named("HalfBuilt"); ok {
		t.Fatal("an unpublished detector is never found")
	}
}

func TestAnUnpublishedDetectorStaysOutOfTheCatalog(t *testing.T) {
	if all := detectors.All(); len(all) != 1 || len(detectors.Of(catalog.Backend)) != 1 || len(detectors.Of(catalog.Python)) != 0 {
		t.Fatalf("got %d detectors", len(all))
	}
	if engine, ok := detectors.EngineOf(WrappingWithoutCauseDetector{}); !ok || engine != catalog.Backend {
		t.Fatalf("got %s", engine)
	}
}

func TestASinPointsAtTheSkillThatFixesIt(t *testing.T) {
	sin := WrappingWithoutCauseDetector{}.Sin().Definition()

	if sin.Slug() != "backend/exceptions" || !sin.Matches("Wrapping Without") || !sin.Scopes("exceptions") || sin.Scopes("absence") {
		t.Fatalf("got %+v", sin)
	}
	if len(sins.All()) != 1 {
		t.Fatalf("got %d sins", len(sins.All()))
	}
}

func TestASkillIsLoadedByItsID(t *testing.T) {
	skill, ok := skills.Slugged("backend/exceptions")
	if !ok {
		t.Fatal("no skill slugged backend/exceptions")
	}
	definition := skill.Definition()

	if definition.ID() != "commandments-backend-exceptions" || definition.Bullet() != "- **`commandments-backend-exceptions`** — fail named" || !definition.Matches("Exceptions") {
		t.Fatalf("got %s / %s", definition.ID(), definition.Bullet())
	}
	if len(skills.InTier(skills.KeepInMind)) != 1 || len(skills.InTier(skills.Mandatory)) != 0 {
		t.Fatal("the skill sits in its own tier only")
	}
}
