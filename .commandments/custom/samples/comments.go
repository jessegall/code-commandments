package samples

// Basket holds the lines a customer chose.
// @righteous OnlyTypesCarryACommentDetector
type Basket struct {
	lines []string
}

// Size reads as a type of its own.
type Size = int

func (b Basket) Total() Size {
	// @sin OnlyTypesCarryACommentDetector
	return len(b.lines) // one per line
}

const (
	small Size = 1
	// @sin OnlyTypesCarryACommentDetector
	large Size = 2 // the largest
)
