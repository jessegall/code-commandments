package shared

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
	skills "github.com/jessegall/code-commandments/skill/shared"
)

// MaskedInvariant checks an optional value that the operation already needs.
type MaskedInvariant struct{}

func (MaskedInvariant) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.Where(engine.Match.MasksFieldInvariant).Get()
}

func (MaskedInvariant) Sin() Text {
	return Text{Name: "masked-invariant", Skill: skills.Absence{}, Description: "Masking an optional field behind a literal hides a value the design requires", Rule: "Keep required state non-null, or pass it directly to the operation that uses it.", Suggestion: "Remove the fallback by giving the value a certain type where it is set.", Formerly: map[catalog.Engine]Former{catalog.Python: {Sin: "python-masked-invariant"}}}
}

func (MaskedInvariant) CrossFile() {}

// NullableCallback checks an optional value that the operation already needs.
type NullableCallback struct{}

func (NullableCallback) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereFunction().Where(engine.Match.HasNullableCallback).Get()
}

func (NullableCallback) Sin() Text {
	return Text{Name: "nullable-callback", Skill: skills.Absence{}, Description: "A callback is declared optional, checked for absence, and then invoked", Rule: "Give a callback a usable default when every caller must be able to invoke it.", Suggestion: "Use a named no-op or require the callback as a parameter.", Formerly: map[catalog.Engine]Former{catalog.Python: {Sin: "python-nullable-callback"}}}
}

// NullableRegistryLookup finds a registry that answers a missing key with absence.
type NullableRegistryLookup struct{}

func (NullableRegistryLookup) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.Where(engine.Match.ReturnsNullableLookup).Get()
}
func (NullableRegistryLookup) Sin() Text {
	return Text{Name: "nullable-registry-lookup", Skill: skills.Absence{}, Description: "A keyed store returns absence on a miss", Rule: "Resolve a required key or fail by name.", Suggestion: "Make the lookup return a value or report the missing key.", Formerly: map[catalog.Engine]Former{catalog.Python: {Sin: "python-nullable-registry-lookup"}}}
}

// PhantomNullable finds an optional field that its reads assume is always present.
type PhantomNullable struct{}

func (PhantomNullable) Find(codebase *engine.Codebase) []engine.Match {
	var fields []engine.Match
	for _, owner := range codebase.WhereTypeDeclaration().Get() {
		fields = append(fields, owner.PhantomFields()...)
	}
	return fields
}
func (PhantomNullable) ChainPath(finding engine.Match, _ *engine.Codebase) []string {
	return finding.AbsenceTrace()
}
func (PhantomNullable) Sin() Text {
	return Text{Name: "phantom-nullable", Skill: skills.Absence{}, Description: "Every dereference assumes the declared optional value is present", Rule: "Make the field's declared type agree with the values it can hold.", Suggestion: "Require the value where it is constructed, or handle its absence at the read.", Formerly: map[catalog.Engine]Former{catalog.Python: {Sin: "python-phantom-nullable"}}}
}
func (PhantomNullable) CrossFile() {}
