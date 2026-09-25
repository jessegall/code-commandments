package vue

import (
	"slices"
	"strings"
)

// A subtree worth a component of its own holds at least this many elements, this many levels deep.
const (
	componentElements = 6
	componentLevels   = 3
)

// tableBound are the tags that only make sense inside their table: a component cannot stand in for one.
var tableBound = []string{"td", "th", "tr", "tbody", "thead", "tfoot", "caption", "colgroup"}

// Boundary is the element a subtree would be extracted from: climbed from an element through every parent
// that holds nothing else, so the wrapper and its lone child are one piece.
func (e Element) Boundary() Element {
	for {
		parent := e.Parent()
		if !parent.Exists() || len(parent.Elements()) != 1 {
			return e
		}
		e = parent
	}
}

// IsExtractable says whether the element's subtree could stand as a component of its own: enough elements,
// enough levels, and not a table part that only its table can hold.
func (e Element) IsExtractable() bool {
	return e.IsElement() &&
		e.Size() >= componentElements &&
		e.Height() >= componentLevels &&
		!slices.Contains(tableBound, strings.ToLower(e.Tag()))
}
