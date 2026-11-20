package packages

import (
	"github.com/jessegall/code-commandments/engine/php/actions"
	"github.com/jessegall/code-commandments/engine/php/laravel"
	"github.com/jessegall/code-commandments/engine/php/spatie"
)

// Laravel excuses the framework's entry points, contract methods, casts, relations and providers.
type Laravel struct{}

func (Laravel) Register(exemptions *Exemptions) {
	exemptions.Exempt(Boundary).Classes(laravel.RequestTypes...)
	exemptions.Exempt(DynamicMembers).Classes(laravel.Model)
	exemptions.Exempt(ContractMethod).
		On(laravel.FormRequest, "rules").
		On(laravel.McpRequest, "rules").
		On(laravel.McpTool, "rules", "schema").
		On(laravel.Model, "casts").
		On(`Illuminate\Foundation\Auth\User`, "casts").
		On(laravel.AuthGuard, "user").
		On(laravel.Factory, "definition").
		On(`Laravel\Fortify\Contracts\ResetsUserPasswords`, "reset").
		On(laravel.AuthUserProvider, "retrieveById", "retrieveByToken", "retrieveByCredentials").
		On(laravel.Arrayable, "toArray")
	exemptions.Exempt(ArrayReturning).Classes(laravel.FormRequest, laravel.McpRequest, laravel.McpTool)
	exemptions.Exempt(ArrayCallbackContract).On(laravel.Factory, "state")
	arrayInputs := exemptions.Exempt(ArrayInputContract).On(laravel.ConsoleCommand, "table")
	for _, builder := range laravel.BuilderTypes {
		arrayInputs.On(builder, "insert", "insertOrIgnore", "update", "upsert")
	}
	exemptions.Exempt(NoContainer).Classes(laravel.CastContracts...)
	exemptions.Exempt(Association).Methods(laravel.RelationMethods...)
	exemptions.Exempt(Association).Attributes(laravel.BindingAttributes...)
	exemptions.Exempt(CompositionRoot).Classes(laravel.ServiceProvider)
	exemptions.Exempt(ValidationResponse).On(laravel.ValidationException, "withMessages")
}

// Spatie excuses the Data package's pipes and casts, which it builds without the container.
type Spatie struct{}

func (Spatie) Register(exemptions *Exemptions) {
	exemptions.Exempt(NoContainer).Classes(spatie.NoContainerContracts...)
	exemptions.Exempt(ContractMethod).On(`Spatie\LaravelData\Contracts\PropertyMorphableData`, "morph")
	exemptions.Exempt(ArrayReturnContract).On(spatie.Data, "rules")
}

// LaravelActions excuses an action's rules(), the validation the package runs, as FormRequest::rules is.
type LaravelActions struct{}

func (LaravelActions) Register(exemptions *Exemptions) {
	exemptions.Exempt(ContractMethod).On(actions.AsAction, "rules")
}

// Dompdf takes the template context as an array of variables.
type Dompdf struct{}

func (Dompdf) Register(exemptions *Exemptions) {
	exemptions.Exempt(ArrayInputContract).
		On(`Barryvdh\DomPDF\Facade\Pdf`, "loadView").
		On(`Barryvdh\DomPDF\PDF`, "loadView")
}

// Fractal supplies the runtime array contract for resource transformers.
type Fractal struct{}

func (Fractal) Register(exemptions *Exemptions) {
	exemptions.Exempt(ArrayReturnContract).On(`League\Fractal\TransformerAbstract`, "transform")
}
