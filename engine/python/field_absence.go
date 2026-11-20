package python

import "github.com/jessegall/code-commandments/engine"

func phantomFields(class engine.Match) []engine.Match {
	if class.Kind() != "ClassDef" {
		return nil
	}
	var fields []engine.Match
	for _, field := range Of(class.Codebase()).PhantomNullableFields(Node{Match: class}) {
		fields = append(fields, field.Match)
	}
	return fields
}
func nullableLookup(match engine.Match) bool {
	node := Node{Match: match}
	if !node.IsCall() || !node.IsEvaluated() || !node.IsReturnedValue() || !node.MissesToNone() || !node.LooksUpOwnDict() {
		return false
	}
	program := Of(match.Codebase())
	return program.NoCallerHandlesTheAbsence(node.EnclosingFunction()) && !program.IsOverride(node.EnclosingFunction())
}
