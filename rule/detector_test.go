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
	sins.Register(catalog.PHP, WrappingWithoutCause{})
	rule.Register(catalog.PHP, WrappingWithoutCauseDetector{})
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
