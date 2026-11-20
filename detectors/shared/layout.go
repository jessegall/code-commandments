package shared

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
)

// MemberAfterMethod finds a field is declared below a method.
type MemberAfterMethod struct{}

func (MemberAfterMethod) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.Where(engine.Match.IsMemberAfterMethod).Get()
}

func (MemberAfterMethod) Sin() Text {
	return argumentSin("member-after-method", "A field is declared below a method", "Move the field above the constructor and methods.")
}

// MemberOutOfOrder finds a constant is declared below an instance field.
type MemberOutOfOrder struct{}

func (MemberOutOfOrder) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.Where(engine.Match.IsMemberOutOfOrder).Get()
}

func (MemberOutOfOrder) Sin() Text {
	return argumentSin("member-out-of-order", "A constant is declared below an instance field", "Keep constants above instance fields.")
}

// PositionalTupleReturn finds a return tuple has interchangeable unnamed slots.
type PositionalTupleReturn struct{}

func (PositionalTupleReturn) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereFunction().Where(engine.Match.ReturnsPositionalTuple).Get()
}

func (PositionalTupleReturn) Sin() Text {
	return argumentSin("positional-tuple-return", "A return tuple has interchangeable unnamed slots", "Name the return slots or return a named record.")
}

// AssembledTemplate finds a multiline template is split into lines and joined.
type AssembledTemplate struct{}

func (AssembledTemplate) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereCall().Where(engine.Match.AssemblesTemplate).Get()
}

func (AssembledTemplate) Sin() Text {
	return argumentSin("assembled-template", "A multiline template is split into lines and joined", "Write the template in the same shape as its output.")
}

// RedundantArrowReturnType finds an arrow whose expression already has the written result type.
type RedundantArrowReturnType struct{}

func (RedundantArrowReturnType) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereFunction().Where(engine.Match.RepeatsArrowReturnType).Get()
}

func (RedundantArrowReturnType) Sin() Text {
	return argumentSin("redundant-arrow-return-type", "An arrow repeats the type already inferred from its expression", "Let the expression supply its return type.")
}

// MatchDefaultReturnsNull finds a null fallback in a multi-arm selection.
type MatchDefaultReturnsNull struct{}

func (MatchDefaultReturnsNull) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.Where(engine.Match.ReturnsNullInDefaultArm).Get()
}

func (MatchDefaultReturnsNull) Sin() Text {
	sin := argumentSin("match-default-returns-null", "The default arm silently returns an absent value", "Handle the expected cases explicitly and signal an unexpected value.")
	sin.Formerly = map[catalog.Engine]Former{catalog.CSharp: {Sin: "csharp-match-default-returns-null"}}
	return sin
}
