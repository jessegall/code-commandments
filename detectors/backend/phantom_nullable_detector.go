package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// PhantomNullableDetector finds a nullable field whose value every read, followed through the program, assumes is set and none guards: the null is a phantom the type should drop.
type PhantomNullableDetector struct{}

func init() { detectors.Register(catalog.PHP, PhantomNullableDetector{}) }

func (PhantomNullableDetector) Sin() sins.Sin { return backendsins.PhantomNullable{} }

func (PhantomNullableDetector) WholeTree() {}

func (PhantomNullableDetector) ChainPath(finding engine.Match, codebase *engine.Codebase) []string {
	field := fieldName(finding)
	if field == "" {
		return nil
	}

	return php.ValueFlowOf(codebase).ChainPath(php.EnclosingClassName(finding), field)
}

func (PhantomNullableDetector) Find(codebase *engine.Codebase) []engine.Match {
	flow := php.ValueFlowOf(codebase)
	var findings []engine.Match
	for _, class := range php.In(codebase).WhereKind("Stmt_Class").Get() {
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

func nullableFields(class engine.Match) []engine.Match {
	var fields []engine.Match
	for _, param := range php.ConstructorParams(class) {
		if len(param.Node().Modifiers) > 0 && php.Written(param.Node().Declared).IsNullable() && fieldName(param) != "" {
			fields = append(fields, param)
		}
	}
	for _, member := range (php.Node{Match: class}).ChildrenIn("stmts") {
		if member.Kind() != "Stmt_Property" || !php.Written(member.Node().Declared).IsNullable() {
			continue
		}
		fields = append(fields, (php.Node{Match: member}).ChildrenIn("props")...)
	}

	return fields
}

func fieldName(field engine.Match) string {
	switch field.Kind() {
	case "Param":
		return field.Child("var").Name()
	case "PropertyItem":
		return field.Name()
	}

	return ""
}

func (PhantomNullableDetector) CrossFile() {}
