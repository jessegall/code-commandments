package shared

import (
	"github.com/jessegall/code-commandments/engine"
	skills "github.com/jessegall/code-commandments/skill/shared"
)

// FlagArgument finds a single two-way choice on a Boolean parameter.
type FlagArgument struct{}

func (FlagArgument) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereFunction().Where(engine.Match.SwitchesOnFlag).Get()
}

func (FlagArgument) Sin() Text {
	return argumentSin("flag-argument", "A Boolean parameter selects which operation runs", "Give each operation its own name.")
}

// FilledParameter finds a function that returns a result through a parameter.
type FilledParameter struct{}

func (FilledParameter) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereFunction().Where(engine.Match.FillsParameterForCaller).Get()
}

func (FilledParameter) Sin() Text {
	return argumentSin("filled-parameter", "The caller reads the result from a parameter the function filled", "Return the result from the function.")
}

func (FilledParameter) WholeTree() {}

// ParamResolvedFromParam finds a parameter used once to resolve another parameter.
type ParamResolvedFromParam struct{}

func (ParamResolvedFromParam) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereFunction().Where(engine.Match.ResolvesParameterFromParameter).Get()
}

func (ParamResolvedFromParam) Sin() Text {
	return argumentSin("param-resolved-from-param", "One parameter is only used to resolve another parameter", "Pass the resolved value to the function.")
}

// UnusedParameter finds a parameter the function never reads.
type UnusedParameter struct{}

func (UnusedParameter) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.Where(engine.Match.IsUnusedParameter).Get()
}

func (UnusedParameter) Sin() Text {
	return argumentSin("unused-parameter", "The function never reads a declared parameter", "Remove the unused parameter from the signature and its callers.")
}

// DerivedArgument finds callers that always supply pieces read from an object.
type DerivedArgument struct{}

func (DerivedArgument) Find(codebase *engine.Codebase) []engine.Match {
	return engine.DerivedArgumentCalls(codebase)
}

func (DerivedArgument) Sin() Text {
	return argumentSin("derived-argument", "Callers repeatedly pass pieces of the same object", "Pass the object and read its pieces where they are needed.")
}

func (DerivedArgument) CrossFile() {}

// ComputedBooleanArgument finds callers that compute several flags from one object.
type ComputedBooleanArgument struct{}

func (ComputedBooleanArgument) Find(codebase *engine.Codebase) []engine.Match {
	return engine.ComputedBooleanCalls(codebase)
}

func (ComputedBooleanArgument) Sin() Text {
	return argumentSin("computed-boolean-argument", "Callers repeatedly compute Boolean arguments from one object", "Pass the object that owns the Boolean decisions.")
}

func (ComputedBooleanArgument) CrossFile() {}

func argumentSin(name, description, rule string) Text {
	return Text{Name: name, Skill: skills.Arguments{}, Description: description, Rule: rule, Suggestion: rule}
}
