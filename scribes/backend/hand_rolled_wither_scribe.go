package backend

import (
	"strings"

	rules "github.com/jessegall/code-commandments/detectors/backend"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/scribes"
)

func init() {
	scribes.Fixes(rules.HandRolledWitherDetector{}, func() scribes.Scribe { return HandRolledWitherScribe{} })
}

// HandRolledWitherScribe rewrites a wither that rebuilds its own class argument by argument into `clone($this, [...])`.
type HandRolledWitherScribe struct{}

// Rewrite replaces each rebuild with a clone naming only what changes.
func (HandRolledWitherScribe) Rewrite(findings []engine.Match, _ *engine.Codebase) (scribes.Rewrites, error) {
	draft := scribes.NewDraft()
	for _, finding := range findings {
		text, rewrites := cloneWith(finding)
		if !rewrites {
			continue
		}
		span, err := finding.Span()
		if err != nil {
			return scribes.Rewrites{}, err
		}
		draft.Edit(span, text)
	}

	return draft.Rewrites(), nil
}

// cloneWith is `clone($this, [...])` holding one `'prop' => value` per argument not carried across verbatim; nothing
// when an argument's property cannot be named for certain.
func cloneWith(finding engine.Match) (string, bool) {
	params := php.ConstructorParams(php.Node{Match: finding}.EnclosingClassLike().Match)
	if finding.Kind() != "Expr_New" || len(params) == 0 {
		return "", false
	}
	var entries []string
	for index, argument := range finding.ChildrenIn("args") {
		value := argument.Child("value")
		if (php.Node{Match: value}).IsOwnPropertyRead() {
			continue
		}
		key, named := textOf(argument.Child("name")), argument.Child("name").Exists()
		if !named {
			key, named = php.PromotedParamName(params, index)
		}
		if !named {
			return "", false
		}
		entries = append(entries, "'"+key+"' => "+textOf(value))
	}
	if len(entries) == 0 {
		return "", false
	}

	return "clone($this, [" + strings.Join(entries, ", ") + "])", true
}
