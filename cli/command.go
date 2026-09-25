// Package cli is the command line: one Kernel that parses the arguments once and dispatches to the
// Command registered for the verb. Every command documents itself, and every help screen and usage error
// is projected from that declaration.
package cli

import (
	"github.com/jessegall/code-commandments/cli/help"
)

// Command is one `commandments` verb. It receives the parsed Input and a Console, and answers an exit
// code; it never re-reads the raw arguments. Its Help is the single source of its documentation.
type Command interface {
	help.Documented
	Run(in *Input, console Console) (int, error)
}

// InvalidConfiguration is the project's own config naming something wrong: the one input the Kernel can
// name precisely, so it says what is wrong in a sentence instead of a trace from inside the engine.
type InvalidConfiguration struct {
	Reason string
}

func (e *InvalidConfiguration) Error() string {
	return e.Reason
}
