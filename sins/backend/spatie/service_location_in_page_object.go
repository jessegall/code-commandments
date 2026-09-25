package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// ServiceLocationInPageObject is the service-location-in-page-object sin.
type ServiceLocationInPageObject struct{}

func init() { sins.Register(catalog.Backend, ServiceLocationInPageObject{}) }

// Definition is what the sin states about itself.
func (ServiceLocationInPageObject) Definition() sins.Definition {
	return sins.Definition{
		Name:        "service-location-in-page-object",
		Skill:       spatieskills.PageObjects{},
		Description: `A page object reaches into the container with ` + "`" + `app()` + "`" + `/` + "`" + `resolve()` + "`" + ` instead of injecting the collaborator via ` + "`" + `#[FromContainer]` + "`" + ``,
		Rule:        `A page object pulls every collaborator through ` + "`" + `#[FromContainer]` + "`" + ` (hidden), never ` + "`" + `app()` + "`" + `/` + "`" + `resolve()` + "`" + ` inside a getter.`,
		Suggestion:  "Inject it as a `#[Hidden] #[FromContainer(Service::class)]` constructor property.",
		Requires:    requiresSpatieData,
	}
}
