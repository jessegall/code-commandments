package shared

import (
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// NarratedCommand finds this value-shape problem in a declaration.
type NarratedCommand struct{}

func (NarratedCommand) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereFunction().Where(engine.Match.IsNarratedCommand).Get()
}

func (NarratedCommand) Sin() Text {
	return argumentSin("narrated-command", "A command method is named in the third person", "Name the command as an instruction.")
}

// BareStatePredicate finds this value-shape problem in a declaration.
type BareStatePredicate struct{}

func (BareStatePredicate) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereFunction().Where(engine.Match.IsBareStatePredicate).Get()
}

func (BareStatePredicate) Sin() Text {
	return argumentSin("bare-state-predicate", "A Boolean method about its own state has the name of an action", "Name the method as a question about its state.")
}

// HandRolledWither finds this value-shape problem in a declaration.
type HandRolledWither struct{}

func (HandRolledWither) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereFunction().Where(engine.Match.RollsItsOwnWither).Get()
}

func (HandRolledWither) Sin() Text {
	return argumentSin("hand-rolled-wither", "A wither repeats fields from its type's constructor", "Use the type's copy operation to carry unchanged fields.")
}

// KeyedLookupEnvy finds this value-shape problem in a declaration.
type KeyedLookupEnvy struct{}

func (KeyedLookupEnvy) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereFunction().Where(engine.Match.LooksUpAnOwnedField).Get()
}

func (KeyedLookupEnvy) Sin() Text {
	return argumentSin("keyed-lookup-envy", "A routine looks up a value using a field of another local type", "Put the lookup next to the type that owns the key.")
}

// ScratchStateRestore finds this value-shape problem in a declaration.
type ScratchStateRestore struct{}

func (ScratchStateRestore) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereFunction().Where(engine.Match.RestoresScratchField).Get()
}

func (ScratchStateRestore) Sin() Text {
	return argumentSin("scratch-state-restore", "A method saves a field, changes it, and restores it", "Keep temporary data local to the operation.")
}

// ArrayBag finds a loose map is read through fixed keys.
type ArrayBag struct{}

func (ArrayBag) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereIs(engine.Parameter).Where(engine.Match.ReadsRecordBag).Get()
}

func (ArrayBag) Sin() Text {
	return argumentSin("array-bag", "A loose map is read through fixed keys", "Give the fields a named record type.")
}

// ArrayBagParameter finds callers pass a loose map with the same fields.
type ArrayBagParameter struct{}

func (ArrayBagParameter) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereIs(engine.Parameter).Where(engine.Match.ReceivesRecordBag).Get()
}

func (ArrayBagParameter) Sin() Text {
	return argumentSin("array-bag-parameter", "Callers pass a loose map with the same fields", "Declare the fields in a named parameter type.")
}

// ArrayReturnBag finds a routine returns a loose map with fixed fields.
type ArrayReturnBag struct{}

func (ArrayReturnBag) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereIs(engine.Return).Where(engine.Match.ReturnsRecordBag).Get()
}

func (ArrayReturnBag) Sin() Text {
	return argumentSin("array-return-bag", "A routine returns a loose map with fixed fields", "Return a named record type.")
}

// CoupledFields finds an operation changes and checks the same fields together.
type CoupledFields struct{}

func (CoupledFields) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereFunction().Where(engine.Match.HoldsCoupledFields).Get()
}

func (CoupledFields) Sin() Text {
	return argumentSin("coupled-fields", "An operation changes and checks the same fields together", "Give the fields a type that owns their shared behavior.")
}

// FeatureEnvy finds an operation reads several pieces of another local type.
type FeatureEnvy struct{}

func (FeatureEnvy) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereFunction().Where(engine.Match.EnviesAnotherObject).Get()
}

func (FeatureEnvy) Sin() Text {
	return argumentSin("feature-envy", "An operation reads several pieces of another local type", "Move the operation next to the data it uses.")
}

// HandedOutCollection finds a routine exposes its own mutable collection.
type HandedOutCollection struct{}

func (HandedOutCollection) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereIs(engine.Return).Where(engine.Match.HandsOutCollection).Get()
}

func (HandedOutCollection) Sin() Text {
	return argumentSin("handed-out-collection", "A routine exposes its own mutable collection", "Expose the operation a caller needs or return a snapshot.")
}

// MutableValueObject finds a value type changes a field after construction.
type MutableValueObject struct{}

func (MutableValueObject) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereFunction().Where(engine.Match.MutatesValueObject).Get()
}

func (MutableValueObject) Sin() Text {
	return argumentSin("mutable-value-object", "A value type changes a field after construction", "Return a new value for the changed state.")
}

// MutableStaticState finds an operation writes shared static state.
type MutableStaticState struct{}

func (MutableStaticState) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereAssign().Where(engine.Match.MutatesStaticState).Get()
}

func (MutableStaticState) Sin() Text {
	return argumentSin("mutable-static-state", "An operation writes shared static state", "Give the state a lifetime and an owner.")
}

// ConstructorSideEffect finds a constructor calls a collaborator and drops the result.
type ConstructorSideEffect struct{}

func (ConstructorSideEffect) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereCall().Where(engine.Match.IsConstructorSideEffect).Get()
}

func (ConstructorSideEffect) Sin() Text {
	return argumentSin("constructor-side-effect", "A constructor calls a collaborator and drops the result", "Run the action in a named operation after construction.")
}

// DataClump finds the same group of scalar parameters on different types.
type DataClump struct{}

func (DataClump) Find(codebase *engine.Codebase) []engine.Match {
	return engine.DataClumps(codebase)
}

func (DataClump) GroupKey(method engine.Match) (string, bool) {
	signature := method.ValueSignature()

	return strings.Join(signature, ", "), len(signature) >= 3
}

func (DataClump) Sin() Text {
	return argumentSin("data-clump", "The same group of scalar parameters appears on different types", "Pass a value that owns the group of fields.")
}

func (DataClump) CrossFile() {}
