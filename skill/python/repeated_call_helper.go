package python

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

// RepeatedCallHelper teaches: a keyword call, a guard or a type check written the same way at 2+ sites belongs as one named method on the type it is about.
type RepeatedCallHelper struct{}

func init() {
	skill.Register(catalog.Python, RepeatedCallHelper{})
}

// Definition is what the skill states about itself.
func (RepeatedCallHelper) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "python/repeated-call-helper",
		Tier:      skill.Mandatory,
		Order:     39,
		Title:     "Python repeated call helper — name what you keep spelling out",
		Trigger:   "When you write the same thing the same way at site after site in Python — the same keyword call (`replace(order, status=...)`, `model.copy(update={...})`), the same compound `if` condition, the same `isinstance(x, A) and isinstance(x.y, B)` narrowing. Read this before copying a condition or a keyword call from one function into another, and when a repeated-guard or repeated-call finding points here.",
		Intro:     repeatedCallHelperIntro,
		Summary:   "a keyword call, a guard or a type check written the same way at 2+ sites belongs as one named method on the type it is about.",
		Principle: repeatedCallHelperPrinciple,
		Languages: []string{"python"},
		Related: []skill.Relation{
			{Slug: "backend/repeated-call-helper", Note: "the same discipline over PHP."},
			{Slug: "python/duplication", Note: "a whole function body written twice, rather than one call or condition."},
			{Slug: "python/fix-at-the-source", Note: "name the question where the data lives, not beside each caller."},
		},
	}
}
