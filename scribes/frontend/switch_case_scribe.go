// Package frontend holds the scribes that rewrite Vue components.
package frontend

import (
	"strings"

	rules "github.com/jessegall/code-commandments/detectors/frontend"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/vue"
	"github.com/jessegall/code-commandments/scribes"
)

func init() {
	scribes.Fixes(rules.SwitchCaseDetector{}, func() scribes.Scribe { return SwitchCaseScribe{} })
}

// SwitchCaseScribe rewrites a v-if chain re-testing one subject into a <SwitchCase> with a slot per case.
type SwitchCaseScribe struct{}

// Rewrite replaces each chain with its <SwitchCase>.
func (SwitchCaseScribe) Rewrite(findings []engine.Match, _ *engine.Codebase) (scribes.Rewrites, error) {
	draft := scribes.NewDraft()
	for _, head := range findings {
		chain, heads := vue.Of(head).SwitchCaseChain()
		if !heads {
			continue
		}
		span, err := chain.Span()
		if err != nil {
			return scribes.Rewrites{}, err
		}
		draft.Edit(span, switchCase(chain, span))
	}

	return draft.Rewrites(), nil
}

// switchCase is the chain as a <SwitchCase> on its subject: each branch a slot named by its key, its structural
// directive cut out, a <template> branch becoming the slot itself.
func switchCase(chain vue.SwitchCaseChain, span engine.Span) string {
	indent := span.LineIndent()
	source := string(span.Source)
	slots := make([]string, 0, len(chain.Branches))
	for index, branch := range chain.Branches {
		directive := "v-else-if"
		switch {
		case index == 0:
			directive = "v-if"
		case branch.Fallback:
			directive = "v-else"
		}
		at := branch.Node().Span
		stripped := branch.SourceOmitting(source, at.Start, at.End, []string{directive})
		if branch.IsTemplate() {
			tagEnd := len("<" + branch.Tag())
			slots = append(slots, indent+"    "+stripped[:tagEnd]+" #"+branch.Slot()+stripped[tagEnd:])

			continue
		}
		slots = append(slots, indent+"    <template #"+branch.Slot()+">"+stripped+"</template>")
	}

	return "<" + vue.SwitchCaseTag + " :value=\"" + chain.Subject + "\">\n" + strings.Join(slots, "\n") + "\n" + indent + "</" + vue.SwitchCaseTag + ">"
}
