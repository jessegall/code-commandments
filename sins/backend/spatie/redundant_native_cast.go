package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// RedundantNativeCast is the redundant-native-cast sin.
type RedundantNativeCast struct{}

func init() { sins.Register(catalog.Backend, RedundantNativeCast{}) }

// Definition is what the sin states about itself.
func (RedundantNativeCast) Definition() sins.Definition {
	return sins.Definition{
		Name:        "redundant-native-cast",
		Skill:       spatieskills.SpatieDataHydration{},
		Description: `An enum / date is constructed at a hydration site (` + "`" + `Enum::from($x)` + "`" + `, ` + "`" + `new DateTime($x)` + "`" + `) where the property auto-casts the raw scalar`,
		Rule:        `Pass the raw scalar to an enum / ` + "`" + `DateTimeInterface` + "`" + ` slot — Spatie auto-casts it; don't construct the value at the hydration site.`,
		Suggestion:  "`'status' => $raw`, not `'status' => Status::from($raw)`.",
		Requires:    requiresSpatieData,
	}
}
