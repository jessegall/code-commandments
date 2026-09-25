package frontend

import (
	"strings"

	rules "github.com/jessegall/code-commandments/detectors/frontend"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/vue"
	"github.com/jessegall/code-commandments/scribes"
)

func init() {
	scribes.Fixes(rules.ControlFlowOnElementDetector{}, func() scribes.Scribe { return WrapControlFlowScribe{} })
}

// WrapControlFlowScribe lifts the control flow off an element onto a <template> wrapped around it.
type WrapControlFlowScribe struct{}

// Rewrite wraps each element in a <template> carrying its structural directives, cut from the element itself.
func (WrapControlFlowScribe) Rewrite(findings []engine.Match, _ *engine.Codebase) (scribes.Rewrites, error) {
	draft := scribes.NewDraft()
	for _, finding := range findings {
		element := vue.Of(finding)
		span, err := element.Span()
		if err != nil {
			return scribes.Rewrites{}, err
		}
		carried := element.CarriedDirectives()
		names := make([]string, 0, len(carried))
		for _, attribute := range carried {
			names = append(names, attribute.Name)
		}
		inner := indentInner(element.SourceOmitting(string(span.Source), span.Start, span.End, names))
		indent := span.LineIndent()
		draft.Edit(span, "<template "+vue.RenderAll(carried)+">\n"+indent+"  "+inner+"\n"+indent+"</template>")
	}

	return draft.Rewrites(), nil
}

// indentInner nests every line after the first one level deeper, a blank line left blank.
func indentInner(inner string) string {
	lines := strings.Split(inner, "\n")
	var nested strings.Builder
	nested.WriteString(lines[0])
	for _, line := range lines[1:] {
		if line == "" {
			nested.WriteString("\n")
		} else {
			nested.WriteString("\n  " + line)
		}
	}

	return nested.String()
}
