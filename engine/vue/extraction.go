package vue

import (

)

const (
	minComponentElements = 6
	minComponentDepth    = 3
)

var tableBoundTags = []string{"td", "th", "tr", "tbody", "thead", "tfoot", "caption", "colgroup"}

var headings = []string{"h1", "h2", "h3", "h4", "h5", "h6"}

var jsGlobals = []string{
	"Object", "Array", "Math", "JSON", "Number", "String", "Boolean", "Date", "RegExp", "Map", "Set",
	"Symbol", "Promise", "console", "window", "document", "globalThis", "NaN", "Infinity", "undefined",
	"parseInt", "parseFloat", "isNaN", "isFinite", "encodeURIComponent", "decodeURIComponent",
}

// Extraction is a subtree of a component and what lifting it into a component of its own takes: the props it reads, the models it writes, the emits it forwards, and its markup as the new component writes it.
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

