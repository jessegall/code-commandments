package python

import (
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
)

func matches(nodes []py.Node) []engine.Match {
	found := make([]engine.Match, len(nodes))
	for at, node := range nodes {
		found[at] = node.Match
	}

	return found
}
