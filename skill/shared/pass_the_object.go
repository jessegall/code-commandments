package shared

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/skill"
)

//go:embed pass_the_object.md
var passTheObjectBody string

// PassTheObject teaches giving a function the object it is about, in every language, instead of pieces a caller derived from it or a container to find it in.
type PassTheObject struct{}

func init() {
	skill.Register(catalog.Shared, PassTheObject{})
}

func (PassTheObject) Definition() skill.Definition {
	var languages []string
	for _, language := range contract.Languages {
		languages = append(languages, string(language))
	}

	return skill.Definition{
		Slug:      "shared/pass-the-object",
		Tier:      skill.KeepInMind,
		Order:     13,
		Title:     "Pass the object — not pieces of it, not the thing it lives in",
		Trigger:   "Adding a parameter the caller derives from an object it already holds: a field of it beside the object itself, several of its fields, a boolean computed from it, or an id beside the container the function then looks it up in. Read this BEFORE you grow a signature that way, and when a pass-the-object finding points here.",
		Summary:   "a signature asks for the subject and the callee works out the rest: no argument derived from another argument, no boolean every caller computes from the same object, and no container plus key where the resolved object would do.",
		Body:      passTheObjectBody,
		Formerly:  []string{"php/pass-the-object", "csharp/pass-the-object", "python/pass-the-object"},
		Languages: languages,
		Related: []skill.Relation{
			{Slug: "shared/tell-dont-ask", Note: "once the callee holds the object, the object answers for itself."},
			{Slug: "shared/value-objects", Note: "when the pieces that travel together have no object yet."},
		},
	}
}
