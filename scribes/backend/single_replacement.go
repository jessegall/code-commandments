package backend

import (
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/scribes"
)

func replaceEach(findings []engine.Match, replacement func(engine.Match) (string, bool), target func(engine.Match) engine.Match) scribes.Rewrites {
	draft := scribes.NewDraft()
	for _, finding := range findings {
		text, rewrites := replacement(finding)
		if !rewrites {
			continue
		}
		node := finding
		if target != nil {
			node = target(finding)
		}
		For(draft, finding).Replace(node, text)
	}

	return draft.Rewrites()
}
