package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// DataMethodHintCollision is the data-method-hint-collision sin.
type DataMethodHintCollision struct{}

func init() { sins.Register(catalog.Backend, DataMethodHintCollision{}) }

// Definition is what the sin states about itself.
func (DataMethodHintCollision) Definition() sins.Definition {
	return sins.Definition{
		Name:        "data-method-hint-collision",
		Skill:       spatieskills.SpatieData{},
		Description: `` + "`" + `@method` + "`" + ` tag that re-declares a real method (names the concrete factory, not the magic ` + "`" + `from` + "`" + `/` + "`" + `collect` + "`" + `)`,
		Rule:        `A ` + "`" + `@method` + "`" + ` hint must name the magic ` + "`" + `from` + "`" + `/` + "`" + `collect` + "`" + `, never re-declare a real method (no IDE collision).`,
		Requires:    requiresSpatieData,
	}
}
