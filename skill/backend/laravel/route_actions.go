package laravel

import (
	_ "embed"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

var (
	//go:embed route_actions.intro.md
	routeActionsIntro string
	//go:embed route_actions.principle.md
	routeActionsPrinciple string
)

// RouteActions teaches: route actions are thin, single entry points — no controller wrapping another, no duplicate actions, no two routes to one action.
type RouteActions struct{}

func init() {
	skill.Register(catalog.Backend, RouteActions{})
}

// Definition is what the skill states about itself.
func (RouteActions) Definition() skill.Definition {
	return skill.Definition{
		Slug:      "backend/route-actions",
		Tier:      skill.Mandatory,
		Order:     15,
		Title:     "Route Actions — one operation, one entry point",
		Trigger:   `How a route action (controller method) earns its existence — it is a THIN seam that validates the request and delegates INTO the domain, and it is the ONLY way in to its operation. Never wrap another controller (a controller that forwards to another controller's action is a redundant entry point); never duplicate a sibling action's body; never wire two routes to the same action. Read this BEFORE you add a controller/route action, forward a request from one controller to another, copy an action body, or register a route.`,
		Intro:     routeActionsIntro,
		Summary:   `route actions are thin, single entry points — no controller wrapping another, no duplicate actions, no two routes to one action.`,
		Principle: routeActionsPrinciple,
		Languages: []string{"php"},
		Related: []skill.Relation{
			{Slug: "backend/fix-at-the-source", Note: `a redundant controller is duplication at the boundary — hoist the shared work to the one place it belongs (a service), then both routes call it.`},
			{Slug: "backend/laravel-idioms", Note: `controllers/routes are Laravel's HTTP edge; this is the discipline for keeping that edge thin and single.`},
		},
	}
}
