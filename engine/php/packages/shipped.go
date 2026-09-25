package packages

import (
	"github.com/jessegall/code-commandments/engine/php/laravel"
	"github.com/jessegall/code-commandments/engine/php/spatie"
)

// Laravel excuses the framework's entry points, contract methods, casts, relations and providers.
type Laravel struct{}

// Register adds what Laravel excuses.
func (Laravel) Register(exemptions *Exemptions) {
	exemptions.Exempt(Boundary).Classes(laravel.RequestTypes...)
	exemptions.Exempt(ContractMethod).
		On(laravel.FormRequest, "rules").
		On(laravel.McpRequest, "rules").
		On(laravel.McpTool, "rules", "schema").
		On(laravel.Model, "casts").
		On(laravel.AuthGuard, "user").
		On(laravel.AuthUserProvider, "retrieveById", "retrieveByToken", "retrieveByCredentials")
	exemptions.Exempt(ArrayReturning).Classes(laravel.FormRequest, laravel.McpRequest, laravel.McpTool)
	exemptions.Exempt(NoContainer).Classes(laravel.CastContracts...)
	exemptions.Exempt(Association).Methods(laravel.RelationMethods...)
	exemptions.Exempt(Association).Attributes(laravel.BindingAttributes...)
	exemptions.Exempt(CompositionRoot).Classes(laravel.ServiceProvider)
}

// Spatie excuses the Data package's pipes and casts, which it builds without the container.
type Spatie struct{}

// Register adds what Spatie Data excuses.
func (Spatie) Register(exemptions *Exemptions) {
	exemptions.Exempt(NoContainer).Classes(spatie.NoContainerContracts...)
}
