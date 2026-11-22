package rule_test

import (
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/rule"
	"github.com/jessegall/code-commandments/sins"
	"github.com/jessegall/code-commandments/skill"
)

type Exceptions struct{}

func (Exceptions) Definition() skill.Definition {
	return skill.Definition{Slug: "php/exceptions", Tier: skill.KeepInMind, Summary: "fail named"}
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

func init() {
	skill.Register(catalog.PHP, Exceptions{})
	sins.Register(catalog.PHP, WrappingWithoutCause{})
	rule.Register(catalog.PHP, WrappingWithoutCauseDetector{})
}

func TestADetectorIsFoundByItsNameLeniently(t *testing.T) {
	for _, name := range []string{"WrappingWithoutCauseDetector", "WrappingWithoutCause", "wrappingwithoutcause"} {
		if _, ok := rule.Named(name); !ok {
			t.Fatalf("%s names no detector", name)
		}
	}
}

func TestASinPointsAtTheSkillThatFixesIt(t *testing.T) {
	sin := WrappingWithoutCauseDetector{}.Sin().Definition()

	if sin.Slug() != "php/exceptions" || !sin.Matches("Wrapping Without") || !sin.Scopes("exceptions") || sin.Scopes("absence") {
		t.Fatalf("got %+v", sin)
	}
	if len(sins.All()) != 1 {
		t.Fatalf("got %d sins", len(sins.All()))
	}
}

func TestASkillIsLoadedByItsID(t *testing.T) {
	found, ok := skill.Slugged("php/exceptions")
	if !ok {
		t.Fatal("no skill slugged backend/exceptions")
	}
	definition := found.Definition()

	if definition.ID() != "commandments-php-exceptions" || definition.Bullet() != "- **`commandments-php-exceptions`** — fail named" || !definition.Matches("Exceptions") {
		t.Fatalf("got %s / %s", definition.ID(), definition.Bullet())
	}
	if len(skill.InTier(skill.KeepInMind)) != 1 || len(skill.InTier(skill.Mandatory)) != 0 {
		t.Fatal("the skill sits in its own tier only")
	}
}
