package php

import (
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/prose"
)

func (n Node) EnclosingClassLike() Node {
	for at := n; at.Exists(); at = at.Up() {
		if at.IsClassLike() {
			return at
		}
	}

	return Node{}
}

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

const unknownRank = 6

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

func (n Node) MethodName() string {
	if n.Kind() != "Stmt_ClassMethod" {
		return ""
	}

	return n.Name()
}

func (n Node) IsMagicMethod() bool {
	return strings.HasPrefix(n.MethodName(), "__")
}

func (n Node) IsConstructorDeclaration() bool {
	return strings.EqualFold(n.MethodName(), "__construct")
}

func (n Node) IsStaticMethod() bool {
	return n.Kind() == "Stmt_ClassMethod" && slices.Contains(n.Node().Modifiers, "static")
}

func (n Node) ReturnTypeName() string {
	if !n.IsFunctionLike() {
		return ""
	}

	return Written(n.Node().Returns).Render()
}

func (n Node) DeclaredReturnType() string {
	return strings.ToLower(n.ReturnTypeName())
}

func (n Node) ReturnsBool() bool {
	return n.DeclaredReturnType() == "bool"
}

func (n Node) TakesNoArguments() bool {
	return n.Kind() == "Stmt_ClassMethod" && len(Params(n.Match)) == 0
}

func (n Node) IsCommandMethod() bool {
	switch n.DeclaredReturnType() {
	case "void", "never":
		return true
	case "static", "self", "$this":
		return !n.IsStaticMethod()
	}

	return false
}

func (n Node) NameIsInherited() bool {
	method := n.MethodName()

	return method != "" && ProgramOf(n.Codebase()).OverridesMethod(EnclosingClassName(n.Match), method)
}

func (n Node) Argument(position int) Node {
	arguments := Arguments(n.Match)
	if position >= len(arguments) {
		return Node{}
	}

	return Node{Match: arguments[position].Child("value")}
}

func (n Node) IsNewlineSeparator() bool {
	if n.Kind() == "Expr_ConstFetch" {
		return n.Child("name").Name() == "PHP_EOL"
	}
	value, ok := n.Text()

	return n.Kind() == "Scalar_String" && ok && value != "" && strings.Trim(value, "\r\n") == ""
}

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

func (n Node) IsReturnedValue() bool {
	return n.Exists() && n.Parent().Kind() == "Stmt_Return"
}

func (n Node) IsOwnedKeyedLookup() bool {
	property := n.Child("var")

	return n.Kind() == "Expr_ArrayDimFetch" && property.Kind() == "Expr_PropertyFetch" &&
		property.Child("var").Kind() == "Expr_Variable" && property.Child("var").Name() == "this"
}

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

func isNullFetch(node engine.Match) bool {
	return node.Kind() == "Expr_ConstFetch" && strings.EqualFold(node.Child("name").Name(), "null")
}

func armDoesWork(statements []engine.Match) bool {
	if len(statements) != 1 {
		return len(statements) > 0
	}

	return !(statements[0].Kind() == "Stmt_Return" && isBareValue(statements[0].Child("expr")))
}

func isBareValue(expression engine.Match) bool {
	return !expression.Exists() || strings.HasPrefix(expression.Kind(), "Scalar_") ||
		expression.Kind() == "Expr_ConstFetch" || expression.Kind() == "Expr_ClassConstFetch"
}

func (n Node) CallsFunction(name string) bool {
	return n.Kind() == "Expr_FuncCall" && isName(n.Child("name")) && n.Child("name").Name() == name
}

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

func (n Node) IsNonFinalClass() bool {
	modifiers := n.Node().Modifiers

	return n.Kind() == "Stmt_Class" && !slices.Contains(modifiers, "final") && !slices.Contains(modifiers, "abstract")
}

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

func (n Node) IsField() bool {
	return n.Kind() == "Param" && len(n.Node().Modifiers) > 0 || n.Kind() == "Stmt_Property"
}

func (n Node) ClassHead() []engine.Match {
	return classHead(n)
}

func (n Node) GroupedClassHead() [][]engine.Match {
	byRank := map[int][]engine.Match{}
	var ranks []int
	for _, member := range classHead(n) {
		rank := layoutRank(member)
		if _, seen := byRank[rank]; !seen {
			ranks = append(ranks, rank)
		}
		byRank[rank] = append(byRank[rank], member)
	}
	slices.Sort(ranks)
	groups := make([][]engine.Match, 0, len(ranks))
	for _, rank := range ranks {
		groups = append(groups, byRank[rank])
	}

	return groups
}

func (n Node) FirstMethodOfItsClass() (engine.Match, bool) {
	for _, member := range n.EnclosingClassLike().ChildrenIn("stmts") {
		if member.Kind() == "Stmt_ClassMethod" {
			return member, true
		}
	}

	return engine.Match{}, false
}

func PromotedParamName(params []engine.Match, index int) (string, bool) {
	if index >= len(params) {
		return "", false
	}
	param := params[index]
	name := variableName(param.Child("var"))
	if len(param.Node().Modifiers) == 0 || slices.Contains(param.Node().Flags, "variadic") || name == "" {
		return "", false
	}

	return name, true
}

const crossings = 3

func (n Node) ReachesThroughThreeTypes() bool {
	if !n.isTheEndOfAChain() {
		return false
	}
	program := ProgramOf(n.Codebase())
	types := TypesOf(n.Codebase())
	own := EnclosingClassName(n.Match)
	crossed := map[string]bool{}
	for _, link := range n.chainLinks() {
		held := strings.TrimLeft(types.TypeOf(link), `?\`)
		if _, declared := program.Declarations()[held]; declared && held != own {
			crossed[held] = true
		}
	}

	return len(crossed) >= crossings
}

func (n Node) isTheEndOfAChain() bool {
	if !slices.Contains([]string{"Expr_MethodCall", "Expr_NullsafeMethodCall", "Expr_PropertyFetch", "Expr_NullsafePropertyFetch"}, n.Kind()) {
		return false
	}
	parent := n.Up()
	if !slices.Contains([]string{"Expr_MethodCall", "Expr_NullsafeMethodCall", "Expr_PropertyFetch", "Expr_NullsafePropertyFetch"}, parent.Kind()) {
		return true
	}

	return parent.Child("var").Node() != n.Node()
}

func (n Node) chainLinks() []engine.Match {
	var links []engine.Match
	held := n
	for held.Exists() {
		if !slices.Contains([]string{"Expr_MethodCall", "Expr_NullsafeMethodCall", "Expr_PropertyFetch", "Expr_NullsafePropertyFetch"}, held.Kind()) {
			break
		}
		links = append(links, held.Child("var"))
		held = Node{Match: held.Child("var")}
	}

	return links
}
