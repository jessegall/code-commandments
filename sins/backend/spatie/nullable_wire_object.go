package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// NullableWireObject is the nullable-wire-object sin.
type NullableWireObject struct{}

func init() { sins.Register(catalog.Backend, NullableWireObject{}) }

// Definition is what the sin states about itself.
func (NullableWireObject) Definition() sins.Definition {
	return sins.Definition{
		Name:        "nullable-wire-object",
		Skill:       spatieskills.SpatieData{},
		Description: `A nested object on a ` + "`" + `#[TypeScript]` + "`" + ` Data is typed ` + "`" + `T | null` + "`" + ` — it ships ` + "`" + `null` + "`" + ` on the wire where ` + "`" + `T | Optional` + "`" + ` would OMIT it (what the frontend's ` + "`" + `x?.` + "`" + ` reads for "absent")`,
		Rule:        `On a ` + "`" + `#[TypeScript]` + "`" + ` (frontend-bound) Data, type a genuinely-absent nested object ` + "`" + `T | Optional = new Optional()` + "`" + `, not ` + "`" + `T | null` + "`" + ` — so the wire omits it rather than carrying a ` + "`" + `null` + "`" + `.`,
		Suggestion:  `` + "`" + `public readonly UiChrome|Optional $chrome = new Optional();` + "`" + `, not ` + "`" + `public readonly UiChrome|null $chrome = null;` + "`" + `.`,
		Requires:    requiresSpatieData,
	}
}
