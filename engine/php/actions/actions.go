// Package actions is what the engine knows of lorisleiva/laravel-actions: a class that uses its AsAction trait is
// one action the package runs as a controller, a job, a listener or a command.
package actions

// AsAction is the trait that makes a class an action, and makes its rules() the validation the package runs.
const AsAction = `Lorisleiva\Actions\Concerns\AsAction`
