package laravel

import (
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	laravelnode "github.com/jessegall/code-commandments/engine/php/laravel"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend/laravel"
)

// DuplicateRouteActionDetector finds route actions of different controllers that are each a thin delegation to one
// and the same operation: two entry points where one belongs.
type DuplicateRouteActionDetector struct{}

func init() { detectors.Register(catalog.Backend, DuplicateRouteActionDetector{}) }

// Sin is the sin the detector finds.
func (DuplicateRouteActionDetector) Sin() sins.Sin { return backendsins.DuplicateRouteAction{} }

// Find is every route action thinly delegating to a target that is no route action itself, where actions of two or
// more controllers delegate to it.
func (DuplicateRouteActionDetector) Find(codebase *engine.Codebase) []engine.Match {
	routes := laravelnode.RouteActionsOf(codebase)
	byTarget := map[string][]engine.Match{}
	var targets []string
	actions := php.In(codebase).WhereKind("Stmt_ClassMethod").Where(engine.As(laravelnode.Node.IsRouteAction)).Get()
	for _, action := range actions {
		target := (laravelnode.Node{Match: action}).ThinDelegationTarget()
		if target == "" {
			continue
		}
		if class, method, _ := strings.Cut(target, "::"); routes.IsRegisteredAction(class, method) {
			continue
		}
		if byTarget[target] == nil {
			targets = append(targets, target)
		}
		byTarget[target] = append(byTarget[target], action)
	}
	var findings []engine.Match
	for _, target := range targets {
		classes := map[string]bool{}
		for _, action := range byTarget[target] {
			classes[php.EnclosingClassName(action)] = true
		}
		if len(classes) >= 2 {
			findings = append(findings, byTarget[target]...)
		}
	}

	return findings
}

// WholeTree says the verdict reads beyond the file it judges, so a per-file check must not ask it.
func (DuplicateRouteActionDetector) WholeTree() {}
