package backend

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed repeated_call_helper.intro.md
	repeatedCallHelperIntro string
	//go:embed repeated_call_helper.principle.md
	repeatedCallHelperPrinciple string
)

// RepeatedCallHelper teaches: a repeated `with`-style call passing the same named argument belongs as a named method on the receiver's type.
type RepeatedCallHelper struct{}

func init() {
	skill.Register(catalog.Backend, RepeatedCallHelper{})
}

// Definition is what the skill states about itself.
func (RepeatedCallHelper) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "backend/repeated-call-helper",
		Tier:      skill.Mandatory,
		Order:     16,
		Title:     "Promote a repeated call to a method",
		Trigger:   "When you keep calling the same `with`-style (variadic, named-argument) method the same way — `$element->copyWith(metadata: SomeData::from([...])->toArray())` at site after site — the repeated call plus its construction boilerplate is a missing method on the receiver's type. Read this when a `->with…(named: …)` / `->copyWith(named: …)` call, especially one wrapping a `Data::from([...])->toArray()`, recurs across call sites.",
		Intro:     repeatedCallHelperIntro,
		Summary:   "a repeated `with`-style call passing the same named argument belongs as a named method on the receiver's type.",
		Principle: repeatedCallHelperPrinciple,
		Languages: []string{"php"},
	}
}
