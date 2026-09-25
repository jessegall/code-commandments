package backend

import (
	rules "github.com/jessegall/code-commandments/detectors/backend"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/scribes"
)

func init() {
	scribes.Fixes(rules.InlineDocblockDetector{}, func() scribes.Scribe { return InlineDocblockScribe{} })
}

// InlineDocblockScribe opens an inline docblock out into its canonical form, a `*` line per line of content.
type InlineDocblockScribe struct{}

// Rewrite rewrites each finding's docblock in canonical form at the indent it stands at.
func (InlineDocblockScribe) Rewrite(findings []engine.Match, _ *engine.Codebase) (scribes.Rewrites, error) {
	draft := scribes.NewDraft()
	for _, finding := range findings {
		doc, documented := php.Node{Match: finding}.DocComment()
		if !documented {
			continue
		}
		writer := For(draft, finding)
		indent, _ := writer.Source().OwnLineIndent(doc.Span.Start)
		writer.ReplaceDocblock(finding, php.DocblockCanonical(doc.Text, indent))
	}

	return draft.Rewrites(), nil
}
