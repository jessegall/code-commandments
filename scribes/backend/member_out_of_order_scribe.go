package backend

import (
	rules "github.com/jessegall/code-commandments/detectors/backend"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/scribes"
)

func init() {
	scribes.Fixes(rules.MemberOutOfOrderDetector{}, func() scribes.Scribe { return MemberOutOfOrderScribe{} })
}

// MemberOutOfOrderScribe regroups a class's head into the order the class layout reads in.
type MemberOutOfOrderScribe struct{}

// Rewrite reorders each class's head once, however many of its members were out of place.
func (MemberOutOfOrderScribe) Rewrite(findings []engine.Match, _ *engine.Codebase) (scribes.Rewrites, error) {
	draft := scribes.NewDraft()
	for _, strays := range perClass(findings) {
		class := php.Node{Match: strays[0]}.EnclosingClassLike()
		if !class.Exists() {
			continue
		}
		For(draft, strays[0]).Reorder(class.ClassHead(), class.GroupedClassHead())
	}

	return draft.Rewrites(), nil
}
