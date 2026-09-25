package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// FlatFieldCluster is the flat-field-cluster sin.
type FlatFieldCluster struct{}

func init() { sins.Register(catalog.Backend, FlatFieldCluster{}) }

// Definition is what the sin states about itself.
func (FlatFieldCluster) Definition() sins.Definition {
	return sins.Definition{
		Name:        "flat-field-cluster",
		Skill:       skills.ValueObjects{},
		Description: `A ` + "`" + `#[TypeScript]` + "`" + ` ` + "`" + `Data` + "`" + ` class spreads a value object it already models flat across sibling scalar fields sharing a camelCase prefix (` + "`" + `wireType` + "`" + ` + ` + "`" + `wireLabel` + "`" + `) instead of nesting the existing ` + "`" + `Wire{type, label}` + "`" + `.`,
		Rule:        `When scalar fields on a Data class share a prefix that names a value object the codebase already declares, they restate that object flat. Nest them into the existing sub-object and shed the prefix — ` + "`" + `wireType` + "`" + `/` + "`" + `wireLabel` + "`" + ` become ` + "`" + `wire: Wire{type, label}` + "`" + `.`,
		Suggestion:  `Replace the prefixed siblings with a single nested property typed as the existing value object, dropping the prefix from each member.`,
		Requires:    requiresSpatieData,
	}
}
