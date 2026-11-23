package shared

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/skill"
)

//go:embed enums.md
var enumsBody string

// Enums teaches modelling a closed set of values as an enum that carries what is known about each case, in every language.
type Enums struct{}

func init() {
	skill.Register(catalog.Shared, Enums{})
}

func (Enums) Definition() skill.Definition {
	var languages []string
	for _, language := range contract.Languages {
		languages = append(languages, string(language))
	}

	return skill.Definition{
		Slug:      "shared/enums",
		Tier:      skill.Mandatory,
		Order:     6,
		Title:     "Enums — a closed set is a type that knows its cases",
		Trigger:   "Writing a fixed set of string or number values (statuses, kinds, modes), a class of constants, a `switch`/`match` over an enum or over strings that mirror one, a membership list of literals, an or-chain over several cases, a side list of which cases have a trait, or a chain of type tests on one value. Read this BEFORE you write any of them, and when an enums finding points here.",
		Summary:   "declare a closed set once as an enum and keep everything keyed off a case on it: no class of constants, no literals repeating the cases, no per-case switch at a call site, no unnamed group of cases, no side table, and no type switch.",
		Body:      enumsBody,
		Formerly:  []string{"php/enums-with-behaviour", "csharp/enums", "python/enums"},
		Languages: languages,
		Related: []skill.Relation{
			{Slug: "shared/tell-dont-ask", Note: "the same move for any object: behaviour lives with its data."},
			{Slug: "shared/flow", Note: "how a dispatch that stays at the call site is shaped."},
		},
	}
}
