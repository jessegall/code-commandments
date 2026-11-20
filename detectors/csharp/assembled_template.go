package csharp

import (
	"slices"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// AssembledTemplateDetector finds a multi-line text taken apart and written line by line: a `string.Join` of three or more lines, two written in the source, or a run of as many `AppendLine`s on one builder.
type AssembledTemplateDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, AssembledTemplateDetector{})
}

func (AssembledTemplateDetector) Sin() sins.Sin {
	return cssins.AssembledTemplate{}
}

func (AssembledTemplateDetector) Find(codebase *engine.Codebase) []engine.Match {
	joins := cs.In(codebase).
		WhereCall().
		Where(engine.As(func(n cs.Node) bool {
			lines := n.JoinedLines()
			fixed := slices.DeleteFunc(lines, func(line cs.Node) bool { return !line.IsFixedText() })
			return engine.HasTemplateLayout(len(lines), len(fixed))
		})).
		Get()
	runs := cs.In(codebase).
		WhereStatement().
		Where(engine.As(cs.Node.IsAppendLineRunStart)).
		Get()

	return append(joins, runs...)
}
