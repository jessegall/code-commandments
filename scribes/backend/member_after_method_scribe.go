package backend

import (
	"slices"

	rules "github.com/jessegall/code-commandments/detectors/backend"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/scribes"
)

func init() {
	scribes.Fixes(rules.MemberAfterMethodDetector{}, func() scribes.Scribe { return MemberAfterMethodScribe{} })
}

// MemberAfterMethodScribe lifts the state a class declares below a method up above its first method.
type MemberAfterMethodScribe struct{}

func (MemberAfterMethodScribe) Rewrite(findings []engine.Match, _ *engine.Codebase) (scribes.Rewrites, error) {
	draft := scribes.NewDraft()
	for _, strays := range perClass(findings) {
		anchor, found := php.Node{Match: strays[0]}.FirstMethodOfItsClass()
		if !found {
			continue
		}
		For(draft, strays[0]).MoveBefore(strays, anchor)
	}

	return draft.Rewrites(), nil
}

func perClass(findings []engine.Match) [][]engine.Match {
	var keys []string
	groups := map[string][]engine.Match{}
	for _, finding := range findings {
		key := classKey(finding)
		if _, seen := groups[key]; !seen {
			keys = append(keys, key)
		}
		groups[key] = append(groups[key], finding)
	}
	ordered := make([][]engine.Match, 0, len(keys))
	for _, key := range keys {
		group := groups[key]
		slices.SortStableFunc(group, func(a, b engine.Match) int { return a.Node().Span.Start - b.Node().Span.Start })
		ordered = append(ordered, group)
	}

	return ordered
}

func classKey(finding engine.Match) string {
	return finding.File() + "::" + php.EnclosingClassName(finding)
}
