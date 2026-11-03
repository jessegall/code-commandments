package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// RepeatedGuardDetector finds one substantive && guard written out at two or more sites: a question its subject
// should answer by name.
type RepeatedGuardDetector struct{}

func init() { detectors.Register(catalog.PHP, RepeatedGuardDetector{}) }

// Sin is the sin the detector finds.
func (RepeatedGuardDetector) Sin() sins.Sin { return backendsins.RepeatedGuard{} }

// GroupKey is the guard's conjuncts, in any order, locals read as what they hold.
func (RepeatedGuardDetector) GroupKey(finding engine.Match) (string, bool) {
	key := (php.Node{Match: finding}).CanonicalGuardHash()

	return key, key != ""
}

// Find is every substantive guard another site writes alike.
func (d RepeatedGuardDetector) Find(codebase *engine.Codebase) []engine.Match {
	candidates := php.In(codebase).Where(engine.As(php.Node.IsSubstantiveGuard)).Get()

	return recurring(candidates, d.GroupKey, 2, func([]engine.Match) bool { return true })
}

// CrossFile says the rule reads more than the file it reports in, which the fixture measures by asking it
// of one file and of the whole tree: a scoped run reads the whole scan while it is enabled.
func (RepeatedGuardDetector) CrossFile() {}
