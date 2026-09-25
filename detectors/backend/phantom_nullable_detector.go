package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// PhantomNullableDetector finds a nullable field whose value every read, followed through the program, assumes is
// set and none guards: the null is a phantom the type should drop.
type PhantomNullableDetector struct{}

func init() { detectors.Register(catalog.Backend, PhantomNullableDetector{}) }

// Sin is the sin the detector finds.
func (PhantomNullableDetector) Sin() sins.Sin { return backendsins.PhantomNullable{} }

// WholeTree says the verdict follows the value into other files.
func (PhantomNullableDetector) WholeTree() {}

// ChainPath is the route the field's value takes to where it is assumed, as kind@file steps.
func (PhantomNullableDetector) ChainPath(finding engine.Match, codebase *engine.Codebase) []string {
	field := fieldName(finding)
	if field == "" {
		return nil
	}

	return php.ValueFlowOf(codebase).ChainPath(php.EnclosingClassName(finding), field)
}

// Find is every nullable field, promoted or declared, that some read assumes set and no read guards, unless its
// TypeScript type is written nullable by hand.
func (PhantomNullableDetector) Find(codebase *engine.Codebase) []engine.Match {
	flow := php.ValueFlowOf(codebase)
	var findings []engine.Match
	for _, class := range codebase.WhereKind("Stmt_Class").Get() {
		for _, field := range nullableFields(class) {
			if (php.Node{Match: field}).DeclaresNullableWireType() {
				continue
			}
			verdict := flow.Verdict(class.Node().Symbol, fieldName(field))
			if verdict.Assume >= 1 && verdict.Guard == 0 {
				findings = append(findings, field)
			}
		}
	}

	return findings
}

// nullableFields is the class's nullable promoted parameters and nullable property items, in that order.
func nullableFields(class engine.Match) []engine.Match {
	var fields []engine.Match
	for _, param := range php.ConstructorParams(class) {
		if len(param.Node().Modifiers) > 0 && php.Written(param.Node().Declared).IsNullable() && fieldName(param) != "" {
			fields = append(fields, param)
		}
	}
	for _, member := range (php.Node{Match: class}).In("stmts") {
		if member.Kind() != "Stmt_Property" || !php.Written(member.Node().Declared).IsNullable() {
			continue
		}
		fields = append(fields, (php.Node{Match: member}).In("props")...)
	}

	return fields
}

// fieldName is the name a promoted parameter or a property item declares.
func fieldName(field engine.Match) string {
	switch field.Kind() {
	case "Param":
		return field.Child("var").Name()
	case "PropertyItem":
		return field.Name()
	}

	return ""
}
