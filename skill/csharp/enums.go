package csharp

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed enums.intro.md
	enumsIntro string
	//go:embed enums.principle.md
	enumsPrinciple string
)

// Enums teaches: a closed set of values is an `enum`, its per-case knowledge in one exhaustive `switch` expression beside it — not string constants compared at every call site.
type Enums struct{}

func init() {
	skill.Register(catalog.CSharp, Enums{})
}

// Definition is what the skill states about itself.
func (Enums) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "csharp/enums",
		Tier:      skill.KeepInMind,
		Order:     34,
		Title:     "C# enums — a closed set is a type, with its knowledge on it",
		Trigger:   "Modelling a value that can only be one of a handful in C# — a status, a kind, a mode held as a `string` and compared (`status == \"paid\"`), a set of `const string` fields standing in for cases, or a `switch` over string literals repeated in several classes. Read this BEFORE writing any of them.",
		Intro:     enumsIntro,
		Summary:   "a closed set of values is an `enum`, its per-case knowledge in one exhaustive `switch` expression beside it — not string constants compared at every call site.",
		Principle: enumsPrinciple,
		Languages: []string{"csharp"},
		Related: []skill.Relation{
			{Slug: "backend/enums-with-behaviour", Note: "the same discipline on the PHP backend, with backed enums."},
			{Slug: "python/enums", Note: "the same discipline in Python."},
			{Slug: "csharp/flow", Note: "the `else if` ladder over one subject that an enum and a `switch` expression replace."},
		},
	}
}
