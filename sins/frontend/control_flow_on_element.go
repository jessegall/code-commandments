package frontend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	frontendskill "github.com/jessegall/code-commandments/skill/frontend"
)

func init() {
	sins.Register(catalog.Frontend, ControlFlowOnElement{})
}

// ControlFlowOnElement is the sin "control-flow-on-element".
type ControlFlowOnElement struct{}

func (ControlFlowOnElement) Definition() sins.Definition {
	return sins.Definition{
		Name:        "control-flow-on-element",
		Skill:       frontendskill.VueControlFlow{},
		Description: "`v-if`/`v-for`/`v-else`/`v-else-if` on an HTML/component tag instead of a `<template>`",
		Rule:        "Put `v-if`/`v-for`/`v-else`/`v-else-if` on a `<template>`, never directly on an HTML or component tag.",
	}
}
