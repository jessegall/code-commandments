package shared

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/skill"
)

//go:embed tell_dont_ask.md
var tellDontAskBody string

// TellDontAsk teaches putting behaviour on the object whose data it uses, in every language, instead of asking for the data and doing the work outside.
type TellDontAsk struct{}

func init() {
	skill.Register(catalog.Shared, TellDontAsk{})
}

func (TellDontAsk) Definition() skill.Definition {
	var languages []string
	for _, language := range contract.Languages {
		languages = append(languages, string(language))
	}

	return skill.Definition{
		Slug:      "shared/tell-dont-ask",
		Tier:      skill.KeepInMind,
		Order:     11,
		Title:     "Tell, don't ask — behaviour lives with its data",
		Trigger:   "Writing a method that loops over or queries another object's collection or fields to work out something that object could answer, checking an object's state and throwing before telling it to change, a call chain through several of your own types (`order.customer().address().city()`), looking a fact about an object up by its id, or a class whose methods only forward to one field. Read this BEFORE you write any of them, and when a tell-dont-ask finding points here.",
		Summary:   "put the method on the object whose data it uses: no behaviour kept away from its data, no state rule kept outside the change it guards, no walk through an object's neighbours, and no class that only forwards.",
		Body:      tellDontAskBody,
		Formerly:  []string{"php/tell-dont-ask", "csharp/tell-dont-ask", "python/tell-dont-ask"},
		Languages: languages,
		Related: []skill.Relation{
			{Slug: "shared/pass-the-object", Note: "the same idea at a signature: give the callee the object, not pieces of it."},
			{Slug: "shared/enums", Note: "behaviour keyed off a case lives on the enum."},
		},
	}
}
