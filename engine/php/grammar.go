package php

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func init() {
	engine.ReadAs(contract.PHP, engine.Grammar{
		Arguments:     values,
		Receiver:      engine.InAnyField("var", "class"),
		Binds:         binding,
		Members:       declaredMembers,
		Parameters:    engine.InFields("params"),
		Extends:       engine.InFields("extends"),
		Implements:    engine.InFields("implements"),
		Traits:        traitNames,
		DeclaredName:  propertyName,
		ClassOf:       classOf,
		TypeOf:        typeOf,
		Annotations:   attributes,
		TypeKind:      engine.Kinds(map[string]string{"Stmt_Class": "class", "Stmt_Interface": "interface", "Stmt_Enum": "enum", "Stmt_Trait": "trait"}),
		ReturnType:    engine.InField("returnType"),
		ParameterType: engine.InField("type"),
		Constructs:    constructed,
		Callers:       callers,
		DocTags:       phpDocTags,
		BodyHash:      func(function engine.Match) string { return Node{Match: function}.BodyHash() },
		CodeWords:     func(node engine.Match) []string { return Node{Match: node}.CodeWords() },
		ElementOf:     func(node engine.Match) *contract.Type { return TypesOf(node.Codebase()).ElementOf(node) },
		NodeHash: func(node engine.Match, normalize bool) string {
			return engine.SyntaxHash([]engine.Match{node}, Hashing, normalize)
		},
		TestFile: testFile,
		Implicit: magic,
		Labels:   func(name engine.Match) bool { return name.Parent().Kind() == "Arg" && name.Node().Field == "name" },
		NamespaceOf: func(symbol string) string {
			class, _, _ := strings.Cut(symbol, "::")

			return engine.Before(strings.TrimSuffix(class, "()"), `\`)
		},
		Continues:         engine.KindIn("Stmt_ElseIf"),
		Body:              engine.InFields("stmts"),
		Otherwise:         otherwise,
		Condition:         condition,
		ComparisonSubject: comparisonSubject,
		ElementName:       elementName,
		Computes:          computes,
	})

}

func declaredMembers(declaration engine.Match) []engine.Match {
	declared := declaration.ChildrenIn("stmts")
	for _, parameter := range ConstructorParams(declaration) {
		if slices.Contains(parameter.Node().Flags, "promoted") {
			declared = append(declared, parameter)
		}
	}

	return declared
}

func binding(statement engine.Match) bool {
	if statement.Kind() != "Stmt_Expression" {
		return false
	}

	return statement.Child("expr").Is(engine.Assignment)
}

func testFile(path, _ string) bool {
	return strings.HasSuffix(path, "Test.php") || engine.InFolderNamed(path, "tests", "test")
}

func values(call engine.Match) []engine.Match {
	var handed []engine.Match
	for _, argument := range call.ChildrenIn("args") {
		if value := argument.Child("value"); value.Exists() {
			handed = append(handed, value)
		}
	}

	return handed
}

func constructed(construction engine.Match) engine.Match {
	if construction.Kind() != "Expr_New" {
		return engine.Match{}
	}

	return construction.Child("class")
}

func callers(function engine.Match) []engine.Match {
	switch function.Kind() {
	case "Stmt_Function":
		return IndexOf(function.Codebase()).FunctionCallersOf(function)
	case "Stmt_ClassMethod":
		if class := function.EnclosingType(); class.Node().Symbol != "" {
			return IndexOf(function.Codebase()).CallersOf(class.Node().Symbol, function.Name())
		}
	}

	return nil
}

func attributes(declaration engine.Match) []engine.Match {
	var carried []engine.Match
	for _, group := range declaration.ChildrenIn("attrGroups") {
		carried = append(carried, group.ChildrenIn("attrs")...)
	}

	return carried
}

func magic(declaration engine.Match) bool {
	return declaration.Kind() == "Stmt_ClassMethod" && Node{Match: declaration}.IsMagicMethod()
}

func phpDocTags(declaration engine.Match) []string {
	var tags []string
	for _, comment := range declaration.DocComments() {
		for _, tag := range docTags(comment) {
			name, _, _ := strings.Cut(strings.TrimPrefix(tag, "@"), " ")
			tags = append(tags, name)
		}
	}

	return tags
}

func otherwise(branch engine.Match) []engine.Match {
	owner := branch
	if branch.Kind() == "Stmt_ElseIf" {
		owner = branch.Parent()
	}
	if owner.Kind() != "Stmt_If" {
		return nil
	}
	rungs := owner.ChildrenIn("elseifs")
	next, found := 0, branch.Kind() != "Stmt_ElseIf"
	for at, rung := range rungs {
		if rung.Node() == branch.Node() {
			next, found = at+1, true
		}
	}
	if !found {
		return nil
	}
	if next < len(rungs) {
		return []engine.Match{rungs[next]}
	}

	return owner.Child("else").Body()
}

func condition(held engine.Match) engine.Match {
	if !slices.Contains([]string{"Stmt_If", "Stmt_ElseIf", "Stmt_While", "Stmt_Do", "Expr_Ternary"}, held.Kind()) {
		return engine.Match{}
	}

	return held.Child("cond")
}

var constantSides = []string{"Scalar_String", "Scalar_Int", "Scalar_Float", "Expr_ClassConstFetch", "Expr_ConstFetch"}

func comparisonSubject(held engine.Match) engine.Match {
	if !slices.Contains([]string{"===", "==", "!==", "!="}, held.Operator()) {
		return engine.Match{}
	}

	left, right := held.Child("left"), held.Child("right")
	leftIsConstant, rightIsConstant := isConstantSide(left), isConstantSide(right)

	switch {
	case !leftIsConstant && rightIsConstant:
		return left
	case leftIsConstant && !rightIsConstant:
		return right
	}

	return engine.Match{}
}

func isConstantSide(side engine.Match) bool {
	return slices.Contains(constantSides, side.Kind())
}

func elementName(loop engine.Match) string {
	if loop.Kind() != "Stmt_Foreach" {
		return ""
	}
	element := loop.Child("valueVar")
	if element.Kind() != "Expr_Variable" {
		return ""
	}

	return element.Name()
}

func computes(held engine.Match) bool {
	kind := held.Kind()
	for _, prefix := range []string{"Expr_BinaryOp_", "Expr_AssignOp_"} {
		if strings.HasPrefix(kind, prefix) {
			return true
		}
	}

	return slices.Contains([]string{"Expr_UnaryMinus", "Expr_UnaryPlus", "Expr_BooleanNot", "Expr_BitwiseNot", "Expr_PreInc", "Expr_PreDec", "Expr_PostInc", "Expr_PostDec"}, kind)
}
