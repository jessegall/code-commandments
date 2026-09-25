package backend

import (
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/scribes"
)

// replaceEach replaces each finding a scribe agrees to rewrite with the text it gives: over the node target picks,
// the finding itself unless target says otherwise.
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
