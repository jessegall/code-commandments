package php

import (
	"slices"
	"strconv"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func (n Node) IsEmptyString() bool {
	text, ok := n.Text()

	return n.Kind() == "Scalar_String" && ok && text == ""
}

func (n Node) IsBlankString() bool {
	return n.IsEmptyString() || RendersBlank(n.Codebase(), n.NewClassName())
}

func (n Node) IsParameterDefault() bool {
	return n.Exists() && n.Parent().Kind() == "Param" && n.Node().Field == "default"
}

func (n Node) IsDeclarationDefault() bool {
	return n.IsParameterDefault() || n.Exists() && n.Parent().Kind() == "PropertyItem"
}

func (n Node) DeclaredType() *contract.Type {
	if parent := n.Parent(); parent.Kind() == "Param" {
		return parent.Node().Declared
	}
	if property := n.Parent().Parent(); property.Kind() == "Stmt_Property" {
		return property.Node().Declared
	}

	return nil
}

func (n Node) DeclarationBecomesProperty() bool {
	parent := n.Parent()

	return n.Exists() && (parent.Kind() == "PropertyItem" || parent.Kind() == "Param" && len(parent.Node().Modifiers) > 0)
}

func (n Node) DeclaredName() string {
	parent := n.Parent()
	if parent.Kind() == "PropertyItem" {
		return parent.Name()
	}
	if variable := parent.Child("var"); parent.Kind() == "Param" && variable.Kind() == "Expr_Variable" {
		return variable.Name()
	}

	return ""
}

func (n Node) DefaultedNameTestedForBlankness() bool {
	var tested []string
	if n.DeclarationBecomesProperty() {
		tested = n.EnclosingClassLike().testedForBlankness(selfPropertyOf, true)
	} else {
		tested = n.EnclosingFunctionLike().testedForBlankness(variableName, true)
	}

	return slices.Contains(tested, n.DeclaredName())
}

func (n Node) VariablesTestedForBlankness(viaPredicates bool) []string {
	return n.testedForBlankness(variableName, viaPredicates)
}

func variableName(node engine.Match) string {
	if node.Kind() != "Expr_Variable" {
		return ""
	}

	return node.Name()
}

func (n Node) testedForBlankness(subject func(engine.Match) string, viaPredicates bool) []string {
	var tested []string
	add := func(name string) {
		if name != "" && !slices.Contains(tested, name) {
			tested = append(tested, name)
		}
	}
	nodes := withDescendants(n.Match)
	for _, node := range nodes {
		if node.Kind() != "Expr_BinaryOp_Identical" && node.Kind() != "Expr_BinaryOp_NotIdentical" {
			continue
		}
		left, right := node.Child("left"), node.Child("right")
		if (Node{Match: right}).IsEmptyString() {
			add(subject(left))
		}
		if (Node{Match: left}).IsEmptyString() {
			add(subject(right))
		}
	}
	for _, node := range nodes {
		if node.Kind() == "Expr_Empty" {
			add(subject(node.Child("expr")))
		}
	}
	if !viaPredicates {
		return tested
	}
	for _, node := range nodes {
		call := Node{Match: node}
		if asked := call.Argument(0); node.Kind() == "Expr_StaticCall" && asked.Exists() && DecidesBlankness(n.Codebase(), call.StaticCallClass(), call.StaticCallMethod()) {
			add(subject(asked.Match))
		}
	}

	return tested
}

func (n Node) StaticCallClass() string {
	class := n.Child("class")
	if n.Kind() != "Expr_StaticCall" || !isName(class) {
		return ""
	}
	if class.Name() == "self" || class.Name() == "static" {
		return EnclosingClassName(n.Match)
	}

	return class.Name()
}

func (n Node) StaticCallMethod() string {
	if name := n.Child("name"); n.Kind() == "Expr_StaticCall" && name.Kind() == "Identifier" {
		return name.Name()
	}

	return ""
}

func (n Node) IsEmptyLiteral() bool {
	switch n.Kind() {
	case "Scalar_String":
		return n.IsEmptyString()
	case "Expr_Array":
		return len(n.ChildrenIn("items")) == 0
	case "Scalar_Int", "Scalar_Float":
		text, _ := n.Text()
		number, err := strconv.ParseFloat(text, 64)

		return err == nil && number == 0
	case "Expr_ConstFetch":
		return n.IsFalse()
	}

	return false
}

func (n Node) IsCancelledCoalesce() bool {
	parent := n.Parent()
	switch parent.Kind() {
	case "Expr_BinaryOp_Identical", "Expr_BinaryOp_NotIdentical", "Expr_BinaryOp_Equal", "Expr_BinaryOp_NotEqual":
	default:
		return false
	}
	if !n.IsCoalesce() {
		return false
	}
	other := parent.Child("left")
	if n.Node().Field == "left" {
		other = parent.Child("right")
	}

	return other.SameSyntax(n.Child("right"))
}

func (n Node) IsConditionalArraySpread() bool {
	chosen, otherwise := n.Child("if"), n.Child("else")
	if !n.IsTernary() || !chosen.Exists() || chosen.Kind() != "Expr_Array" || otherwise.Kind() != "Expr_Array" {
		return false
	}
	if (len(Node{Match: chosen}.ChildrenIn("items")) == 0) == (len(Node{Match: otherwise}.ChildrenIn("items")) == 0) {
		return false
	}
	parent := n.Parent()
	if parent.Kind() == "ArrayItem" && slices.Contains(parent.Node().Flags, "spread") && n.Node().Field == "value" {
		return true
	}
	call := parent.Parent()

	return parent.Kind() == "Arg" && n.Node().Field == "value" && call.Kind() == "Expr_FuncCall" &&
		isName(call.Child("name")) && strings.EqualFold(call.Child("name").Name(), "array_merge")
}

func (n Node) ReturnsNullableObject() bool {
	return n.IsFunctionDeclaration() && Written(n.Node().Returns).NullableClass() != ""
}

func (n Node) FillsSlotTyped(written string) bool {
	if n.IsDeclarationDefault() {
		return Written(n.DeclaredType()).Render() == written
	}

	return n.IsReturnedValue() && Written(n.EnclosingFunctionLike().Node().Returns).Render() == written
}

func (n Node) FillsArgument() bool {
	current := n.Parent()
	for strings.HasPrefix(current.Kind(), "Expr_Cast_") {
		current = current.Parent()
	}

	return current.Kind() == "Arg"
}

func (n Node) ResultIsDeNulled() bool {
	if isDeNulled(n.Match) {
		return true
	}
	assigned := n.Parent()
	if assigned.Kind() != "Expr_Assign" || assigned.Child("var").Kind() != "Expr_Variable" {
		return false
	}
	for _, interaction := range Trace(assigned.Child("var")) {
		if interaction.Kind.DeNulls() {
			return true
		}
	}

	return false
}

func (n Node) HasNullNormalisedNullableCallback() bool {
	if !n.IsFunctionDeclaration() {
		return false
	}
	for _, param := range Params(n.Match) {
		variable := param.Child("var")
		if !isNullableCallbackWithNullDefault(param) || variable.Kind() != "Expr_Variable" || variable.Name() == "" {
			continue
		}
		if n.normalisesNullFor(variable.Name()) && n.isInvoked(variable.Name()) {
			return true
		}
	}

	return false
}

func isNullableCallbackWithNullDefault(param engine.Match) bool {
	if !isNullFetch(param.Child("default")) {
		return false
	}
	declared := param.Node().Declared
	if declared == nil {
		return false
	}
	var candidates []*contract.Type
	switch {
	case declared.Kind == "union":
		candidates = declared.Members
	case declared.Nullable:
		candidates = []*contract.Type{declared}
	}
	for _, candidate := range candidates {
		if name := strings.ToLower(ShortName(candidate.Name)); name == "callable" || name == "closure" {
			return true
		}
	}

	return false
}

func (n Node) normalisesNullFor(name string) bool {
	for _, variable := range withDescendants(n.Match) {
		if variable.Kind() != "Expr_Variable" || variable.Name() != name {
			continue
		}
		parent := variable.Parent()
		switch parent.Kind() {
		case "Expr_BinaryOp_Identical", "Expr_BinaryOp_NotIdentical", "Expr_BooleanNot", "Expr_BinaryOp_BooleanAnd", "Expr_BinaryOp_BooleanOr":
			return true
		case "Expr_BinaryOp_Coalesce":
			if variable.Node().Field == "left" {
				return true
			}
		}
		if isConditionOf(parent, variable) {
			return true
		}
	}

	return false
}

func isConditionOf(parent, subject engine.Match) bool {
	if parent.Kind() == "MatchArm" {
		return subject.Node().Field == "conds"
	}
	switch parent.Kind() {
	case "Stmt_If", "Stmt_ElseIf", "Stmt_While", "Stmt_Do", "Expr_Ternary", "Expr_Match":
		return subject.Node().Field == "cond"
	}

	return false
}

func (n Node) isInvoked(name string) bool {
	for _, call := range withDescendants(n.Match) {
		target := call.Child("name")
		if target.Kind() == "Expr_BinaryOp_Coalesce" {
			target = target.Child("left")
		}
		if call.Kind() == "Expr_FuncCall" && target.Kind() == "Expr_Variable" && target.Name() == name {
			return true
		}
	}

	return false
}

var renderings = Memoised(func(*engine.Codebase) *sync.Map { return &sync.Map{} })

func RendersBlank(codebase *engine.Codebase, class string) bool {
	rendered, ok := RenderingOf(codebase, class)

	return ok && rendered == ""
}

func RenderingOf(codebase *engine.Codebase, class string) (string, bool) {
	if class == "" {
		return "", false
	}
	type rendering struct {
		text string
		ok   bool
	}
	if known, ok := renderings.Of(codebase).Load(class); ok {
		return known.(rendering).text, known.(rendering).ok
	}
	found := rendering{}
	if declaration, declared := ProgramOf(codebase).Class(class); declared {
		for _, method := range Methods(declaration) {
			body := Node{Match: method}.ChildrenIn("stmts")
			if strings.EqualFold(method.Name(), "__toString") && len(body) == 1 && body[0].Kind() == "Stmt_Return" {
				found.text, found.ok = body[0].Child("expr").Text()
				found.ok = found.ok && body[0].Child("expr").Kind() == "Scalar_String"
			}
		}
	}
	renderings.Of(codebase).Store(class, found)

	return found.text, found.ok
}

func DecidesBlankness(codebase *engine.Codebase, class, method string) bool {
	if class == "" || method == "" {
		return false
	}
	key := class + "::" + method

	return decidesBlankness(codebase, class, method, map[string]bool{key: true})
}

func decidesBlankness(codebase *engine.Codebase, class, method string, seen map[string]bool) bool {
	declaration, declared := ProgramOf(codebase).Class(class)
	if !declared {
		return false
	}
	var found engine.Match
	for _, candidate := range Methods(declaration) {
		if strings.EqualFold(candidate.Name(), method) {
			found = candidate
		}
	}
	params := Params(found)
	if !found.Exists() || !slices.Contains(found.Node().Modifiers, "static") || len(params) == 0 {
		return false
	}
	subject := params[0].Child("var")
	if subject.Kind() != "Expr_Variable" || subject.Name() == "" {
		return false
	}
	body := Node{Match: found}
	if slices.Contains(body.VariablesTestedForBlankness(false), subject.Name()) {
		return true
	}
	for _, node := range withDescendants(found) {
		call := Node{Match: node}
		argument := call.Argument(0)
		if node.Kind() != "Expr_StaticCall" || argument.Kind() != "Expr_Variable" || argument.Name() != subject.Name() {
			continue
		}
		target := call.StaticCallClass()
		if target == "" {
			target = class
		}
		key := target + "::" + call.StaticCallMethod()
		if seen[key] {
			continue
		}
		deeper := map[string]bool{key: true}
		for earlier := range seen {
			deeper[earlier] = true
		}
		if decidesBlankness(codebase, target, call.StaticCallMethod(), deeper) {
			return true
		}
	}

	return false
}

func (n Node) IsFallbackOfAFirstMatch() bool {
	function := n.EnclosingFunctionLike()
	own := n.Parent().Location()

	for _, statement := range function.ChildrenIn("stmts") {
		for _, returned := range withDescendants(statement) {
			value := Node{Match: returned.Child("expr")}
			if returned.Kind() != "Stmt_Return" || !value.Exists() || value.IsNull() || returned.Location() == own {
				continue
			}

			if (Node{Match: returned}).EnclosingFunctionLike().Location() == function.Location() {
				return true
			}
		}
	}

	return false
}

func (p *Program) GuardsWhatTheTypeRulesOut(n Node) bool {
	declared, tested, guards := p.typeOfWhatItTestsForNull(n)
	function := Node{Match: enclosingFunction(n.Match)}
	if !guards || Written(declared).IsNullable() {
		return false
	}
	if _, known := p.Declarations()[strings.TrimLeft(Written(declared).Class(), `\`)]; !known {
		return false
	}

	if n.onlyFailsFast() {
		return false
	}

	return tested == "" || !function.everPutsNullIn(tested)
}

func (n Node) onlyFailsFast() bool {
	guard := n.Up()
	body := guard.ChildrenIn("stmts")
	if guard.Kind() != "Stmt_If" || len(body) != 1 {
		return false
	}
	statement := Node{Match: body[0]}

	return statement.Kind() == "Stmt_Expression" && statement.thrownAlone().Exists()
}

func (p *Program) typeOfWhatItTestsForNull(n Node) (*contract.Type, string, bool) {
	tested, compares := n.comparedWithNull()
	if !compares {
		return nil, "", false
	}
	if tested.Kind() == "Expr_Variable" {
		declared, types := Node{Match: enclosingFunction(n.Match)}.declaredTypeOf(tested.Name())

		return declared, tested.Name(), types
	}
	declared, types := p.fieldTypeOf(n, tested)

	return declared, "", types
}

func (p *Program) fieldTypeOf(n Node, read Node) (*contract.Type, bool) {
	owner := read.Child("var")
	class := n.Closest(engine.TypeDeclaration)
	if read.Kind() != "Expr_PropertyFetch" || owner.Kind() != "Expr_Variable" || owner.Name() != "this" || !class.Exists() {
		return nil, false
	}
	for _, field := range Fields(class) {
		if field.Name == read.Child("name").Name() {
			return field.Type, field.Type != nil
		}
	}

	return nil, false
}

func (n Node) comparedWithNull() (Node, bool) {
	if n.Kind() != "Expr_BinaryOp_Identical" && n.Kind() != "Expr_BinaryOp_NotIdentical" {
		return Node{}, false
	}
	left, right := n.Child("left"), n.Child("right")
	if IsNullConstant(right) {
		return Node{Match: left}, true
	}
	if IsNullConstant(left) {
		return Node{Match: right}, true
	}

	return Node{}, false
}

func (n Node) declaredTypeOf(name string) (*contract.Type, bool) {
	for _, parameter := range Params(n.Match) {
		if parameter.Child("var").Name() != name {
			continue
		}
		declared := parameter.Node().Declared
		defaulted := parameter.Child("default")

		return declared, declared != nil && !(defaulted.Exists() && IsNullConstant(defaulted))
	}

	return nil, false
}

func (n Node) everPutsNullIn(name string) bool {
	return slices.ContainsFunc(descendantsOf(n.Match), func(node engine.Match) bool {
		target := node.Child("var")

		return node.Kind() == "Expr_Assign" && target.Kind() == "Expr_Variable" && target.Name() == name && IsNullConstant(node.Child("expr"))
	})
}
