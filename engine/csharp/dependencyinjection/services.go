package dependencyinjection

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

var resolutionNames = []string{"GetService", "GetRequiredService", "GetKeyedService", "GetRequiredKeyedService"}
var registrationNames = []string{"AddSingleton", "AddScoped", "AddTransient", "TryAddSingleton", "TryAddScoped", "TryAddTransient"}

func ReachesContainer(call engine.Match) bool {
	if !resolvesService(call) || call.IsTest() {
		return false
	}
	return len(constructorParameters(call.EnclosingType())) > 0
}

func OrphanedBindings(codebase *engine.Codebase) []engine.Match {
	calls := codebase.WhereCall().Get()
	reached := map[string]bool{}
	for _, owner := range codebase.WhereTypeDeclaration().Reject(engine.Match.IsTest).Get() {
		for _, parameter := range constructorParameters(owner) {
			if typed := parameter.Typed(); typed != nil {
				reached[typeKey(typed.Text)] = true
			}
		}
	}
	for _, call := range calls {
		if resolvesService(call) && !call.IsTest() {
			if service := genericService(call); service != "" {
				reached[service] = true
			}
		}
	}
	var findings []engine.Match
	for _, call := range calls {
		if call.IsTest() || !slices.Contains(registrationNames, call.CalleeName()) ||
			!strings.Contains(call.TargetSymbol(), "Microsoft.Extensions.DependencyInjection.") {
			continue
		}
		if service := genericService(call); service != "" && !reached[service] {
			findings = append(findings, call)
		}
	}
	return findings
}

func constructorParameters(owner engine.Match) []engine.Match {
	parameters := owner.Child("ParameterList").ChildrenIn("Parameters")
	for _, member := range owner.Members() {
		if member.IsConstructor() {
			parameters = append(parameters, member.Parameters()...)
		}
	}
	return parameters
}

func resolvesService(call engine.Match) bool {
	return slices.Contains(resolutionNames, call.CalleeName()) && (strings.Contains(call.TargetSymbol(), "System.IServiceProvider.") ||
		strings.Contains(call.TargetSymbol(), "Microsoft.Extensions.DependencyInjection."))
}

func genericService(call engine.Match) string {
	callee := call.Child("Expression")
	if member := callee.Child("Name"); member.Exists() {
		callee = member
	}
	first := callee.Child("TypeArgumentList").Child("Arguments")
	if typed := first.Typed(); typed != nil {
		return typeKey(typed.Text)
	}
	if refers := first.Refers(); refers != "" {
		return typeKey(refers)
	}
	return ""
}

func typeKey(typed string) string {
	return strings.TrimSuffix(strings.TrimPrefix(typed, "global::"), "?")
}
