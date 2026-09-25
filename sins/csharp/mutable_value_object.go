package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/csharp"
)

// MutableValueObject is a record that can change after it is built — a `set` accessor, or a method that writes its own state — so two holders of the same value can end up seeing different things.
type MutableValueObject struct{}

func init() {
	sins.Register(catalog.CSharp, MutableValueObject{})
}

// Definition is what the sin states about itself.
func (MutableValueObject) Definition() sins.Definition {
	return sins.Definition{
		Name:        "csharp-mutable-value-object",
		Skill:       skills.ValueObjects{},
		Description: "a record that can change after it is built — a `set` accessor, or a method that writes its own state — so two holders of the same value can end up seeing different things",
		Rule:        "Build a record complete and never change it; derive a new one with `with` instead.",
		Suggestion:  "Use `{ get; init; }` instead of `{ get; set; }`, and turn `void Add() { Items++; }` into `Cart Added() => this with { Items = Items + 1 };`.",
	}
}
