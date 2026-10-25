package bridge

import "github.com/jessegall/code-commandments/contract"

// Asker is a bridge that can be asked for a tree: a process this run started, the socket a service keeps one up
// on, or, for a language this binary parses itself, the parser in this process. Everything that reaches a bridge
// reaches it through this, which is why nothing that asks one knows which of the three it holds.
type Asker interface {
	// AskTallied answers the whole stream for the request, telling tally each file as it is read.
	AskTallied(request Request, tally func()) (*contract.Stream, error)
	// AskEach hands each line of the answer to each, for a reader that cuts a stream it never holds whole.
	AskEach(request Request, each func(line contract.Line, raw []byte) error) error
	// Close lets go of whatever the asker holds: a process it started, a socket it opened, or nothing.
	Close() error
}
