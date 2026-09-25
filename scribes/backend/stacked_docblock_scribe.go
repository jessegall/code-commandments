package backend

import (
	rules "github.com/jessegall/code-commandments/detectors/backend"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/scribes"
)

func init() {
	scribes.Fixes(rules.StackedDocblockDetector{}, func() scribes.Scribe { return StackedDocblockScribe{} })
}

// StackedDocblockScribe folds a stack of docblocks over one declaration into one block.
type StackedDocblockScribe struct{}

// Rewrite merges each foldable stack into one canonical block at the indent its first block stands at; a stack that
// would misattribute prose or promote a contradicting tag is left for a human.
func (StackedDocblockScribe) Rewrite(findings []engine.Match, _ *engine.Codebase) (scribes.Rewrites, error) {
	draft := scribes.NewDraft()
	for _, finding := range findings {
		node := php.Node{Match: finding}
		blocks := node.Docblocks()
		if len(blocks) < 2 || !node.DocblockStackIsFoldable() {
			continue
		}
		writer := For(draft, finding)
		indent, _ := writer.Source().OwnLineIndent(blocks[0].Span.Start)
		texts := make([]string, 0, len(blocks))
		for _, block := range blocks {
			texts = append(texts, block.Text)
		}
		writer.ReplaceComments(blocks, php.DocblockMerge(texts, indent))
	}

	return draft.Rewrites(), nil
}
