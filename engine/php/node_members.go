package php

import (
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/prose"
)

// EnclosingClassLike is the node itself when it declares a class-like, else the nearest one around it.
func (n Node) EnclosingClassLike() Node {
	for at := n; at.Exists(); at = at.Up() {
		if at.IsClassLike() {
			return at
		}
	}

	return Node{}
}

// IsBelowAMethodInItsClass says whether a method comes before the node among its class's members.
func (n Node) IsBelowAMethodInItsClass() bool {
	seenMethod := false
	for _, member := range n.EnclosingClassLike().ChildrenIn("stmts") {
		if member.Node() == n.Node() {
			return seenMethod
		}
		seenMethod = seenMethod || member.Kind() == "Stmt_ClassMethod"
	}

	return false
}

// BreaksClassLayoutOrder says whether a member of a class's head sits below one that ranks after it.
func (n Node) BreaksClassLayoutOrder() bool {
	class := n.EnclosingClassLike()
	if !class.Exists() {
		return false
	}
	rank := layoutRank(n.Match)
	for _, earlier := range classHead(class) {
		if earlier.Node() == n.Node() {
			return false
		}
		if layoutRank(earlier) > rank {
			return true
		}
	}

	return false
}

// classHead is a class's members above its first method.
func classHead(class Node) []engine.Match {
	var head []engine.Match
	for _, member := range class.ChildrenIn("stmts") {
		if member.Kind() == "Stmt_ClassMethod" {
			return head
		}
		head = append(head, member)
	}

	return head
}

// unknownRank ranks a member the layout does not order.
const unknownRank = 6

// layoutRank is where a member belongs in a class's head: trait uses, enum cases, constants, static properties,
// then properties by visibility, hooked properties after plain ones.
func layoutRank(member engine.Match) int {
	switch member.Kind() {
	case "Stmt_TraitUse":
		return 0
	case "Stmt_EnumCase":
		return 1
	case "Stmt_ClassConst":
		return 2
	case "Stmt_Property":
		modifiers := member.Node().Modifiers
		if slices.Contains(modifiers, "static") {
			return 3
		}
		base := 4
		if len(Node{Match: member}.ChildrenIn("hooks")) > 0 {
			base = 7
		}
		switch {
		case slices.Contains(modifiers, "private"):
			return base + 2
		case slices.Contains(modifiers, "protected"):
			return base + 1
		}

		return base
	}

	return unknownRank
}

// MethodName is a method declaration's name; empty for any other node.
func (n Node) MethodName() string {
	if n.Kind() != "Stmt_ClassMethod" {
		return ""
	}

	return n.Name()
}

// IsMagicMethod says whether the node declares a method whose name opens with a double underscore.
func (n Node) IsMagicMethod() bool {
	return strings.HasPrefix(n.MethodName(), "__")
}

// IsConstructorDeclaration says whether the node declares a constructor.
func (n Node) IsConstructorDeclaration() bool {
	return strings.EqualFold(n.MethodName(), "__construct")
}

// IsStaticMethod says whether the node declares a static method.
func (n Node) IsStaticMethod() bool {
	return n.Kind() == "Stmt_ClassMethod" && slices.Contains(n.Node().Modifiers, "static")
}

// ReturnTypeName is the return type a function-like writes; empty when it writes none.
func (n Node) ReturnTypeName() string {
	if !n.IsFunctionLike() {
		return ""
	}

	return Written(n.Node().Returns).Render()
}

// DeclaredReturnType is the written return type, lower-cased; empty when none is written.
func (n Node) DeclaredReturnType() string {
	return strings.ToLower(n.ReturnTypeName())
}

// ReturnsBool says whether the node declares a bool return.
func (n Node) ReturnsBool() bool {
	return n.DeclaredReturnType() == "bool"
}

// TakesNoArguments says whether the node declares a method with no parameters.
func (n Node) TakesNoArguments() bool {
	return n.Kind() == "Stmt_ClassMethod" && len(Params(n.Match)) == 0
}

// IsCommandMethod says whether the node declares a method that returns nothing, or returns its own instance.
func (n Node) IsCommandMethod() bool {
	switch n.DeclaredReturnType() {
	case "void", "never":
		return true
	case "static", "self", "$this":
		return !n.IsStaticMethod()
	}

	return false
}

// NameIsInherited says whether the node declares a method an ancestor of its class declares too.
func (n Node) NameIsInherited() bool {
	method := n.MethodName()

	return method != "" && ProgramOf(n.Codebase()).OverridesMethod(EnclosingClassName(n.Match), method)
}

// Argument is the value of the call's argument at the position; no node when there is none.
func (n Node) Argument(position int) Node {
	arguments := Arguments(n.Match)
	if position >= len(arguments) {
		return Node{}
	}

	return Node{Match: arguments[position].Child("value")}
}

// IsNewlineSeparator says whether the node is PHP_EOL or a string of nothing but line ends.
func (n Node) IsNewlineSeparator() bool {
	if n.Kind() == "Expr_ConstFetch" {
		return n.Child("name").Name() == "PHP_EOL"
	}
	value, ok := n.Text()

	return n.Kind() == "Scalar_String" && ok && value != "" && strings.Trim(value, "\r\n") == ""
}

// LiteralItems is the value of every item of an array literal that is a plain string.
func (n Node) LiteralItems() []string {
	var literals []string
	for _, item := range n.ChildrenIn("items") {
		if value := item.Child("value"); item.Kind() == "ArrayItem" && value.Kind() == "Scalar_String" {
			text, _ := value.Text()
			literals = append(literals, text)
		}
	}

	return literals
}

// IsReturnedValue says whether the node is the value a return statement returns.
func (n Node) IsReturnedValue() bool {
	return n.Exists() && n.Parent().Kind() == "Stmt_Return"
}

// IsOwnedKeyedLookup says whether the node reads an offset of one of its own object's properties: $this->items[$key].
func (n Node) IsOwnedKeyedLookup() bool {
	property := n.Child("var")

	return n.Kind() == "Expr_ArrayDimFetch" && property.Kind() == "Expr_PropertyFetch" &&
		property.Child("var").Kind() == "Expr_Variable" && property.Child("var").Name() == "this"
}

// SwitchesEntirelyOnABoolParam says whether the node's whole body is a two-way branch on one of its bool parameters.
func (n Node) SwitchesEntirelyOnABoolParam() bool {
	if !n.IsFunctionDeclaration() {
		return false
	}
	for _, param := range Params(n.Match) {
		variable := param.Child("var")
		if variable.Kind() == "Expr_Variable" && variable.Name() != "" &&
			strings.EqualFold(Written(param.Node().Declared).SimpleName(), "bool") && n.bodyIsTwoWayBranchOn(variable.Name(), testsVariable) {
			return true
		}
	}

	return false
}

// SwitchesEntirelyOnAnAbsentParam says whether the node's whole body is a two-way branch on whether one of its
// nullable parameters is null.
func (n Node) SwitchesEntirelyOnAnAbsentParam() bool {
	if !n.IsFunctionDeclaration() {
		return false
	}
	for _, param := range Params(n.Match) {
		variable := param.Child("var")
		if variable.Kind() == "Expr_Variable" && variable.Name() != "" &&
			Written(param.Node().Declared).IsNullable() && n.bodyIsTwoWayBranchOn(variable.Name(), testsAbsence) {
			return true
		}
	}

	return false
}

// bodyIsTwoWayBranchOn says whether the node's only statement is an if/else or a two-arm match on the variable, each
// side doing work of its own.
func (n Node) bodyIsTwoWayBranchOn(name string, tests func(engine.Match, string) bool) bool {
	body := n.ChildrenIn("stmts")
	if !n.IsFunctionDeclaration() || len(body) != 1 {
		return false
	}
	only := Node{Match: body[0]}
	if only.Kind() == "Stmt_If" {
		otherwise := Node{Match: only.Child("else")}

		return len(only.ChildrenIn("elseifs")) == 0 && otherwise.Exists() && armDoesWork(only.ChildrenIn("stmts")) &&
			armDoesWork(otherwise.ChildrenIn("stmts")) && tests(only.Child("cond"), name)
	}
	if only.Kind() != "Stmt_Return" && only.Kind() != "Stmt_Expression" {
		return false
	}
	match := Node{Match: only.Child("expr")}
	arms := match.ChildrenIn("arms")

	return match.Kind() == "Expr_Match" && len(arms) == 2 &&
		!slices.ContainsFunc(arms, func(arm engine.Match) bool { return isBareValue(arm.Child("body")) }) &&
		tests(match.Child("cond"), name)
}

func testsVariable(condition engine.Match, name string) bool {
	if condition.Kind() == "Expr_BooleanNot" {
		condition = condition.Child("expr")
	}

	return condition.Kind() == "Expr_Variable" && condition.Name() == name
}

func testsAbsence(condition engine.Match, name string) bool {
	if condition.Kind() == "Expr_BooleanNot" {
		condition = condition.Child("expr")
	}
	switch condition.Kind() {
	case "Expr_BinaryOp_Identical", "Expr_BinaryOp_NotIdentical":
		left, right := condition.Child("left"), condition.Child("right")

		return isNullFetch(right) && testsVariable(left, name) || isNullFetch(left) && testsVariable(right, name)
	case "Expr_FuncCall":
		function := condition.Child("name")
		arguments := Arguments(condition)

		return isName(function) && strings.EqualFold(function.Name(), "is_null") && len(arguments) > 0 &&
			testsVariable(arguments[0].Child("value"), name)
	}

	return false
}

// isNullFetch says whether the node is the constant null, in any case.
func isNullFetch(node engine.Match) bool {
	return node.Kind() == "Expr_ConstFetch" && strings.EqualFold(node.Child("name").Name(), "null")
}

// armDoesWork says whether a branch's statements do more than hand back a bare value.
func armDoesWork(statements []engine.Match) bool {
	if len(statements) != 1 {
		return len(statements) > 0
	}

	return !(statements[0].Kind() == "Stmt_Return" && isBareValue(statements[0].Child("expr")))
}

// isBareValue says whether an expression is nothing, a scalar or a constant.
func isBareValue(expression engine.Match) bool {
	return !expression.Exists() || strings.HasPrefix(expression.Kind(), "Scalar_") ||
		expression.Kind() == "Expr_ConstFetch" || expression.Kind() == "Expr_ClassConstFetch"
}

// CallsFunction says whether the node calls the named function, as written.
func (n Node) CallsFunction(name string) bool {
	return n.Kind() == "Expr_FuncCall" && isName(n.Child("name")) && n.Child("name").Name() == name
}

// ArgumentArrayLiteral is the array literal the call passes at the position: written there, or assigned to the
// variable passed there; no node when neither.
func (n Node) ArgumentArrayLiteral(position int) Node {
	argument := n.Argument(position)
	if argument.Kind() == "Expr_Array" {
		return argument
	}
	if argument.Kind() != "Expr_Variable" {
		return Node{}
	}
	for _, interaction := range Trace(argument.Match) {
		assigned := interaction.Node.Parent()
		if value := assigned.Child("expr"); assigned.Kind() == "Expr_Assign" && value.Kind() == "Expr_Array" {
			return Node{Match: value}
		}
	}

	return Node{}
}

// IsNonFinalClass says whether the node is a class neither final nor abstract.
func (n Node) IsNonFinalClass() bool {
	modifiers := n.Node().Modifiers

	return n.Kind() == "Stmt_Class" && !slices.Contains(modifiers, "final") && !slices.Contains(modifiers, "abstract")
}

// EveryConstructorParamNullable says whether the node is a class whose constructor promotes parameters, every one of
// them nullable.
func (n Node) EveryConstructorParamNullable() bool {
	if n.Kind() != "Stmt_Class" {
		return false
	}
	promoted := 0
	for _, param := range ConstructorParams(n.Match) {
		if len(param.Node().Modifiers) == 0 {
			continue
		}
		promoted++
		if !Written(param.Node().Declared).IsNullable() {
			return false
		}
	}

	return promoted > 0
}

var (
	methodTag  = regexp.MustCompile(`^\s*\*?\s*@method\b`)
	methodName = regexp.MustCompile(`(\w+)\s*\(`)
)

// DocblockMethodTagRedeclaresRealMethod says whether the node's doc comment declares an @method the class-like also
// declares for real.
func (n Node) DocblockMethodTagRedeclaresRealMethod() bool {
	doc, ok := n.DocComment()
	if !n.IsClassLike() || !ok {
		return false
	}
	declared := map[string]bool{}
	for _, method := range Methods(n.Match) {
		declared[strings.ToLower(method.Name())] = true
	}
	for _, line := range prose.Lines(doc.Text) {
		if !methodTag.MatchString(line) {
			continue
		}
		if names := methodName.FindAllStringSubmatch(line, -1); len(names) > 0 && declared[strings.ToLower(names[len(names)-1][1])] {
			return true
		}
	}

	return false
}

// IsField says whether the node declares a field: a promoted parameter or a property.
func (n Node) IsField() bool {
	return n.Kind() == "Param" && len(n.Node().Modifiers) > 0 || n.Kind() == "Stmt_Property"
}
