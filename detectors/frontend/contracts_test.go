package frontend_test

import (
	"path/filepath"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/published"
)

// fixtureServer publishes what the frontend fixture's server side declares: its three Data classes, and the
// file typescript-transformer writes their types to. The frontend stream holds no PHP to read them from.
type fixtureServer struct{}

func init() {
	published.Register(fixtureServer{})
}

func (fixtureServer) Contracts(*engine.Codebase) []published.Contract {
	output, _ := filepath.Abs("../../tests/Fixtures/frontend/generated/server-types.ts")

	return []published.Contract{
		published.TypeContract{Name: "CustomerData", Fields: []string{"firstName", "lastName", "emailAddress", "phoneNumber"}},
		published.TypeContract{Name: "OrderData", Fields: []string{"id", "total", "placedAt", "status"}},
		published.TypeContract{Name: "ProductData", Fields: []string{"id", "name", "price", "sku", "stock"}},
		published.GeneratedTypes{Location: output},
	}
}
