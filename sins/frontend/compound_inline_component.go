package frontend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	frontendskill "github.com/jessegall/code-commandments/skill/frontend"
)

func init() {
	sins.Register(catalog.Frontend, CompoundInlineComponent{})
}

// CompoundInlineComponent is the sin "compound-inline-component".
type CompoundInlineComponent struct{}

func (CompoundInlineComponent) Definition() sins.Definition {
	return sins.Definition{
		Name:        "compound-inline-component",
		Skill:       frontendskill.VueComponents{},
		Description: "A compound primitive (`Dialog`/`Card`/`Sheet`/`Tabs`…) assembled inline with a substantial body — extract it into its own named component.",
		Rule:        "Lift a compound primitive (`Dialog`/`Card`/`Sheet`/`Tabs`) assembled inline into its own named component.",
		Suggestion:  "Extract to a `<{Object}{Action}Dialog>` component; pass `v-model` + props.",
	}
}
