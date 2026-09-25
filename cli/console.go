package cli

import (
	"fmt"
	"io"
)

// Refused is what a command answers when it declines to act, distinct from the 2 a malformed invocation
// answers.
const Refused = 1

// Console is where a command puts words: Out for its answer, Err for what goes wrong beside it.
type Console struct {
	Out io.Writer
	Err io.Writer
}

// Write puts text on screen with no line break after it, as a prompt is.
func (c Console) Write(text string) {
	fmt.Fprint(c.Out, text)
}

// Say prints each line and answers 0, so a command's tail reads as the one statement it is.
func (c Console) Say(lines ...string) int {
	for _, line := range lines {
		fmt.Fprintln(c.Out, line)
	}

	return 0
}

// Refuse prints each line and answers Refused: a refusal only a person can see is not a refusal to a
// script chained behind it.
func (c Console) Refuse(lines ...string) int {
	c.Say(lines...)

	return Refused
}

// Warn prints each line on Err.
func (c Console) Warn(lines ...string) {
	for _, line := range lines {
		fmt.Fprintln(c.Err, line)
	}
}
