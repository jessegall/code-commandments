package frontend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	frontendskill "github.com/jessegall/code-commandments/skill/frontend"
)

func init() {
	sins.Register(catalog.Frontend, LoopWithCondition{})
}

// LoopWithCondition is the sin "loop-with-condition".
type LoopWithCondition struct{}

func (LoopWithCondition) Definition() sins.Definition {
	return sins.Definition{
		Name:        "loop-with-condition",
		Skill:       frontendskill.VueControlFlow{},
		Description: "`v-for` and `v-if`/`v-else-if` on the same element — the condition is re-evaluated every iteration.",
		Rule:        "Never put `v-if` on a `v-for` element; filter in a computed, or wrap the `v-for` in a `<template>` and put the `v-if` on the child.",
	}
}
