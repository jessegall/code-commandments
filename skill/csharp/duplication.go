package csharp

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed duplication.intro.md
	duplicationIntro string
	//go:embed duplication.principle.md
	duplicationPrinciple string
)

// Duplication teaches: a method body written twice becomes one shared method, parameterised by what differs.
type Duplication struct{}

func init() {
	skill.Register(catalog.CSharp, Duplication{})
}

// Definition is what the skill states about itself.
func (Duplication) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "csharp/duplication",
		Tier:      skill.KeepInMind,
		Order:     29,
		Title:     "C# duplication — one behaviour, one home",
		Trigger:   "Copying a method body from one C# class into another — the same `Map`/`Validate`/`Format` written a second time under another name, or a near-copy that differs only in the key, the path or the message it uses. Read this BEFORE pasting a method you already wrote in another class, and when a duplicate C# function finding points here. The fix is one method both call, parameterised by whatever actually differs.",
		Intro:     duplicationIntro,
		Summary:   "a method body written twice becomes one shared method, parameterised by what differs.",
		Principle: duplicationPrinciple,
		Languages: []string{"csharp"},
		Related: []skill.Relation{
			{Slug: "backend/fix-at-the-source", Note: "the root instinct — one decision, made once, where it is born."},
			{Slug: "python/duplication", Note: "the same discipline over Python modules."},
			{Slug: "typescript/duplication", Note: "the same discipline over TypeScript modules and Vue components."},
		},
	}
}
