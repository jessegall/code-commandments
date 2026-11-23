package shared

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/skill"
)

//go:embed behaviour_per_method.md
var behaviourPerMethodBody string

// BehaviourPerMethod teaches giving each behaviour a method of its own in every language, never a flag that picks between two.
type BehaviourPerMethod struct{}

func init() {
	skill.Register(catalog.Shared, BehaviourPerMethod{})
}

func (BehaviourPerMethod) Definition() skill.Definition {
	var languages []string
	for _, language := range contract.Languages {
		languages = append(languages, string(language))
	}

	return skill.Definition{
		Slug:      "shared/behaviour-per-method",
		Tier:      skill.KeepInMind,
		Order:     18,
		Title:     "One method, one behaviour — never a flag that picks",
		Trigger:   "Adding a `bool` parameter that switches what a method does, or making a parameter optional so that leaving it out means a different behaviour (\"all of them\"). Read this BEFORE you write `render(true)` or `archive(null)`, and when a behaviour-per-method finding points here.",
		Summary:   "give each behaviour its own named method: a method whose body is one branch on a flag, or on whether an optional argument was given, is two methods sharing a name.",
		Body:      behaviourPerMethodBody,
		Formerly:  []string{"php/behaviour-per-method", "csharp/behaviour-per-method", "python/behaviour-per-method"},
		Languages: languages,
		Related: []skill.Relation{
			{Slug: "shared/flow", Note: "how the body of each half is shaped."},
		},
	}
}
