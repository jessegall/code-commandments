package csharp

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed type_honesty.intro.md
	typeHonestyIntro string
	//go:embed type_honesty.principle.md
	typeHonestyPrinciple string
)

// TypeHonesty teaches: a type must not lie: no `T?` a value never is, no `!` to silence the compiler, no per-call scratch state kept on the instance.
type TypeHonesty struct{}

func init() {
	skill.Register(catalog.CSharp, TypeHonesty{})
}

// Definition is what the skill states about itself.
func (TypeHonesty) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "csharp/type-honesty",
		Tier:      skill.Mandatory,
		Order:     36,
		Title:     "C# type honesty — a type must say what is true",
		Trigger:   "Declaring a property or field `T?` that is always set by the time anything reads it, reading it back with `?.` and `??`, silencing the compiler with `!` or `= null!`, filling a required property with `\"\"` or `0` just so the object can be built, saving a field to a local and putting it back later, or a property that returns the same constant however the object was made. Read this BEFORE you make a type nullable to get code to compile, and when a type-honesty finding points here.",
		Intro:     typeHonestyIntro,
		Summary:   "a type must not lie: no `T?` a value never is, no `!` to silence the compiler, no per-call scratch state kept on the instance.",
		Principle: typeHonestyPrinciple,
		Languages: []string{"csharp"},
		Related: []skill.Relation{
			{Slug: "backend/type-honesty", Note: "the same discipline in PHP."},
			{Slug: "csharp/absence", Note: "the complement: absence models a value that really can be missing; this removes one that cannot."},
			{Slug: "csharp/fix-at-the-source", Note: "make the type certain where the value is made, not defended at every read."},
		},
	}
}
