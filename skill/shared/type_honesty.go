package shared

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/skill"
)

//go:embed type_honesty.md
var typeHonestyBody string

// TypeHonesty teaches that a declaration must tell the truth in every language: a type, a signature, a subtype and a property each say what is really there.
type TypeHonesty struct{}

func init() {
	skill.Register(catalog.Shared, TypeHonesty{})
}

func (TypeHonesty) Definition() skill.Definition {
	var languages []string
	for _, language := range contract.Languages {
		languages = append(languages, string(language))
	}

	return skill.Definition{
		Slug:      "shared/type-honesty",
		Tier:      skill.Mandatory,
		Order:     12,
		Title:     "Type honesty — what the code declares is what it does",
		Trigger:   "Writing a check for a case the type already rules out, widening a type to make something pass, a method that changes an object it was handed and returns nothing, a field saved to a local and restored later, an override that only throws, a parameter or private member nothing uses, or a getter that returns a constant. Read this BEFORE you add a defence beside a declaration, and when a type-honesty finding points here.",
		Summary:   "a declaration must not lie: no guard for a case the type rules out, no method that silently fills what it was handed, no per-call value kept as a field, no subtype that refuses its contract, no parameter or private member nothing uses, and no getter that computes nothing.",
		Body:      typeHonestyBody,
		Formerly:  []string{"php/type-honesty", "csharp/type-honesty", "python/type-honesty"},
		Languages: languages,
		Related: []skill.Relation{
			{Slug: "shared/absence", Note: "the other half: deciding where a value that really can be missing is handled."},
		},
	}
}
