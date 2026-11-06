package bridge

import "github.com/jessegall/code-commandments/contract"

// Asker is a bridge that can be asked for a tree: a process this run started, the socket a service keeps one up on, or, for a language this binary parses itself, the parser in this process.
type Asker interface {
	AskTallied(request Request, tally func()) (*contract.Stream, error)
	AskEach(request Request, each func(line contract.Line, raw []byte) error) error
	Close() error
}
