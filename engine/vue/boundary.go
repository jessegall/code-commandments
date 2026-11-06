package vue

import (
	"slices"
	"strings"
)

const (
	componentElements = 6
	componentLevels   = 3
)

var tableBound = []string{"td", "th", "tr", "tbody", "thead", "tfoot", "caption", "colgroup"}

func (e Element) Boundary() Element {
	for {
		parent := e.Parent()
		if !parent.Exists() || len(parent.Elements()) != 1 {
			return e
		}
		e = parent
	}
}

func (e Element) IsExtractable() bool {
	return e.IsElement() &&
		e.Size() >= componentElements &&
		e.Height() >= componentLevels &&
		!slices.Contains(tableBound, strings.ToLower(e.Tag()))
}

func (e Element) HoldsView() bool {
	for _, candidate := range append([]Element{e}, e.DescendantElements()...) {
		if !candidate.IsTemplate() && candidate.IsExtractable() {
			return true
		}
	}

	return false
}
