package vue

import (
	"crypto/md5"
	"encoding/hex"
	"slices"
	"sort"
	"strings"

	"github.com/jessegall/code-commandments/engine/typescript"
)

// A subtree worth a component of its own, as the PHP tool counts it.
const (
	minComponentElements = 6
	minComponentDepth    = 3
)

// IsText says whether the node is text.
func (m *Markup) IsText() bool { return m.Tag == "#text" }

// IsRoot says whether the node is the template's root.
func (m *Markup) IsRoot() bool { return m.Tag == "#root" }

// IsElement says whether the node is a tag.
func (m *Markup) IsElement() bool { return !strings.HasPrefix(m.Tag, "#") }

// IsTemplate says whether the node is a `<template>`.
func (m *Markup) IsTemplate() bool { return strings.ToLower(m.Tag) == "template" }

// IsStaticText says whether the node is text holding something and no interpolation.
func (m *Markup) IsStaticText() bool {
	return m.IsText() && strings.Trim(m.Text, " \t\n\r\x00\x0b") != "" && len(typescript.Interpolations(m.Text)) == 0
}

// IsComponent says whether the node is a tag named in PascalCase.
func (m *Markup) IsComponent() bool {
	return m.IsElement() && m.Tag != "" && m.Tag[0] >= 'A' && m.Tag[0] <= 'Z'
}

// HasAttribute says whether the tag writes the attribute.
func (m *Markup) HasAttribute(name string) bool {
	_, written := m.Attributes.get(name)

	return written
}

// Attribute is the value the tag writes for the attribute; none when it writes none or writes the name bare.
func (m *Markup) Attribute(name string) (string, bool) {
	written, ok := m.Attributes.get(name)

	return written.Value, ok && written.Valued
}

// NamedAttribute is the attribute as the tag writes it.
func (m *Markup) NamedAttribute(name string) (WrittenAttribute, bool) {
	written, ok := m.Attributes.get(name)
	if !ok {
		return WrittenAttribute{}, false
	}

	return WrittenAttribute{Name: written.Name, Value: written.Value, Valued: written.Valued, Start: written.Start, End: written.End}, true
}

// AttributeSpan is where the tag writes the attribute.
func (m *Markup) AttributeSpan(name string) (int, int, bool) {
	written, ok := m.Attributes.get(name)

	return written.Start, written.End, ok
}

// Elements is the node's child tags.
func (m *Markup) Elements() []*Markup {
	var elements []*Markup
	for _, child := range m.Children {
		if child.IsElement() {
			elements = append(elements, child)
		}
	}

	return elements
}

// Descendants is every tag below the node, in pre-order.
func (m *Markup) Descendants() []*Markup {
	var descendants []*Markup
	for _, child := range m.Elements() {
		descendants = append(descendants, child)
		descendants = append(descendants, child.Descendants()...)
	}

	return descendants
}

// Ancestors is every node above this one, innermost first.
func (m *Markup) Ancestors() []*Markup {
	var ancestors []*Markup
	for at := m.Parent; at != nil; at = at.Parent {
		ancestors = append(ancestors, at)
	}

	return ancestors
}

// Loop is the node's v-for, parsed.
func (m *Markup) Loop() (*typescript.Expr, bool) {
	value, ok := m.Attribute("v-for")
	if !ok {
		return nil, false
	}

	return typescript.ParseFor(value), true
}

// LoopVars is the names the node's v-for binds.
func (m *Markup) LoopVars() []string {
	if loop, ok := m.Loop(); ok {
		return loop.Aliases
	}

	return nil
}

// LoopVar is the first name the node's v-for binds.
func (m *Markup) LoopVar() (string, bool) {
	if vars := m.LoopVars(); len(vars) > 0 {
		return vars[0], true
	}

	return "", false
}

// LoopIterable is what the node's v-for loops over.
func (m *Markup) LoopIterable() (*typescript.Expr, bool) {
	loop, ok := m.Loop()
	if !ok {
		return nil, false
	}

	return loop.Iterable(), true
}

// DirectiveBindings is every value the directive is written with, arguments and modifiers included.
func (m *Markup) DirectiveBindings(directive string) []string {
	var bindings []string
	for _, written := range m.Attributes {
		if written.Valued && (written.Name == directive || strings.HasPrefix(written.Name, directive+":") || strings.HasPrefix(written.Name, directive+".")) {
			bindings = append(bindings, written.Value)
		}
	}

	return bindings
}

// NamedExpression is an attribute's name and its value, parsed.
type NamedExpression struct {
	Name       string
	Expression *typescript.Expr
}

// PropBindings is every prop the tag binds, camelised, and its expression.
func (m *Markup) PropBindings() []NamedExpression {
	var bindings []NamedExpression
	for _, written := range m.Attributes {
		if !written.Valued {
			continue
		}
		var prop string
		switch {
		case strings.HasPrefix(written.Name, ":"):
			prop = written.Name[1:]
		case strings.HasPrefix(written.Name, "v-bind:"):
			prop = written.Name[7:]
		default:
			continue
		}
		if prop == "" || prop[0] == '[' {
			continue
		}
		bindings = putBinding(bindings, NamedExpression{Name: camelize(prop), Expression: typescript.ParseExpression(written.Value)})
	}

	return bindings
}

func putBinding(bindings []NamedExpression, binding NamedExpression) []NamedExpression {
	for index := range bindings {
		if bindings[index].Name == binding.Name {
			bindings[index] = binding

			return bindings
		}
	}

	return append(bindings, binding)
}

func camelize(name string) string {
	var out strings.Builder
	upper := false
	for index := 0; index < len(name); index++ {
		if name[index] == '-' {
			upper = true

			continue
		}
		if upper && name[index] >= 'a' && name[index] <= 'z' {
			out.WriteByte(name[index] - 'a' + 'A')
		} else {
			out.WriteByte(name[index])
		}
		upper = false
	}

	return out.String()
}

// EventBindings is every event the tag listens to and its handler, parsed.
func (m *Markup) EventBindings() []NamedExpression {
	var bindings []NamedExpression
	for _, written := range m.Attributes {
		if written.Valued && (strings.HasPrefix(written.Name, "@") || strings.HasPrefix(written.Name, "v-on:")) {
			bindings = append(bindings, NamedExpression{Name: written.Name, Expression: typescript.ParseExpression(written.Value)})
		}
	}

	return bindings
}

func isBindingName(name string) bool {
	return strings.HasPrefix(name, ":") || strings.HasPrefix(name, "@") || strings.HasPrefix(name, "v-")
}

// Expressions is every expression the tag writes: its bindings' values, then its text's interpolations.
func (m *Markup) Expressions() []*typescript.Expr {
	var expressions []*typescript.Expr
	for _, written := range m.Attributes {
		if written.Valued && isBindingName(written.Name) {
			expressions = append(expressions, typescript.ParseExpression(written.Value))
		}
	}
	for _, child := range m.Children {
		if child.IsText() {
			for _, body := range typescript.Interpolations(child.Text) {
				expressions = append(expressions, typescript.ParseExpression(body))
			}
		}
	}

	return expressions
}

// IsContextBound says whether the node is a `<template>` that only makes sense where it stands: a v-else branch, or a
// slot.
func (m *Markup) IsContextBound() bool {
	if !m.IsTemplate() {
		return false
	}
	if m.HasAttribute("v-else") || m.HasAttribute("v-else-if") {
		return true
	}
	for _, written := range m.Attributes {
		if strings.HasPrefix(written.Name, "#") || written.Name == "v-slot" || strings.HasPrefix(written.Name, "v-slot:") {
			return true
		}
	}

	return false
}

// Renders says whether the node or a tag below it is the tag.
func (m *Markup) Renders(tag string) bool {
	if m.IsElement() && m.Tag == tag {
		return true
	}
	for _, element := range m.Descendants() {
		if element.Tag == tag {
			return true
		}
	}

	return false
}

// StaticTextIn is the first static text of a tag below the node whose name ends with the suffix, trimmed.
func (m *Markup) StaticTextIn(suffix string) (string, bool) {
	for _, element := range m.Descendants() {
		if !strings.HasSuffix(element.Tag, suffix) {
			continue
		}
		for _, child := range element.Children {
			if child.IsStaticText() {
				return strings.Trim(child.Text, " \t\n\r\x00\x0b"), true
			}
		}
	}

	return "", false
}

// Height is how many levels of tags the node stands on.
func (m *Markup) Height() int {
	highest := 0
	for _, child := range m.Elements() {
		highest = max(highest, child.Height())
	}

	return highest + 1
}

// SubtreeSize is how many tags the node's subtree holds, itself included.
func (m *Markup) SubtreeSize() int {
	size := 0
	if m.IsElement() {
		size = 1
	}
	for _, child := range m.Children {
		size += child.SubtreeSize()
	}

	return size
}

// Substantial says whether the node's subtree is big and deep enough to be a component of its own.
func (m *Markup) Substantial() bool {
	return m.SubtreeSize() >= minComponentElements && m.Height() >= minComponentDepth
}

// StructureHash fingerprints the node's markup: tags, attributes and text, whitespace collapsed.
func (m *Markup) StructureHash() string {
	return md5Hex(m.canonical())
}

// ShapeSignature fingerprints the node's shape: tags, attribute names and where text stands.
func (m *Markup) ShapeSignature() string {
	return md5Hex(m.shape())
}

func md5Hex(text string) string {
	sum := md5.Sum([]byte(text))

	return hex.EncodeToString(sum[:])
}

func (m *Markup) canonical() string {
	if m.IsText() {
		text := collapseWhitespace(strings.Trim(m.Text, " \t\n\r\x00\x0b"))
		if text == "" {
			return ""
		}

		return "T:" + text
	}
	if !m.IsElement() {
		return ""
	}
	attributes := slices.Clone(m.Attributes)
	sort.SliceStable(attributes, func(a, b int) bool { return attributes[a].Name < attributes[b].Name })
	pairs := make([]string, 0, len(attributes))
	for _, written := range attributes {
		if written.Valued {
			pairs = append(pairs, written.Name+"="+written.Value)
		} else {
			pairs = append(pairs, written.Name)
		}
	}
	var children strings.Builder
	for _, child := range m.Children {
		children.WriteString(child.canonical())
	}

	return "E:" + m.Tag + "[" + strings.Join(pairs, ",") + "](" + children.String() + ")"
}

func (m *Markup) shape() string {
	if m.IsText() {
		if strings.Trim(m.Text, " \t\n\r\x00\x0b") == "" {
			return ""
		}

		return "T"
	}
	if !m.IsElement() {
		return ""
	}
	names := make([]string, 0, len(m.Attributes))
	for _, written := range m.Attributes {
		names = append(names, written.Name)
	}
	sort.Strings(names)
	var children strings.Builder
	for _, child := range m.Children {
		children.WriteString(child.shape())
	}

	return "E:" + m.Tag + "[" + strings.Join(names, ",") + "](" + children.String() + ")"
}

// collapseWhitespace runs each stretch of whitespace inside the text into one space.
func collapseWhitespace(text string) string {
	var out strings.Builder
	pending := false
	for index := 0; index < len(text); index++ {
		if isSpace(text[index]) {
			pending = out.Len() > 0

			continue
		}
		if pending {
			out.WriteByte(' ')
			pending = false
		}
		out.WriteByte(text[index])
	}

	return out.String()
}

// CarriedDirectives is what must travel with the node's content when it is lifted elsewhere: its structural
// directives, and a loop's key.
func (m *Markup) CarriedDirectives() []WrittenAttribute {
	var carried []WrittenAttribute
	for _, name := range []string{"v-if", "v-else-if", "v-else", "v-for"} {
		if written, ok := m.NamedAttribute(name); ok {
			carried = append(carried, written)
		}
	}
	if !m.HasAttribute("v-for") {
		return carried
	}
	for _, key := range []string{":key", "key"} {
		if written, ok := m.NamedAttribute(key); ok {
			return append(carried, written)
		}
	}

	return carried
}

// splice is a cut of the source and what takes its place.
type splice struct {
	start, end int
	text       string
}

// spliceSource is the source's slice [from, to) with the splices made, right to left.
func spliceSource(source string, from, to int, splices []splice) string {
	ordered := slices.Clone(splices)
	sort.SliceStable(ordered, func(a, b int) bool { return ordered[a].start > ordered[b].start })
	text := source[from:to]
	for _, cut := range ordered {
		text = text[:cut.start-from] + cut.text + text[cut.end-from:]
	}

	return text
}
