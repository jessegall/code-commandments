package php

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func (n Node) IsMiddleMan() bool {
	if n.Kind() != "Stmt_Class" || n.Child("extends").Exists() || len(n.ChildrenIn("implements")) > 0 || n.HasModifier("abstract") {
		return false
	}
	delegate, forwarded := "", 0
	for _, method := range Methods(n.Match) {
		if (Node{Match: method}).IsConstructorDeclaration() {
			continue
		}
		field, ok := forwardedField(method)
		if !ok || !IsPublic(method.Node()) || delegate != "" && field != delegate {
			return false
		}
		delegate = field
		forwarded++
	}

	return forwarded >= 2
}

func forwardedField(method engine.Match) (string, bool) {
	statements := method.ChildrenIn("stmts")
	if len(statements) != 1 || !slices.Contains([]string{"Stmt_Return", "Stmt_Expression"}, statements[0].Kind()) {
		return "", false
	}
	call := statements[0].Child("expr")
	field := ownProperty(call.Child("var"))
	if !isMethodSend(call) || field == "" || call.Child("name").Kind() != "Identifier" {
		return "", false
	}
	params, arguments := Params(method), Arguments(call)
	if len(params) != len(arguments) {
		return "", false
	}
	for position, argument := range arguments {
		value := argument.Child("value")
		if value.Kind() != "Expr_Variable" || value.Name() != params[position].Child("var").Name() || argument.Child("name").Exists() {
			return "", false
		}
	}

	return field, true
}

func (n Node) RefusesWhatItInherits() bool {
	if n.Kind() != "Stmt_ClassMethod" || !n.NameIsInherited() {
		return false
	}
	thrown, ok := onlyThrows(n.Match)

	return ok && !contractOutcomes.Of(n.Codebase())[strings.ToLower(n.Name())][thrown]
}

func onlyThrows(method engine.Match) (string, bool) {
	statements := method.ChildrenIn("stmts")
	if len(statements) != 1 {
		return "", false
	}
	switch statement := statements[0]; {
	case statement.Kind() == "Stmt_Throw":
		return thrownClass(statement), true
	case statement.Kind() == "Stmt_Expression" && statement.Child("expr").Kind() == "Expr_Throw":
		return thrownClass(statement.Child("expr")), true
	}

	return "", false
}

func thrownClass(throw engine.Match) string {
	return strings.ToLower(ShortName(throw.Child("expr").Child("class").Name()))
}

var contractOutcomes = engine.Memoised(func(codebase *engine.Codebase) map[string]map[string]bool {
	outcomes := map[string]map[string]bool{}
	for _, method := range codebase.WhereKind("Stmt_ClassMethod").Get() {
		if _, refuses := onlyThrows(method); refuses {
			continue
		}
		name := strings.ToLower(method.Name())
		if outcomes[name] == nil {
			outcomes[name] = map[string]bool{}
		}
		for _, node := range descendantsOf(method) {
			if node.Kind() == "Expr_Throw" || node.Kind() == "Stmt_Throw" {
				outcomes[name][thrownClass(node)] = true
			}
		}
		if doc, ok := DocComment(method); ok {
			for _, documented := range DocumentedThrows(doc.Text) {
				outcomes[name][strings.ToLower(ShortName(documented))] = true
			}
		}
	}

	return outcomes
})

func (n Node) ReturnsFalseForNothing() bool {
	declared := n.Node().Written
	if !n.IsFunctionLike() || declared == nil || declared.Kind != "union" {
		return false
	}

	return slices.ContainsFunc(declared.Members, func(member *contract.Type) bool {
		return member.Kind == "keyword" && strings.EqualFold(member.Name, "false")
	})
}

func (n Node) EndsEveryBranchAlike() bool {
	if n.Kind() != "Stmt_If" || !n.Child("else").Exists() {
		return false
	}
	branches := [][]engine.Match{n.ChildrenIn("stmts")}
	for _, elseif := range n.ChildrenIn("elseifs") {
		branches = append(branches, (Node{Match: elseif}).ChildrenIn("stmts"))
	}
	branches = append(branches, (Node{Match: n.Child("else")}).ChildrenIn("stmts"))
	if slices.ContainsFunc(branches, func(branch []engine.Match) bool { return len(branch) == 0 }) {
		return false
	}
	last := branches[0][len(branches[0])-1]

	return !slices.ContainsFunc(branches[1:], func(branch []engine.Match) bool { return !branch[len(branch)-1].SameSyntax(last) })
}

func (n Node) IsUnusedPrivateMethod() bool {
	class := Node{Match: n.Closest(engine.TypeDeclaration)}
	if n.Kind() != "Stmt_ClassMethod" || !n.HasModifier("private") || n.IsMagicMethod() || !class.ownsItsPrivates() {
		return false
	}

	return !slices.ContainsFunc(descendantsOf(class.Match), func(node engine.Match) bool {
		return callsTheMethod(node, n.Name()) || namesInAString(node, n.Name())
	})
}

func (n Node) IsUnreadPrivateProperty() bool {
	class := Node{Match: n.Closest(engine.TypeDeclaration)}
	field, ok := AsField(n.Match)
	if n.Kind() != "Stmt_Property" || !ok || !n.HasModifier("private") || len(AttributeNames(n.Match)) > 0 || !class.ownsItsPrivates() {
		return false
	}

	return !slices.ContainsFunc(descendantsOf(class.Match), func(node engine.Match) bool {
		return readsTheProperty(node, field.Name, n.HasModifier("static")) || namesInAString(node, field.Name)
	})
}

func (n Node) ownsItsPrivates() bool {
	return n.Kind() == "Stmt_Class" && !n.ReadsItsFieldsWithoutNamingThem() && !slices.ContainsFunc(descendantsOf(n.Match), sendsByAComputedName)
}

func sendsByAComputedName(node engine.Match) bool {
	switch node.Kind() {
	case "Expr_MethodCall", "Expr_NullsafeMethodCall", "Expr_StaticCall", "Expr_PropertyFetch", "Expr_StaticPropertyFetch":
		return node.Child("name").Kind() != "Identifier" && node.Child("name").Kind() != "VarLikeIdentifier"
	}

	return false
}

func callsTheMethod(node engine.Match, method string) bool {
	switch node.Kind() {
	case "Expr_MethodCall", "Expr_NullsafeMethodCall", "Expr_StaticCall":
		return strings.EqualFold(node.Child("name").Name(), method)
	}

	return false
}

func readsTheProperty(node engine.Match, property string, static bool) bool {
	if isAssignmentTarget(node) {
		return false
	}
	if static {
		return node.Kind() == "Expr_StaticPropertyFetch" && node.Child("name").Name() == property
	}

	return (Node{Match: node}).isSelfFieldNamed(property)
}

func namesInAString(node engine.Match, name string) bool {
	text, ok := node.Text()

	return node.Kind() == "Scalar_String" && ok && strings.EqualFold(text, name)
}

func (n Node) IsUnusedPrivateMember() bool {
	return n.IsUnusedPrivateMethod() || n.IsUnreadPrivateProperty()
}
