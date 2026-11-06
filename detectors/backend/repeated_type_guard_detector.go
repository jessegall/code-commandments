package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// RepeatedTypeGuardDetector finds one chain of instanceof tests written out at two or more sites: a type question its subject should answer by name.
type RepeatedTypeGuardDetector struct{}

func init() { detectors.Register(catalog.PHP, RepeatedTypeGuardDetector{}) }

func (RepeatedTypeGuardDetector) Sin() sins.Sin { return backendsins.RepeatedTypeGuard{} }

func (RepeatedTypeGuardDetector) GroupKey(finding engine.Match) (string, bool) {
	key := (php.Node{Match: finding}).CanonicalGuardHash()

	return key, key != ""
}

func (d RepeatedTypeGuardDetector) Find(codebase *engine.Codebase) []engine.Match {
	candidates := php.In(codebase).Where(engine.As(php.Node.IsTypeNarrowingGuard)).Get()

	return recurring(candidates, d.GroupKey, 2, func([]engine.Match) bool { return true })
}

func (RepeatedTypeGuardDetector) CrossFile() {}
