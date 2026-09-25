package vue

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"

	"github.com/jessegall/code-commandments/engine/typescript"
)

// LoopVars is the names the element's v-for binds, each alias's names in turn.
func (e Element) LoopVars() []string {
	var names []string
	for _, alias := range e.Directive(For).Aliases() {
		_, bound := typescript.Pattern(typescript.Of(alias))
		names = append(names, bound...)
	}

	return names
}

// LoopVar is the first name the element's v-for binds.
func (e Element) LoopVar() (string, bool) {
	if names := e.LoopVars(); len(names) > 0 {
		return names[0], true
	}

	return "", false
}

// TextRun is a stretch of text between two tags, its interpolations included, as written.
type TextRun struct {
	Text   string
	Static bool
}

// TextRuns is each stretch of text among the element's children, in order.
func (e Element) TextRuns() []TextRun {
	var runs []TextRun
	start, end, static := -1, -1, true
	flush := func() {
		if start >= 0 {
			span, _ := e.Span()
			runs = append(runs, TextRun{Text: string(span.Source[start:end]), Static: static})
		}
		start, static = -1, true
	}
	for _, child := range e.Children() {
		switch child.Kind() {
		case "Text", "Interpolation":
			at := child.Node().Span
			if start < 0 {
				start = at.Start
			}
			end = at.End
			static = static && child.Kind() == "Text"
		case "Element":
			flush()
		}
	}
	flush()

	return runs
}

// StaticAttribute is the value the element's tag writes for a plain attribute, as written.
func (e Element) StaticAttribute(name string) (string, bool) {
	attribute, written := e.WrittenAttribute(name)

	return attribute.Value, written && attribute.Valued
}

// IsContextBound says whether the element is a `<template>` that only makes sense where it stands: a v-else branch,
// or a slot.
func (e Element) IsContextBound() bool {
	if !e.IsTemplate() {
		return false
	}
	for _, attribute := range e.WrittenAttributes() {
		name := attribute.Name
		if name == "v-else" || name == "v-else-if" || strings.HasPrefix(name, "#") || name == "v-slot" || strings.HasPrefix(name, "v-slot:") {
			return true
		}
	}

	return false
}

// Module is the component's script, read.
func (c Component) Module() typescript.Module {
	return typescript.ModuleOfNodes(c.Statements())
}

// StaticTextIn is the first stretch of plain text in an element below whose tag ends with the suffix, trimmed.
func (e Element) StaticTextIn(suffix string) (string, bool) {
	for _, element := range e.DescendantElements() {
		if !strings.HasSuffix(element.Tag(), suffix) {
			continue
		}
		for _, run := range element.TextRuns() {
			if run.Static {
				return strings.TrimSpace(run.Text), true
			}
		}
	}

	return "", false
}

// Renders says whether the subtree renders the tag: the subtree's head or an element below it.
func (x *Extraction) Renders(tag string) bool {
	for _, element := range x.elements() {
		if element.Tag() == tag {
			return true
		}
	}

	return false
}

// scripts is the content of each of the component's script blocks, and which is the `<script setup>` one.
func (c Component) scripts() ([]engine.Match, int) {
	var contents []engine.Match
	setup := -1
	for _, block := range c.ChildrenIn("blocks") {
		if block.Name() != "script" {
			continue
		}
		if setup < 0 && slices.ContainsFunc(block.ChildrenIn("attributes"), func(attribute engine.Match) bool { return attribute.Name() == "setup" }) {
			setup = len(contents)
		}
		contents = append(contents, block.Child("children"))
	}

	return contents, setup
}

// ScriptText is every script block's content, joined by line breaks.
func (c Component) ScriptText() string {
	contents, _ := c.scripts()
	parts := make([]string, 0, len(contents))
	for _, content := range contents {
		parts = append(parts, content.Written())
	}

	return strings.Join(parts, "\n")
}

// ScriptContentStart is where the `<script setup>` content starts, else the first script's.
func (c Component) ScriptContentStart() (int, bool) {
	contents, setup := c.scripts()
	if len(contents) == 0 {
		return 0, false
	}
	chosen := contents[0]
	if setup >= 0 {
		chosen = contents[setup]
	}
	span, err := chosen.Span()

	return span.Start, err == nil
}
