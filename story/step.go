package story

import (
	"fmt"
	"strings"
)

// Step is one thing a story did, as its shape and the words that filled it: the shape is what makes two stories the same story, the words are what made this one different.
type Step struct {
	Shape string
	Words []string
}

func StepOf(shape string, words ...string) Step {
	return Step{Shape: shape, Words: words}
}

func (s Step) Text() string {
	filled := make([]any, len(s.Words))
	for at, word := range s.Words {
		filled[at] = word
	}

	return fmt.Sprintf(s.Shape, filled...)
}

func shapesOf(steps []Step) string {
	shapes := make([]string, len(steps))
	for at, step := range steps {
		shapes[at] = step.Shape
	}

	return strings.Join(shapes, "\n")
}
