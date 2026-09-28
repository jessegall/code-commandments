// Package spatie publishes what a Laravel backend's spatie/laravel-data declares for the frontend's detectors: each
// Data class as a type the server owns, and the file its TypeScript transformer writes their types to.
package spatie

import (
	"slices"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/spatie"
	"github.com/jessegall/code-commandments/published"
)

func init() {
	published.Register(Contracts{})
}

// Contracts publishes the Data classes of the codebase's PHP and where their generated types are written: where the
// transformer's config says, and wherever its manifest shows it has written.
type Contracts struct{}

func (Contracts) Contracts(codebase *engine.Codebase) []published.Contract {
	var contracts []published.Contract
	classes := codebase.Of(contract.PHP).
		WhereKind("Stmt_Class").
		Where(engine.As(spatie.Node.IsDataClass)).
		Get()
	for _, class := range classes {
		fields := publicFieldNames(class)
		if len(fields) == 0 {
			continue
		}
		contracts = append(contracts, published.TypeContract{
			Name:     php.ShortName(php.EnclosingClassName(class)),
			Fields:   fields,
			Optional: spatie.Node{Match: class}.OptionalPublicFieldNames(),
		})
	}
	if output := spatie.TransformerOutputIn(codebase); output != "" {
		contracts = append(contracts, published.GeneratedTypes{Location: output})
	}

	for _, output := range spatie.TransformerOutputsBeside(codebase) {
		contracts = append(contracts, published.GeneratedTypes{Location: output})
	}

	return contracts
}

// publicFieldNames is every public field the class declares, promoted or not, once each.
func publicFieldNames(class engine.Match) []string {
	var names []string
	for _, field := range php.Fields(class) {
		if field.IsPublic && !slices.Contains(names, field.Name) {
			names = append(names, field.Name)
		}
	}

	return names
}
