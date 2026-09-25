package laravel

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	laravelskills "github.com/jessegall/code-commandments/skill/backend/laravel"
)

// BoundaryDuplicatedOperation is the boundary-duplicated-operation sin.
type BoundaryDuplicatedOperation struct{}

func init() { sins.Register(catalog.Backend, BoundaryDuplicatedOperation{}) }

// Definition is what the sin states about itself.
func (BoundaryDuplicatedOperation) Definition() sins.Definition {
	return sins.Definition{
		Name:        "boundary-duplicated-operation",
		Skill:       laravelskills.RouteActions{},
		Description: `The same domain operation hand-rolled at two DIFFERENT entry boundaries (a console command and an MCP tool, a controller and a command) — one operation with two implementations that drift`,
		Rule:        `One operation, one implementation. A boundary translates its own protocol and calls the shared application service; it does not re-spell the operation.`,
		Suggestion:  "Hoist the shared sequence into one application service and have both faces call it.",
		Requires:    requiresLaravel,
	}
}
