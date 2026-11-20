package shared

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
	skills "github.com/jessegall/code-commandments/skill/shared"
)

// RedundantElse is an else after a branch that already left.
type RedundantElse struct{}

func (RedundantElse) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		WhereIs(engine.Branch).
		Where(engine.HasRedundantElse).
		Get()
}

func (RedundantElse) Sin() Text {
	return Text{
		Name:        "redundant-else",
		Skill:       skills.Flow{},
		Description: "An `else` after an `if` branch that already left (it returns, throws, breaks or continues): it indents the rest for nothing",
		Rule:        "Drop the `else` after a branch that returns, throws, continues or breaks; let the rest run at the function's own level.",
		Suggestion:  "Delete the `else` and dedent what was inside it; the exit above already says the rest only runs when the condition did not hold.",
		Formerly: map[catalog.Engine]Former{
			catalog.Python: {Sin: "redundant-python-else"},
			catalog.CSharp: {Sin: "redundant-csharp-else"},
			catalog.Go:     {Sin: "redundant-go-else"},
		},
	}
}

// LoopInvertedGuard is a loop whose whole body sits under one if.
type LoopInvertedGuard struct{}

func (LoopInvertedGuard) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		WhereIs(engine.Branch).
		Where(engine.IsSoleLoopBodyGuard).
		Get()
}

func (LoopInvertedGuard) Sin() Text {
	return Text{
		Name:        "loop-inverted-guard",
		Skill:       skills.Flow{},
		Description: "A loop whose whole body is one `if` (no `else`) around real work: the iteration pushed a level deep behind a condition",
		Rule:        "Use a `continue` guard so the loop body stays flat; don't wrap the whole body in an `if`.",
		Suggestion:  "Make the negated condition the loop's first line, followed by `continue`, and dedent the body under it.",
		Formerly: map[catalog.Engine]Former{
			catalog.Python: {Sin: "python-loop-wrapped-in-if", Detector: "LoopWrappedInIfDetector"},
			catalog.CSharp: {Sin: "csharp-loop-wrapped-in-if", Detector: "LoopWrappedInIfDetector"},
			catalog.Go:     {Sin: "go-loop-wrapped-in-if", Detector: "LoopWrappedInIfDetector"},
		},
	}
}

const ladderRungs = 4

// SubjectLadder is an if and else-if chain testing one subject against a constant at every rung.
type SubjectLadder struct{}

func (SubjectLadder) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		WhereIs(engine.Branch).
		Where(func(branch engine.Match) bool { return engine.SubjectLadderLength(branch) >= ladderRungs }).
		Get()
}

func (SubjectLadder) Sin() Text {
	return Text{
		Name:        "subject-ladder",
		Skill:       skills.Flow{},
		Description: "An `if`/`else if` chain of four or more rungs that each test the same subject for equality with a constant: a dispatch written as a ladder",
		Rule:        "Dispatch on a value with a type that answers per case, a lookup keyed by the value, or a `switch`/`match` on it; never a ladder of equality tests on one subject.",
		Suggestion:  "Make the closed set an enum and put the per-case answer on it, or key a lookup by the value; a `switch`/`match` fits a structural dispatch.",
		Formerly: map[catalog.Engine]Former{
			catalog.Python: {Sin: "python-subject-ladder"},
			catalog.CSharp: {Sin: "csharp-subject-ladder"},
		},
	}
}

// DuplicatedBranchTail is an if whose every branch, the else included, ends with the same statement.
type DuplicatedBranchTail struct{}

func (DuplicatedBranchTail) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		WhereIs(engine.Branch).
		Where(engine.EndsEveryBranchAlike).
		Reject(engine.Match.IsTest).
		Get()
}

func (DuplicatedBranchTail) Sin() Text {
	return Text{
		Name:        "duplicated-branch-tail",
		Skill:       skills.Flow{},
		Description: "Every branch of an `if`/`else` ends with the same statement: it runs whichever way the condition goes.",
		Rule:        "A statement every branch ends with belongs after the conditional, written once.",
		Suggestion:  "Move the shared last statement below the `if`/`else`.",
	}
}

// UnreachableStatement is a statement after one that already left its block.
type UnreachableStatement struct{}

func (UnreachableStatement) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		Where(engine.IsUnreachable).
		Reject(engine.Match.IsTest).
		Get()
}

func (UnreachableStatement) Sin() Text {
	return Text{
		Name:        "unreachable-statement",
		Skill:       skills.Flow{},
		Description: "A statement after a return, throw, break or continue in the same block: it can never run.",
		Rule:        "Every statement in a block can run; code after the block has left is dead.",
		Suggestion:  "Delete the statement, or move it before the statement that leaves the block if it was meant to run.",
	}
}

// NestedTernary is a conditional expression with another inside it.
type NestedTernary struct{}

func (NestedTernary) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		Where(engine.IsOutermostNestedTernary).
		Get()
}

func (NestedTernary) Sin() Text {
	return Text{
		Name:        "nested-ternary",
		Skill:       skills.Flow{},
		Description: "A conditional expression (`a ? b : c`, `b if a else c`) with another inside it: several decisions folded into one expression",
		Rule:        "Use one conditional expression for one choice; for more, use a `match`/`switch`, a lookup, or guards that return early.",
		Suggestion:  "A `match`/`switch` over the subject, a lookup for a table of values, or a small function whose guards return early.",
		Formerly: map[catalog.Engine]Former{
			catalog.Python: {Sin: "python-nested-conditional", Detector: "NestedConditionalDetector"},
			catalog.CSharp: {Sin: "csharp-nested-ternary"},
		},
	}
}

// TernaryStatement is a conditional expression whose value nothing reads.
type TernaryStatement struct{}

func (TernaryStatement) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		Where(engine.IsTernaryStatement).
		Get()
}

func (TernaryStatement) Sin() Text {
	return Text{
		Name:        "ternary-statement",
		Skill:       skills.Flow{},
		Description: "A bare conditional expression as a statement (`cond ? doThis() : doThat()`): its value is never read, so it chooses an action, not a value",
		Rule:        "Choose an action with `if`/`else`; a conditional expression chooses a value, so never write one whose value nothing reads.",
		Suggestion:  "An `if` with each side as its own body, and no `else` at all when one side did nothing.",
		Formerly: map[catalog.Engine]Former{
			catalog.Python: {Sin: "python-conditional-statement", Detector: "ConditionalStatementDetector"},
		},
	}
}

// ShortCircuitStatement is an and/or whose value nothing reads, so it acts as an if.
type ShortCircuitStatement struct{}

func (ShortCircuitStatement) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		Where(engine.IsShortCircuitStatement).
		Get()
}

func (ShortCircuitStatement) Sin() Text {
	return Text{
		Name:        "short-circuit-statement",
		Skill:       skills.Flow{},
		Description: "A bare `a && b()` or `a || b()` statement: a short-circuit whose value nothing reads, so the operator is an `if` in disguise",
		Rule:        "Branch with an `if`; never run work off the right side of a bare `&&`/`||` (`and`/`or`) whose value nothing reads.",
		Suggestion:  "Write the condition as an `if` and the right side as its body, negating it for an `||`.",
		Formerly: map[catalog.Engine]Former{
			catalog.Python: {Sin: "python-short-circuit-statement"},
		},
	}
}

// ReassignedParameter is a parameter given a new value inside its own function.
type ReassignedParameter struct{}

func (ReassignedParameter) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		Where(engine.Match.ReassignsAParameter).
		Reject(engine.Match.IsTest).
		Get()
}

func (ReassignedParameter) Sin() Text {
	return Text{
		Name:        "reassigned-parameter",
		Skill:       skills.Flow{},
		Description: "A parameter overwritten inside its function, so one name holds the value the caller passed and later a different one.",
		Rule:        "A parameter keeps the value its caller passed; a new value gets a name of its own.",
		Suggestion:  "Assign the new value to a local named for what it is (`trimmed = trim(name)`), or ask the caller for the finished value.",
	}
}

// DeepNesting is a choice opening a fourth level of choices inside one function.
type DeepNesting struct{}

func (DeepNesting) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		Where(engine.Match.IsDeeplyNested).
		Get()
}

func (DeepNesting) Sin() Text {
	return Text{
		Name:        "deep-nesting",
		Skill:       skills.Flow{},
		Description: "A decision sits deep inside other decisions in one function",
		Rule:        "Flatten with guards that leave early, or extract the inner decision into a function of its own.",
		Suggestion:  "Turn the outer conditions into guards that return or `continue`, or move the innermost block into a well-named function.",
		Formerly: map[catalog.Engine]Former{
			catalog.Python: {Sin: "deep-python-nesting"},
			catalog.CSharp: {Sin: "deep-csharp-nesting"},
			catalog.Go:     {Sin: "deep-go-nesting"},
		},
	}
}

// NonCountingFor is a counting loop whose step moves to the next item instead of a counter.
type NonCountingFor struct{}

func (NonCountingFor) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.
		Where(engine.IsNonCountingFor).
		Get()
}

func (NonCountingFor) Sin() Text {
	return Text{
		Name:        "non-counting-for",
		Skill:       skills.Flow{},
		Description: "A `for` loop whose step assigns the next item instead of moving a counter: a walk written as a count",
		Rule:        "Keep `for` for a counted loop, whose step advances a counter; walk with a `while`, or let the type hand out its own sequence.",
		Suggestion:  "A `while` over an explicit cursor, or better, an iterator on the type being walked, so the caller never holds the cursor at all.",
		Formerly: map[catalog.Engine]Former{
			catalog.CSharp: {Sin: "csharp-non-counting-for"},
		},
	}
}
