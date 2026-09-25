package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// HookMissingComputed is the hook-missing-computed sin.
type HookMissingComputed struct{}

func init() { sins.Register(catalog.Backend, HookMissingComputed{}) }

// Definition is what the sin states about itself.
func (HookMissingComputed) Definition() sins.Definition {
	return sins.Definition{
		Name:        "hook-missing-computed",
		Skill:       spatieskills.SpatieData{},
		Description: `A get-only property HOOK on a ` + "`" + `Data` + "`" + ` class lacks ` + "`" + `#[Computed]` + "`" + ` — Spatie reads the virtual property as a hydration INPUT, expects it in ` + "`" + `::from()` + "`" + `, and crashes or silently drops it`,
		Rule:        `Mark every get-only property hook on a ` + "`" + `Data` + "`" + ` class ` + "`" + `#[Computed]` + "`" + `, so Spatie treats it as an output-only computed value, not a required hydration input.`,
		Suggestion:  `Add ` + "`" + `#[Computed]` + "`" + ` above the property: ` + "`" + `#[Computed] public array $docks { get => $this->dockSet->all(); }` + "`" + `.`,
		Requires:    requiresSpatieData,
	}
}
