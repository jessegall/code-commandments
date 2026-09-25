package vue

import (
	"slices"
	"strings"
)

// WrittenAttribute is an attribute as its tag writes it, directives included: its name as written (`v-if`, `:key`,
// `class`), its value between its quotes when it has one, and where it stands.
type WrittenAttribute struct {
	Name   string
	Value  string
	Valued bool
	Start  int
	End    int
}

// Render is the attribute as a tag writes it: `name="value"`, or its bare name.
func (a WrittenAttribute) Render() string {
	if !a.Valued {
		return a.Name
	}

	return a.Name + `="` + a.Value + `"`
}

// RenderAll is a run of attributes as a tag writes them, a space between each.
func RenderAll(attributes []WrittenAttribute) string {
	rendered := make([]string, 0, len(attributes))
	for _, attribute := range attributes {
		rendered = append(rendered, attribute.Render())
	}

	return strings.Join(rendered, " ")
}

// WrittenAttributes is every attribute the element's tag writes, in source order, each read from its own span.
func (e Element) WrittenAttributes() []WrittenAttribute {
	span, err := e.Span()
	if err != nil {
		return nil
	}
	var written []WrittenAttribute
	for _, child := range e.Children() {
		if kind := child.Kind(); kind != "Attribute" && kind != "Directive" {
			continue
		}
		at := child.Node().Span
		written = append(written, readAttribute(string(span.Source[at.Start:at.End]), at.Start, at.End))
	}

	return written
}

// WrittenAttribute is the last attribute the tag writes under the name, as the tag reads when one is written twice.
func (e Element) WrittenAttribute(name string) (WrittenAttribute, bool) {
	written := e.WrittenAttributes()
	for index := len(written) - 1; index >= 0; index-- {
		if written[index].Name == name {
			return written[index], true
		}
	}

	return WrittenAttribute{}, false
}

// CarriedDirectives is what must travel with the element's content when it is lifted elsewhere: its structural
// directives, and, for a loop, its key.
func (e Element) CarriedDirectives() []WrittenAttribute {
	var carried []WrittenAttribute
	for _, name := range []string{"v-if", "v-else-if", "v-else", "v-for"} {
		if attribute, written := e.WrittenAttribute(name); written {
			carried = append(carried, attribute)
		}
	}
	if _, loops := e.WrittenAttribute("v-for"); !loops {
		return carried
	}
	for _, key := range []string{":key", "key"} {
		if attribute, written := e.WrittenAttribute(key); written {
			return append(carried, attribute)
		}
	}

	return carried
}

// IsTemplate says whether the element is a `<template>`.
func (e Element) IsTemplate() bool {
	return strings.EqualFold(e.Tag(), "template")
}

// SourceOmitting is the source's slice [from, to) with each named attribute inside it cut out, each taking the space
// before it, and its whole line when it stood alone on one.
func (e Element) SourceOmitting(source string, from, to int, names []string) string {
	type cut struct{ start, end int }
	var cuts []cut
	for _, name := range names {
		attribute, written := e.WrittenAttribute(name)
		if !written || attribute.Start < from || attribute.End > to {
			continue
		}
		start, end := RemovalSpan(source, from, to, attribute.Start, attribute.End)
		cuts = append(cuts, cut{start, end})
	}
	slices.SortStableFunc(cuts, func(a, b cut) int { return b.start - a.start })
	text := source[from:to]
	for _, removed := range cuts {
		text = text[:removed.start-from] + text[removed.end-from:]
	}

	return text
}

// RemovalSpan is what to cut to remove an attribute: the spaces and tabs before it, and when it stood alone on its
// line, the line break after it.
func RemovalSpan(source string, from, to, start, end int) (int, int) {
	for start > from && (source[start-1] == ' ' || source[start-1] == '\t') {
		start--
	}
	if start == from || source[start-1] == '\n' {
		scan := end
		for scan < to && (source[scan] == ' ' || source[scan] == '\t') {
			scan++
		}
		if scan < to && source[scan] == '\n' {
			end = scan + 1
		}
	}

	return start, end
}

// readAttribute reads one attribute's text: its name up to a space, `=` or `/`, and a value quoted or bare.
func readAttribute(text string, start, end int) WrittenAttribute {
	at := 0
	for at < len(text) && !isSpace(text[at]) && text[at] != '=' && text[at] != '/' {
		at++
	}
	attribute := WrittenAttribute{Name: text[:at], Start: start, End: end}
	for at < len(text) && isSpace(text[at]) {
		at++
	}
	if at >= len(text) || text[at] != '=' {
		return attribute
	}
	at++
	for at < len(text) && isSpace(text[at]) {
		at++
	}
	attribute.Valued = true
	if at < len(text) && (text[at] == '"' || text[at] == '\'') {
		quote := text[at]
		closing := strings.IndexByte(text[at+1:], quote)
		if closing < 0 {
			attribute.Value = text[at+1:]
		} else {
			attribute.Value = text[at+1 : at+1+closing]
		}

		return attribute
	}
	valueEnd := at
	for valueEnd < len(text) && !isSpace(text[valueEnd]) && text[valueEnd] != '>' {
		valueEnd++
	}
	attribute.Value = text[at:valueEnd]

	return attribute
}

// isSpace is ctype_space's whitespace: space, tab, line feed, vertical tab, form feed, carriage return.
func isSpace(c byte) bool {
	return c == ' ' || c == '\t' || c == '\n' || c == '\v' || c == '\f' || c == '\r'
}
