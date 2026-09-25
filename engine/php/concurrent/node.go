// Package concurrent is what the engine knows of jessegall/concurrent, stated once.
package concurrent

import (
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

// concurrentClass is the package's base class for state shared across processes.
const concurrentClass = `JesseGall\Concurrent\Concurrent`

// Node is a match read in jessegall/concurrent's terms.
type Node struct {
	engine.Match
}

// Decorate reads a match as a Node.
func (Node) Decorate(m engine.Match) Node {
	return Node{Match: m}
}

// ExtendsConcurrent says whether the class the node sits in extends Concurrent.
func (n Node) ExtendsConcurrent() bool {
	return php.ProgramOf(n.Codebase()).Extends(php.EnclosingClassName(n.Match), concurrentClass)
}
