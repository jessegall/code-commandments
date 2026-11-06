package php

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func (n Node) HasReturnType() bool {
	return n.Kind() == "Expr_ArrowFunction" && n.Node().Returns != nil
}

func (n Node) ReturnTypeRestatesItsExpression() bool {
	if !n.HasReturnType() {
		return false
	}
	declared := Written(n.Node().Returns).Render()
	if lowered := strings.ToLower(declared); lowered == "self" || lowered == "static" || lowered == "parent" {
		return false
	}
	yielded := ExpressionType(n.Codebase(), n.Child("expr"), EnclosingClassName(n.Match))

	return yielded != "" && yielded == declared
}

func ExpressionType(codebase *engine.Codebase, expr engine.Match, self string) string {
	switch expr.Kind() {
	case "Scalar_String":
		return "string"
	case "Scalar_Int":
		return "int"
	case "Scalar_Float":
		return "float"
	case "Expr_ConstFetch":
		if name := strings.ToLower(expr.Child("name").Name()); name == "true" || name == "false" {
			return "bool"
		}
	case "Expr_Array":
		return "array"
	case "Expr_New":
		if class := expr.Child("class"); isName(class) {
			return strings.TrimLeft(class.Name(), `\`)
		}
	case "Expr_PropertyFetch":
		if field := memberOnThis(expr); field != "" {
			return declaredPropertyType(codebase, self, field)
		}
	case "Expr_MethodCall":
		if method := memberOnThis(expr); method != "" {
			return declaredReturn(codebase, self, method, self)
		}
	case "Expr_StaticCall":
		class, name := expr.Child("class"), expr.Child("name")
		if !isName(class) || name.Kind() != "Identifier" {
			return ""
		}
		called := strings.TrimLeft(class.Name(), `\`)
		if lowered := strings.ToLower(called); lowered == "self" || lowered == "static" {
			called = self
		}

		return declaredReturn(codebase, called, name.Name(), called)
	}

	return ""
}

func memberOnThis(expr engine.Match) string {
	receiver, name := expr.Child("var"), expr.Child("name")
	if receiver.Kind() != "Expr_Variable" || receiver.Name() != "this" || name.Kind() != "Identifier" {
		return ""
	}

	return name.Name()
}

func declaredPropertyType(codebase *engine.Codebase, class, field string) string {
	declaration, declared := ProgramOf(codebase).Declaration(class)
	if !declared {
		return ""
	}
	for _, member := range (Node{Match: declaration}).ChildrenIn("stmts") {
		if member.Kind() != "Stmt_Property" {
			continue
		}
		for _, item := range (Node{Match: member}).ChildrenIn("props") {
			if item.Name() == field && member.Node().Declared != nil {
				return Written(member.Node().Declared).Render()
			}
		}
	}
	for _, method := range Methods(declaration) {
		if !strings.EqualFold(method.Name(), "__construct") {
			continue
		}
		for _, param := range Params(method) {
			variable := param.Child("var")
			if len(param.Node().Modifiers) > 0 && variable.Kind() == "Expr_Variable" && variable.Name() == field && param.Node().Declared != nil {
				return Written(param.Node().Declared).Render()
			}
		}
	}

	return ""
}

func declaredReturn(codebase *engine.Codebase, class, method, self string) string {
	declaration, declared := ProgramOf(codebase).Declaration(class)
	if !declared {
		return ""
	}
	for _, candidate := range Methods(declaration) {
		if !strings.EqualFold(candidate.Name(), method) || candidate.Node().Returns == nil {
			continue
		}
		rendered := Written(candidate.Node().Returns).Render()
		if lowered := strings.ToLower(rendered); lowered == "self" || lowered == "static" {
			return self
		}

		return rendered
	}

	return ""
}

func (n Node) HasOwnStateSaveAndRestore() bool {
	if !n.IsFunctionDeclaration() || n.hasCallableParam() {
		return false
	}
	savedInto := map[string]string{}
	restoredFrom := map[string][]string{}
	for _, statement := range n.ChildrenIn("stmts") {
		for _, assign := range withDescendants(statement) {
			if assign.Kind() != "Expr_Assign" {
				continue
			}
			target, value := assign.Child("var"), assign.Child("expr")
			if source := selfPropertyOf(value); source != "" && target.Kind() == "Expr_Variable" && target.Name() != "" {
				savedInto[source] = target.Name()
			}
			if property := selfPropertyOf(target); property != "" && value.Kind() == "Expr_Variable" && value.Name() != "" {
				restoredFrom[property] = append(restoredFrom[property], value.Name())
			}
		}
	}
	for property, local := range savedInto {
		if slices.Contains(restoredFrom[property], local) {
			return true
		}
	}

	return false
}

func (n Node) hasCallableParam() bool {
	return slices.ContainsFunc(Params(n.Match), func(param engine.Match) bool {
		return slices.ContainsFunc(typeNames(param.Node().Declared), func(name string) bool { return name == "callable" || name == "Closure" })
	})
}

func typeNames(written *contract.Type) []string {
	if written == nil {
		return nil
	}
	if len(written.Members) > 0 {
		var names []string
		for _, member := range written.Members {
			names = append(names, typeNames(member)...)
		}

		return names
	}
	if written.Name == "" {
		return nil
	}

	return []string{ShortName(written.Name)}
}

func (n Node) IsAbstractHook() bool {
	return n.Kind() == "PropertyHook" && !n.Child("body").Exists()
}

func (n Node) HookedPropertyHasSetter() bool {
	return slices.ContainsFunc(n.Up().ChildrenIn("hooks"), func(hook engine.Match) bool { return hook.Name() == "set" })
}

func (n Node) ReferencesThis() bool {
	return slices.ContainsFunc(withDescendants(n.Match), func(node engine.Match) bool {
		if node.Kind() == "Expr_Variable" && node.Name() == "this" {
			return true
		}
		class := strings.ToLower(node.Child("class").Name())

		return (node.Kind() == "Expr_StaticCall" || node.Kind() == "Expr_StaticPropertyFetch") && isName(node.Child("class")) &&
			(class == "self" || class == "static" || class == "parent")
	})
}

func (n Node) IsLateStaticBound() bool {
	return slices.ContainsFunc(withDescendants(n.Match), func(node engine.Match) bool {
		switch node.Kind() {
		case "Expr_ClassConstFetch", "Expr_StaticCall", "Expr_StaticPropertyFetch", "Expr_New":
			return isName(node.Child("class")) && strings.EqualFold(node.Child("class").Name(), "static")
		}

		return false
	})
}

func (n Node) MasksOwnState() bool {
	if !n.IsCoalesce() || !isNonNullLiteral(n.Child("right")) {
		return false
	}
	property := nullsafeOwnProperty(n.Child("left"))

	return property != "" && transientNullables.Of(n.Codebase())[EnclosingClassName(n.Match)][property]
}

func nullsafeOwnProperty(expr engine.Match) string {
	for {
		switch expr.Kind() {
		case "Expr_MethodCall", "Expr_PropertyFetch":
		case "Expr_NullsafeMethodCall", "Expr_NullsafePropertyFetch":
			if property := ownProperty(expr.Child("var")); property != "" {
				return property
			}
		default:
			return ""
		}
		expr = expr.Child("var")
	}
}

func ownProperty(expr engine.Match) string {
	name := expr.Child("name")
	if !isPropertyRead(expr) || expr.Child("var").Kind() != "Expr_Variable" || expr.Child("var").Name() != "this" || name.Kind() != "Identifier" {
		return ""
	}

	return name.Name()
}

func isNonNullLiteral(expr engine.Match) bool {
	if strings.HasPrefix(expr.Kind(), "Scalar_") {
		return true
	}
	name := strings.ToLower(expr.Child("name").Name())

	return expr.Kind() == "Expr_ConstFetch" && (name == "true" || name == "false")
}

func privateNullablesOf(class engine.Match) map[string]bool {
	nullable := map[string]bool{}
	for _, member := range (Node{Match: class}).ChildrenIn("stmts") {
		if member.Kind() != "Stmt_Property" {
			continue
		}
		if !slices.Contains(member.Node().Modifiers, "private") || !Written(member.Node().Declared).IsNullable() {
			continue
		}
		for _, item := range (Node{Match: member}).ChildrenIn("props") {
			nullable[item.Name()] = true
		}
	}

	return nullable
}

func assignedIn(method engine.Match, named map[string]bool) map[string]bool {
	assigned := map[string]bool{}
	for _, statement := range (Node{Match: method}).ChildrenIn("stmts") {
		for _, held := range withDescendants(statement) {
			if held.Kind() != "Expr_Assign" {
				continue
			}
			if property := ownProperty(held.Child("var")); named[property] {
				assigned[property] = true
			}
		}
	}

	return assigned
}

var transientNullables = Memoised(func(codebase *engine.Codebase) map[string]map[string]bool {
	byClass := map[string]map[string]bool{}
	for _, class := range codebase.WhereKind("Stmt_Class").Get() {
		name := class.Node().Symbol
		if name == "" {
			continue
		}
		nullable := privateNullablesOf(class)
		transient := map[string]bool{}
		for _, method := range Methods(class) {
			if strings.EqualFold(method.Name(), "__construct") {
				continue
			}
			for property := range assignedIn(method, nullable) {
				transient[property] = true
			}
		}
		byClass[name] = transient
	}

	return byClass
})

func ParamForArgument(params []engine.Match, argument engine.Match, position int) engine.Match {
	name := argument.Child("name")
	if !name.Exists() {
		if position < len(params) {
			return params[position]
		}

		return engine.Match{}
	}
	for _, param := range params {
		if param.Child("var").Name() == name.Name() {
			return param
		}
	}

	return engine.Match{}
}

var wireTypeAttributes = []string{"LiteralTypeScriptType", "TypeScriptType"}

func (n Node) DeclaresNullableWireType() bool {
	carrier := n
	for carrier.Exists() && carrier.Kind() != "Param" && carrier.Kind() != "Stmt_Property" {
		carrier = carrier.Up()
	}
	for _, group := range carrier.Children() {
		if group.Kind() != "AttributeGroup" {
			continue
		}
		for _, attribute := range group.Children() {
			if attribute.Kind() != "Attribute" || !slices.Contains(wireTypeAttributes, ShortName(attribute.Child("name").Name())) {
				continue
			}
			arguments := Arguments(attribute)
			if len(arguments) == 0 {
				continue
			}
			if written, ok := arguments[0].Child("value").Text(); arguments[0].Child("value").Kind() == "Scalar_String" && ok && typeStringIsNullable(written) {
				return true
			}
		}
	}

	return false
}

func typeStringIsNullable(written string) bool {
	if strings.HasPrefix(strings.TrimSpace(written), "?") {
		return true
	}

	return slices.ContainsFunc(strings.Split(written, "|"), func(part string) bool { return strings.ToLower(strings.TrimSpace(part)) == "null" })
}

func (n Node) DecidesOnBoolsAlone() bool {
	if !n.IsFunctionDeclaration() || n.IsConstructorDeclaration() {
		return false
	}
	named := 0
	for _, param := range Params(n.Match) {
		variable := param.Child("var")
		if variable.Kind() != "Expr_Variable" || variable.Name() == "" {
			continue
		}
		named++
		if !strings.EqualFold(Written(param.Node().Declared).SimpleName(), "bool") || !n.readsAsCondition(variable.Name()) {
			return false
		}
	}

	return named > 0
}

func (n Node) readsAsCondition(name string) bool {
	for _, variable := range withDescendants(n.Match) {
		if variable.Kind() != "Expr_Variable" || variable.Name() != name {
			continue
		}
		parent := variable.Parent()
		switch parent.Kind() {
		case "Expr_BooleanNot", "Expr_BinaryOp_BooleanAnd", "Expr_BinaryOp_BooleanOr":
			return true
		}
		if isConditionOf(parent, variable) {
			return true
		}
	}

	return false
}

func (n Node) ArgumentSubjectType() string {
	arguments := Arguments(n.Match)
	if len(arguments) == 0 || !n.EnclosingFunctionLike().Exists() {
		return ""
	}
	types := TypesOf(n.Codebase())
	subjects := map[string]bool{}
	for _, argument := range arguments {
		asked := 0
		for _, node := range withDescendants(argument.Child("value")) {
			if !isMethodSend(node) && node.Kind() != "Expr_PropertyFetch" {
				continue
			}
			asked++
			subjects[types.TypeOf(node.Child("var"))] = true
		}
		if asked == 0 {
			return ""
		}
	}
	if len(subjects) != 1 || subjects[""] {
		return ""
	}
	for subject := range subjects {
		return subject
	}

	return ""
}
