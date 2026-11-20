package shared

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
	skills "github.com/jessegall/code-commandments/skill/shared"
)

// SwallowCatch is a catch that turns the failure it caught into nothing, or into a value that looks like an answer.
type SwallowCatch struct{}

func (SwallowCatch) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		Where(engine.Match.HandlesFailure).
		Reject(engine.Match.IsTestFixture).
		Where(engine.IsSwallowedCatch).
		Get()
}

func (SwallowCatch) Sin() Text {
	return Text{
		Name:        "swallow-catch",
		Skill:       skills.Exceptions{},
		Description: "A `catch` whose body is empty, or only returns `null`/`false`/an empty value or moves on: the failure becomes something that looks like an answer",
		Rule:        "Let a failure throw, or surface it named with its cause; never swallow a catch into an empty body or an absent value.",
		Suggestion:  "Rethrow it wrapped with the caught failure as its cause, or catch, log and skip at one named boundary.",
		Formerly: map[catalog.Engine]Former{
			catalog.Python: {Sin: "python-swallowed-exception", Detector: "SwallowedExceptionDetector"},
			catalog.CSharp: {Sin: "csharp-swallowed-exception", Detector: "SwallowedExceptionDetector"},
		},
	}
}

// RethrownUnchanged is a catch whose whole body throws back what it caught.
type RethrownUnchanged struct{}

func (RethrownUnchanged) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		WhereIs(engine.Catch).
		Where(engine.IsUnchangedRethrow).
		Get()
}

func (RethrownUnchanged) Sin() Text {
	return Text{
		Name:        "rethrown-unchanged",
		Skill:       skills.Exceptions{},
		Description: "A `catch` whose whole body throws back the failure it caught: it adds nothing, which is what writing no clause does",
		Rule:        "Catch a failure only to do something about it: handle it, add what the caller needs, or let it pass by writing no clause.",
		Suggestion:  "Delete the clause and let the failure travel, or give it work: roll back, release what was held, or throw a failure of your own with it as the cause.",
		Formerly: map[catalog.Engine]Former{
			catalog.Python: {Sin: "python-rethrown-unchanged"},
			catalog.CSharp: {Sin: "csharp-rethrown-unchanged"},
		},
	}
}

// WrappingWithoutCause is a new failure thrown inside a catch that does not carry the caught one.
type WrappingWithoutCause struct{}

func (WrappingWithoutCause) Find(codebase *engine.Codebase) []engine.Match {
	return failureLocations(codebase.
		Where(engine.Match.IsFailureSite).
		Where(engine.IsWrappingWithoutCause).
		Get())
}

func (WrappingWithoutCause) Sin() Text {
	return Text{
		Name:        "wrapping-without-cause",
		Skill:       skills.Exceptions{},
		Description: "A new failure thrown inside a `catch` without the caught one as its cause: the trace of where it started is lost",
		Rule:        "When wrapping a caught failure, hand the original over as its cause; never drop the trace.",
		Suggestion:  "Pass the caught failure as the cause (`previous: $e`, `new X(message, e)`, `{ cause: e }`, `raise X(...) from e`).",
		Formerly: map[catalog.Engine]Former{
			catalog.Python: {Sin: "python-raise-without-cause", Detector: "RaiseWithoutCauseDetector"},
			catalog.CSharp: {Sin: "csharp-wrapping-without-cause"},
		},
	}
}

// GenericException is a throw of the language's catch-all failure instead of a named one.
type GenericException struct{}

func (GenericException) Find(codebase *engine.Codebase) []engine.Match {
	return failureLocations(codebase.
		WhereIs(engine.Throw).
		Where(engine.IsGenericThrow).
		Reject(engine.Match.IsTest).
		Get())
}

func (GenericException) Sin() Text {
	return Text{
		Name:        "generic-exception",
		Skill:       skills.Exceptions{},
		Description: "A throw of the language's catch-all failure (`Exception`, `RuntimeException`, `Error`, `RuntimeError`) instead of a named one",
		Rule:        "Throw a named failure, never the language's catch-all one.",
		Suggestion:  "A failure type of its own, with a named constructor that takes the values involved and writes the message.",
		Formerly: map[catalog.Engine]Former{
			catalog.Python: {Sin: "python-message-string-raise", Detector: "MessageStringRaiseDetector"},
			catalog.CSharp: {Sin: "csharp-generic-throw", Detector: "GenericThrowDetector"},
		},
	}
}

// MessageAtThrow is a failure whose message is written at the throw.
type MessageAtThrow struct{}

func (MessageAtThrow) Find(codebase *engine.Codebase) []engine.Match {
	return failureLocations(codebase.
		Where(engine.Match.IsFailureSite).
		Where(engine.HasMessageAtThrow).
		Reject(engine.Match.IsTest).
		Get())
}

func failureLocations(failures []engine.Match) []engine.Match {
	for at, failure := range failures {
		failures[at] = failure.FailureLocation()
	}
	return failures
}

func (MessageAtThrow) Sin() Text {
	return Text{
		Name:        "message-at-throw",
		Skill:       skills.Exceptions{},
		Description: "A failure's message written as a string at the throw, not by a named constructor given the values involved",
		Rule:        "Pass the values involved to a named constructor on the failure; never write the message at the throw.",
		Suggestion:  "A named constructor on the failure (`::for($order)`, `ForOrder(order)`) that writes the sentence once.",
	}
}
