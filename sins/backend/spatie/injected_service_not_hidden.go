package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// InjectedServiceNotHidden is the injected-service-not-hidden sin.
type InjectedServiceNotHidden struct{}

func init() { sins.Register(catalog.Backend, InjectedServiceNotHidden{}) }

// Definition is what the sin states about itself.
func (InjectedServiceNotHidden) Definition() sins.Definition {
	return sins.Definition{
		Name:        "injected-service-not-hidden",
		Skill:       spatieskills.PageObjects{},
		Description: `A page object injects a service (` + "`" + `#[FromContainer]` + "`" + `, …) into a public property without ` + "`" + `#[Hidden]` + "`" + ` — it leaks into the generated TypeScript type`,
		Rule:        `Every injected collaborator on a page object carries ` + "`" + `#[Hidden]` + "`" + `, so the service never serializes or reaches the frontend type.`,
		Suggestion:  `Add ` + "`" + `#[Hidden]` + "`" + ` above the injection attribute to keep it off the wire. To also keep it out of the generated TypeScript, wire the scaffolded hidden-aware transformer into your typescript-transformer config — otherwise LaravelData's ` + "`" + `#[Hidden]` + "`" + ` alone still leaks the property into the TS type.`,
		Scaffolds: []sins.Scaffold{
			{Path: "TypeScript/DropDataHiddenPropertyProcessor.php", Stub: "DropDataHiddenPropertyProcessor.php.stub", Target: sins.BackendRoot},
			{Path: "TypeScript/HiddenAwareAttributedClassTransformer.php", Stub: "HiddenAwareAttributedClassTransformer.php.stub", Target: sins.BackendRoot},
		},
		Requires: requiresSpatieData,
	}
}
