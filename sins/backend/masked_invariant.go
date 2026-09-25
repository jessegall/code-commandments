package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// MaskedInvariant is the masked-invariant sin.
type MaskedInvariant struct{}

func init() { sins.Register(catalog.Backend, MaskedInvariant{}) }

// Definition is what the sin states about itself.
func (MaskedInvariant) Definition() sins.Definition {
	return sins.Definition{
		Name:        "masked-invariant",
		Skill:       skills.TypeHonesty{},
		Description: `Masked invariant — an own field read as ` + "`" + `?->… ?? <fake literal>` + "`" + `, even though the very operation sets that field first, so the fallback only ever answers an impossible "not set yet".`,
		Rule:        "Make an invariant certain (hold it non-nullable / assert it); don't mask it with `?->… ?? <fake>`.",
	}
}
