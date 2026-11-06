// Package cli is the command line: one Kernel that parses the arguments once and dispatches to the Command registered for the verb.
package cli

import (
	"github.com/jessegall/code-commandments/cli/help"
)

// Command is one `commandments` verb.
type Command interface {
	help.Documented
	Run(in *Input, console Console) (int, error)
}

// InvalidConfiguration is the project's own config naming something wrong: the one input the Kernel can name precisely, so it says what is wrong in a sentence instead of a trace from inside the engine.
type InvalidConfiguration struct {
	Reason string
}

func (e *InvalidConfiguration) Error() string {
	return e.Reason
}
