package shared

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/skill"
)

//go:embed duplication.md
var duplicationBody string

// Duplication teaches giving each piece of knowledge one home in every language: a repeated body, condition, call, path or list is written once and named.
type Duplication struct{}

func init() {
	skill.Register(catalog.Shared, Duplication{})
}

func (Duplication) Definition() skill.Definition {
	var languages []string
	for _, language := range contract.Languages {
		languages = append(languages, string(language))
	}

	return skill.Definition{
		Slug:      "shared/duplication",
		Tier:      skill.Mandatory,
		Order:     4,
		Title:     "Duplication — one piece of knowledge, one home",
		Trigger:   "Pasting a function body you already wrote, writing a near-copy that differs only in a name or a value, writing out the same compound condition, type check or builder call at a second site, converting a value the same way before every call, spelling out a folder path again, typing a literal that has a named constant, or listing by hand the classes a folder already holds. Read this BEFORE you write the second copy, and when a duplication finding points here.",
		Summary:   "write it once, name it, and put it with the thing it is about: no copied or near-copied function, no condition or call repeated across sites, no conversion every caller performs, no path or literal spelled out again, and no hand-kept list of what the code already knows.",
		Body:      duplicationBody,
		Formerly:  []string{"php/duplication", "csharp/duplication", "python/duplication", "typescript/duplication", "php/repeated-call-helper", "csharp/repeated-call-helper", "python/repeated-call-helper"},
		Languages: languages,
		Related: []skill.Relation{
			{Slug: "shared/tell-dont-ask", Note: "where the one copy usually belongs: on the object it is about."},
			{Slug: "shared/enums", Note: "a repeated group of cases gets a named test on the enum."},
		},
	}
}
