package cli

import (
	"fmt"
	"io"
)

const Refused = 1

// Console is where a command puts words: Out for its answer, Err for what goes wrong beside it.
type Console struct {
	Out io.Writer
	Err io.Writer
}

func (c Console) Write(text string) {
	fmt.Fprint(c.Out, text)
}

func (c Console) Say(lines ...string) int {
	for _, line := range lines {
		fmt.Fprintln(c.Out, line)
	}

	return 0
}

func (c Console) Refuse(lines ...string) int {
	c.Say(lines...)

	return Refused
}

func (c Console) Warn(lines ...string) {
	for _, line := range lines {
		fmt.Fprintln(c.Err, line)
	}
}
