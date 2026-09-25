package laravel

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	laravelnode "github.com/jessegall/code-commandments/engine/php/laravel"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend/laravel"
)

// DuplicateRouteDetector finds one controller action registered under one verb by two or more routes.
type DuplicateRouteDetector struct{}

func init() { detectors.Register(catalog.Backend, DuplicateRouteDetector{}) }

// Sin is the sin the detector finds.
func (DuplicateRouteDetector) Sin() sins.Sin { return backendsins.DuplicateRoute{} }

// Find is every route registration of a verb and action another registration repeats, single-action controllers
// aside.
func (DuplicateRouteDetector) Find(codebase *engine.Codebase) []engine.Match {
	registrations := append(
		php.In(codebase).WhereKind("Expr_StaticCall").Where(namesARouteVerb).Get(),
		php.In(codebase).WhereKind("Expr_MethodCall", "Expr_NullsafeMethodCall").Where(namesARouteVerb).Get()...,
	)
	byRoute := map[string][]engine.Match{}
	var routes []string
	for _, registration := range registrations {
		verb := laravelnode.VerbOf(registration)
		if verb == "" {
			continue
		}
		for _, action := range laravelnode.ActionsOf(registration) {
			if strings.HasSuffix(action, "::__invoke") {
				continue
			}
			route := verb + " " + action
			if byRoute[route] == nil {
				routes = append(routes, route)
			}
			byRoute[route] = append(byRoute[route], registration)
		}
	}
	var findings []engine.Match
	for _, route := range routes {
		if len(byRoute[route]) >= 2 {
			findings = append(findings, byRoute[route]...)
		}
	}

	return findings
}

// namesARouteVerb says whether a call names a route verb as its method.
func namesARouteVerb(call engine.Match) bool {
	name := call.Child("name")

	return name.Kind() == "Identifier" && slices.Contains(laravelnode.RouteVerbs, name.Name())
}
