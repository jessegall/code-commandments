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
		Members:       engine.InFields("stmts"),
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
		TestFile:      testFile,
		Implicit:      magic,
		Labels:        func(name engine.Match) bool { return name.Parent().Kind() == "Arg" && name.Node().Field == "name" },
		NamespaceOf: func(symbol string) string {
			class, _, _ := strings.Cut(symbol, "::")

			return engine.Before(strings.TrimSuffix(class, "()"), `\`)
		},
		Continues: engine.KindIn("Stmt_ElseIf"),
		// PHP hangs a body straight off its construct, as its statements, so there is no block to read through.
		Body:        engine.InFields("stmts"),
		Otherwise:   otherwise,
		Condition:   condition,
		ElementName: elementName,
		Computes:    computes,
	})

}

// binding says whether a statement only binds a name to a value: PHP files every assignment as an expression
// wrapped in a statement of its own.
func binding(statement engine.Match) bool {
	if statement.Kind() != "Stmt_Expression" {
		return false
	}

	return statement.Child("expr").Is(engine.Assignment)
}

// testFile says whether a PHP file is a test's: PHPUnit's `*Test.php`, or any file under a tests folder.
func testFile(path, _ string) bool {
	return strings.HasSuffix(path, "Test.php") || engine.InFolderNamed(path, "tests", "test")
}

// values are the values a call's arguments hand it, named and unpacked ones alike; a first-class callable's
// `(...)` hands none.
func values(call engine.Match) []engine.Match {
	var handed []engine.Match
	for _, argument := range call.ChildrenIn("args") {
		if value := argument.Child("value"); value.Exists() {
			handed = append(handed, value)
		}
	}

	return handed
}

// constructed is the class a `new` names.
func constructed(construction engine.Match) engine.Match {
	if construction.Kind() != "Expr_New" {
		return engine.Match{}
	}

	return construction.Child("class")
}

// callers are the calls reaching a function or a method: a method's the index finds sent to its class or a
// subclass, static or not, and a function's the calls naming it.
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

// attributes are the names of the attributes a declaration carries, in every group.
func attributes(declaration engine.Match) []engine.Match {
	var names []engine.Match
	for _, group := range declaration.ChildrenIn("attrGroups") {
		for _, attribute := range group.ChildrenIn("attrs") {
			names = append(names, attribute.Child("name"))
		}
	}

	return names
}

// magic says whether PHP itself calls the method: a constructor, a destructor or any other magic method.
func magic(declaration engine.Match) bool {
	return declaration.Kind() == "Stmt_ClassMethod" && Node{Match: declaration}.IsMagicMethod()
}

// phpDocTags are the tags of a declaration's PHPDoc comments, each by its bare name: `@param $x` is param.
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

// otherwise are the statements a branch runs when its condition does not hold. PHP files a ladder FLAT: an `if`
// keeps its `elseif`s in a list of its own, beside its `else`, rather than nesting each in the one before. So the
// next rung is the next entry of that list, and the `else` is only reached once the list is spent.
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

// condition is what a branch or a loop decides by. A `switch` holds its subject under the same name, which its
// cases are matched against rather than decided by, and a `foreach` decides by nothing at all.
func condition(held engine.Match) engine.Match {
	if !slices.Contains([]string{"Stmt_If", "Stmt_ElseIf", "Stmt_While", "Stmt_Do", "Expr_Ternary"}, held.Kind()) {
		return engine.Match{}
	}

	return held.Child("cond")
}

// elementName is the name a foreach binds for each item: the `$line` of `foreach ($order->lines as $line)`.
// Nothing for a `for` or a `while`, which bind no item, and nothing for a foreach that unpacks its item into
// several names, since no one name holds it.
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

// computes says whether the expression works a value out of its parts: PHP names every binary operator, every
// compound assignment and every unary one with a prefix of its own, so the prefix is the whole answer.
func computes(held engine.Match) bool {
	kind := held.Kind()
	for _, prefix := range []string{"Expr_BinaryOp_", "Expr_AssignOp_"} {
		if strings.HasPrefix(kind, prefix) {
			return true
		}
	}

	return slices.Contains([]string{"Expr_UnaryMinus", "Expr_UnaryPlus", "Expr_BooleanNot", "Expr_BitwiseNot", "Expr_PreInc", "Expr_PreDec", "Expr_PostInc", "Expr_PostDec"}, kind)
}
