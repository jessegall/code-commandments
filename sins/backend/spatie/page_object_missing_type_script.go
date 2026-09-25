package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// PageObjectMissingTypeScript is the page-object-missing-typescript sin.
type PageObjectMissingTypeScript struct{}

func init() { sins.Register(catalog.Backend, PageObjectMissingTypeScript{}) }

// Definition is what the sin states about itself.
func (PageObjectMissingTypeScript) Definition() sins.Definition {
	return sins.Definition{
		Name:        "page-object-missing-typescript",
		Skill:       spatieskills.PageObjects{},
		Description: `A page object travels back in a response but carries no ` + "`" + `#[TypeScript]` + "`" + ` — the ` + "`" + `.vue` + "`" + ` page reads it as untyped ` + "`" + `any` + "`" + `, so the whole page-prop contract goes unchecked`,
		Rule:        `Annotate every page object ` + "`" + `#[TypeScript]` + "`" + ` so it generates a frontend type the page binds against — a response-bound Data with no annotation is a type-safety hole.`,
		Suggestion:  `Add ` + "`" + `#[TypeScript]` + "`" + ` above the page-object class (` + "`" + `use Spatie\TypeScriptTransformer\Attributes\TypeScript;` + "`" + `).`,
		Requires:    requiresSpatieData,
	}
}
