package vue

import (
	"slices"
	"sort"
	"strings"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/typescript"
)

// A subtree is worth a component of its own when it holds this many elements, this many levels deep.
const (
	minComponentElements = 6
	minComponentDepth    = 3
)

// tableBoundTags are the tags only their table can hold.
var tableBoundTags = []string{"td", "th", "tr", "tbody", "thead", "tfoot", "caption", "colgroup"}

// headings are the tags a subtree's title stands in.
var headings = []string{"h1", "h2", "h3", "h4", "h5", "h6"}

// jsGlobals are the names a template reads that no parent passes down.
var jsGlobals = []string{
	"Object", "Array", "Math", "JSON", "Number", "String", "Boolean", "Date", "RegExp", "Map", "Set",
	"Symbol", "Promise", "console", "window", "document", "globalThis", "NaN", "Infinity", "undefined",
	"parseInt", "parseFloat", "isNaN", "isFinite", "encodeURIComponent", "decodeURIComponent",
}

// Extraction is a subtree of a component and what lifting it into a component of its own takes: the props it reads,
// the models it writes, the emits it forwards, and its markup as the new component writes it.
type Extraction struct {
	Element   Element
	Component Component
	emits     *forwardedEmits
}

// forwardedEmits is the handler calls to a parent function the subtree makes, each an `$emit` in the new component.
type forwardedEmits struct {
	splices []splice
	events  []string
	arity   map[string]int
	safe    bool
}

// splice is a cut of the source and what takes its place.
type splice struct {
	start, end int
	text       string
}

// ExtractionAt is the extraction of the subtree an element heads.
func ExtractionAt(element Element) *Extraction {
	return &Extraction{Element: element, Component: ComponentOf(element.Match)}
}

// Path is the component file the subtree stands in.
func (x *Extraction) Path() string {
	return x.Element.File()
}

// Script is the component's script, read.
func (x *Extraction) Script() typescript.Module {
	return x.Component.Module()
}

// Name is what the new component is called, from what the subtree shows: a list item, a list, the object it reads
// most, its heading, its class, or its tag.
func (x *Extraction) Name() string {
	if item, ok := x.Element.LoopVar(); ok {
		return upperFirst(item) + "ListItem"
	}
	if item, ok := x.childLoopVar(); ok {
		return upperFirst(item) + "List"
	}
	if item, ok := x.ancestorLoopVar(); ok {
		return upperFirst(item) + "ListItem"
	}
	if object, ok := x.DominantObject(); ok {
		return upperFirst(object) + "Section"
	}
	if heading, ok := x.headingName(); ok {
		return heading + "Section"
	}
	if class, ok := x.semanticName(); ok {
		return class + "Section"
	}
	if tag := x.Element.Tag(); tag != strings.ToLower(tag) {
		return tag + "Part"
	}

	return "Section"
}

func (x *Extraction) headingName() (string, bool) {
	for _, element := range x.elements() {
		if !slices.Contains(headings, strings.ToLower(element.Tag())) {
			continue
		}
		for _, run := range element.TextRuns() {
			if name := pascalWords(run.Text); name != "" {
				return name, true
			}
		}
	}

	return "", false
}

func (x *Extraction) semanticName() (string, bool) {
	classes, ok := x.Element.StaticAttribute("class")
	if !ok {
		return "", false
	}
	class := firstToken(classes)
	if class == "" || !(strings.Contains(class, "__") || strings.Contains(class, "-") || strings.Contains(class, "_")) {
		return "", false
	}
	name := pascalWords(strings.NewReplacer("__", " ", "-", " ", "_", " ").Replace(class))

	return name, name != ""
}

// firstToken is the text's first run of non-spaces, as strtok cuts it on spaces.
func firstToken(text string) string {
	trimmed := strings.TrimLeft(text, " ")
	if end := strings.IndexByte(trimmed, ' '); end >= 0 {
		return trimmed[:end]
	}

	return trimmed
}

// pascalWords is the text's alphanumeric words that start with a letter, each capitalised and joined.
func pascalWords(text string) string {
	var name strings.Builder
	for _, word := range alnumWords(text) {
		if isLetter(word[0]) {
			name.WriteString(upperFirst(strings.ToLower(word)))
		}
	}

	return name.String()
}

func alnumWords(text string) []string {
	var words []string
	current := ""
	for index := 0; index < len(text); index++ {
		if isAlnumByte(text[index]) {
			current += string(text[index])
		} else if current != "" {
			words = append(words, current)
			current = ""
		}
	}
	if current != "" {
		words = append(words, current)
	}

	return words
}

func isLetter(c byte) bool    { return c >= 'a' && c <= 'z' || c >= 'A' && c <= 'Z' }
func isAlnumByte(c byte) bool { return isLetter(c) || c >= '0' && c <= '9' }
func upperFirst(text string) string {
	if text == "" || text[0] < 'a' || text[0] > 'z' {
		return text
	}

	return string(text[0]-'a'+'A') + text[1:]
}

// DominantObject is the prop the subtree reads most, the first of them on a tie.
func (x *Extraction) DominantObject() (string, bool) {
	props := x.Props()
	if len(props) == 0 {
		return "", false
	}
	counts := map[string]int{}
	for _, element := range x.elements() {
		for _, read := range element.Reads() {
			for _, root := range read.Roots() {
				if slices.Contains(props, root) {
					counts[root]++
				}
			}
		}
	}
	best := props[0]
	for _, prop := range props[1:] {
		if counts[prop] > counts[best] {
			best = prop
		}
	}

	return best, true
}

// Props is every name the subtree reads that a parent must pass down: not a name it binds, calls, imports or a JS
// global; the props variable's members stand in for the variable itself.
func (x *Extraction) Props() []string {
	var reads, bound, called []string
	for _, element := range x.elements() {
		bound = append(bound, element.LoopVars()...)
		for _, read := range element.Reads() {
			reads = append(reads, read.Roots()...)
			called = append(called, read.CalledFunctions()...)
		}
	}
	imported := x.importedNames()
	var props []string
	for _, read := range reads {
		if strings.HasPrefix(read, "$") || slices.Contains(props, read) || slices.Contains(bound, read) || slices.Contains(called, read) ||
			slices.Contains(jsGlobals, read) || slices.Contains(imported, read) {
			continue
		}
		props = append(props, read)
	}

	return x.unwrapPropsVariable(props)
}

func (x *Extraction) unwrapPropsVariable(props []string) []string {
	variable, ok := x.PropsVariable()
	if !ok || !slices.Contains(props, variable) {
		return props
	}
	var unwrapped []string
	for _, prop := range append(slices.DeleteFunc(slices.Clone(props), func(name string) bool { return name == variable }), x.PropsVariableMembers()...) {
		if !slices.Contains(unwrapped, prop) {
			unwrapped = append(unwrapped, prop)
		}
	}

	return unwrapped
}

// PropsVariable is the name the component's script holds its props under.
func (x *Extraction) PropsVariable() (string, bool) {
	return x.Script().VariableNamedFrom(func(call typescript.Call) bool {
		if call.Callee == "defineProps" {
			return true
		}
		if call.Callee != "withDefaults" || len(call.Arguments) == 0 {
			return false
		}
		inner, ok := call.Arguments[0].CalleeName()

		return ok && inner == "defineProps"
	})
}

// EmitName is the name the component's script holds its emit under.
func (x *Extraction) EmitName() (string, bool) {
	return x.Script().VariableNamedFrom(func(call typescript.Call) bool { return call.Callee == "defineEmits" })
}

// PropsVariableMembers is every member of the props variable the subtree reads.
func (x *Extraction) PropsVariableMembers() []string {
	variable, ok := x.PropsVariable()
	if !ok {
		return nil
	}
	var members []string
	for _, element := range x.elements() {
		for _, chain := range element.Chains() {
			if len(chain) > 1 && chain[0] == variable && !slices.Contains(members, chain[1]) {
				members = append(members, chain[1])
			}
		}
	}

	return members
}

// CallSiteExpression is what the call site binds a prop to: through the props variable for one of its members.
func (x *Extraction) CallSiteExpression(prop string) string {
	if slices.Contains(x.PropsVariableMembers(), prop) {
		variable, _ := x.PropsVariable()

		return variable + "." + prop
	}

	return prop
}

func (x *Extraction) importedNames() []string {
	var names []string
	for _, imported := range x.Script().Imports() {
		names = append(names, imported.Names...)
	}

	return names
}

// HasSlots says whether the subtree renders a `<slot>`.
func (x *Extraction) HasSlots() bool {
	return slices.ContainsFunc(x.elements(), func(element Element) bool { return element.Tag() == "slot" })
}

// Models is every name the subtree writes: through a v-model, or a handler assigning it.
func (x *Extraction) Models() []string {
	var models []string
	add := func(names []string) {
		for _, name := range names {
			if !slices.Contains(models, name) {
				models = append(models, name)
			}
		}
	}
	for _, element := range x.elements() {
		for _, directive := range element.Directives() {
			if directive.Named(Model) {
				add(typescript.Of(directive.Value()).Roots())
			}
		}
		for _, read := range element.Reads() {
			if read.IsAssignment() {
				add(read.Left().Roots())
			}
		}
	}

	return models
}

// ContentSpan is the source the new component takes: the subtree, or a `<template>`'s content, its directives riding
// out to the call site.
func (x *Extraction) ContentSpan() engine.Span {
	span, _ := x.Element.Span()
	children := x.Element.Elements()
	if !x.Element.IsTemplate() || len(children) == 0 {
		return span
	}
	first, _ := children[0].Span()
	last, _ := children[len(children)-1].Span()
	span.Start, span.End = first.Start, last.End

	return span
}

// Markup is the subtree as the new component writes it: its handler calls forwarded as emits, its carried directives
// cut out, laid out from the left.
func (x *Extraction) Markup() string {
	span := x.ContentSpan()
	var splices []splice
	for _, forwarded := range x.forwarded().splices {
		if forwarded.start >= span.Start && forwarded.end <= span.End {
			splices = append(splices, forwarded)
		}
	}
	source := string(span.Source)
	for _, carried := range x.Carried() {
		if carried.Start >= span.Start && carried.End <= span.End {
			start, end := RemovalSpan(source, span.Start, span.End, carried.Start, carried.End)
			splices = append(splices, splice{start: start, end: end})
		}
	}

	return engine.Reindent(spliceSource(source, span.Start, span.End, splices), span.Column(), "    ")
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

// Extractable says whether every read the subtree makes of a parent function is a handler it can forward as an emit.
func (x *Extraction) Extractable() bool {
	return x.forwarded().safe
}

// EmitEvents is each event the new component emits and the most arguments any handler passes it, in order.
func (x *Extraction) EmitEvents() ([]string, map[string]int) {
	forwarded := x.forwarded()

	return forwarded.events, forwarded.arity
}

func (x *Extraction) forwarded() *forwardedEmits {
	if x.emits != nil {
		return x.emits
	}
	locals := x.Script().LocalNames()
	emit, _ := x.EmitName()
	forwarded := &forwardedEmits{arity: map[string]int{}}
	rewrites, reached := 0, 0
	for _, element := range x.elements() {
		for _, read := range element.Reads() {
			if slices.ContainsFunc(read.CalledFunctions(), func(name string) bool { return slices.Contains(locals, name) }) {
				reached++
			}
		}
		for _, directive := range element.Directives() {
			if !directive.Named(On) {
				continue
			}
			call, ok := typescript.Of(directive.Value()).AsPlainCall()
			if !ok || !slices.Contains(locals, call.Name) || call.Name == emit {
				continue
			}
			span, err := directive.Span()
			if err != nil {
				continue
			}
			forwarded.splices = append(forwarded.splices, splice{start: span.Start, end: span.End, text: directive.Match.Name() + `="$emit('` + call.Name + `'` + call.TrailingArguments() + `)"`})
			if arity, seen := forwarded.arity[call.Name]; !seen || arity < call.Arity() {
				if !seen {
					forwarded.events = append(forwarded.events, call.Name)
				}
				forwarded.arity[call.Name] = call.Arity()
			}
			rewrites++
		}
	}
	forwarded.safe = rewrites == reached
	x.emits = forwarded

	return forwarded
}

// Carried is the directives the call site carries in the subtree's place.
func (x *Extraction) Carried() []WrittenAttribute {
	if x.Element.IsContextBound() {
		return nil
	}

	return x.Element.CarriedDirectives()
}

// HasCarriedLoop says whether the call site carries the subtree's v-for.
func (x *Extraction) HasCarriedLoop() bool {
	return slices.ContainsFunc(x.Carried(), func(carried WrittenAttribute) bool { return carried.Name == "v-for" })
}

// OwnLoopVars is the names the subtree's own v-for binds.
func (x *Extraction) OwnLoopVars() []string {
	return x.Element.LoopVars()
}

// IterableOf is what the loop binding the variable iterates.
func (x *Extraction) IterableOf(variable string) (typescript.Node, bool) {
	for _, element := range x.elements() {
		if alias, ok := element.LoopVar(); ok && alias == variable {
			return typescript.Of(element.Directive(For).Iterable()), true
		}
	}

	return typescript.Node{}, false
}

func (x *Extraction) childLoopVar() (string, bool) {
	found, seen := "", false
	for _, element := range x.Element.DescendantElements() {
		variable, ok := element.LoopVar()
		if !ok {
			continue
		}
		if seen {
			return "", false
		}
		found, seen = variable, true
	}

	return found, seen
}

func (x *Extraction) ancestorLoopVar() (string, bool) {
	for at := x.Element.Parent(); at.Exists(); at = at.Parent() {
		if variable, ok := at.LoopVar(); ok {
			return variable, true
		}
	}

	return "", false
}

// elements is every element of the subtree, its head first, in pre-order.
func (x *Extraction) elements() []Element {
	return append([]Element{x.Element}, x.Element.DescendantElements()...)
}

// Valid says whether the subtree could stand as a component: a substantial element that no table alone must hold.
func (x *Extraction) Valid() bool {
	if !x.Element.Exists() || x.Element.Size() < minComponentElements || x.Element.Height() < minComponentDepth {
		return false
	}

	return !slices.Contains(tableBoundTags, strings.ToLower(x.Element.Tag()))
}
