package php

import (
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// IsScalarLiteral says whether the node is a string, integer or float literal.
func (n Node) IsScalarLiteral() bool {
	return n.Kind() == "Scalar_String" || n.Kind() == "Scalar_Int" || n.Kind() == "Scalar_Float"
}

// IsDocumentLiteral says whether the node is a string holding a document: several lines, or written as a heredoc
// or nowdoc.
func (n Node) IsDocumentLiteral() bool {
	text, _ := n.Text()
	if n.Kind() != "Scalar_String" {
		return false
	}
	span, err := n.Span()

	return strings.Contains(text, "\n") || err == nil && strings.HasPrefix(span.Text(), "<<<")
}

// IsScalarConstClass says whether the node is a class of nothing but two or more constants of scalar, non-document
// values.
func (n Node) IsScalarConstClass() bool {
	if n.Kind() != "Stmt_Class" {
		return false
	}
	constants := 0
	for _, member := range n.In("stmts") {
		if member.Kind() != "Stmt_ClassConst" {
			return false
		}
		for _, constant := range (Node{Match: member}).In("consts") {
			value := Node{Match: constant.Child("value")}
			if !value.IsScalarLiteral() || value.IsDocumentLiteral() {
				return false
			}
			constants++
		}
	}

	return constants >= 2
}

// ExtendsAClass says whether the node is a class that names a parent.
func (n Node) ExtendsAClass() bool {
	return n.Kind() == "Stmt_Class" && isName(n.Child("extends"))
}

// OrChainComparedClass is the class whose constants two or more comparisons of an outermost || chain compare
// against; empty when there is none.
func (n Node) OrChainComparedClass() string {
	if n.Kind() != "Expr_BinaryOp_BooleanOr" || n.Parent().Kind() == "Expr_BinaryOp_BooleanOr" {
		return ""
	}
	counts := map[string]int{}
	var order []string
	for _, operand := range flattenOr(n.Match) {
		if class := comparedConstClass(operand); class != "" {
			if counts[class] == 0 {
				order = append(order, class)
			}
			counts[class]++
		}
	}
	for _, class := range order {
		if counts[class] >= 2 {
			return class
		}
	}

	return ""
}

func flattenOr(node engine.Match) []engine.Match {
	var operands []engine.Match
	for _, side := range []engine.Match{node.Child("left"), node.Child("right")} {
		if side.Kind() == "Expr_BinaryOp_BooleanOr" {
			operands = append(operands, flattenOr(side)...)
		} else {
			operands = append(operands, side)
		}
	}

	return operands
}

func comparedConstClass(operand engine.Match) string {
	if operand.Kind() != "Expr_BinaryOp_Identical" && operand.Kind() != "Expr_BinaryOp_NotIdentical" {
		return ""
	}
	for _, side := range []engine.Match{operand.Child("left"), operand.Child("right")} {
		if side.Kind() == "Expr_ClassConstFetch" && isName(side.Child("class")) {
			return side.Child("class").Name()
		}
	}

	return ""
}

// matchSubject is what a match or a switch dispatches on; no node for any other.
func (n Node) matchSubject() Node {
	if n.Kind() != "Expr_Match" && n.Kind() != "Stmt_Switch" {
		return Node{}
	}

	return Node{Match: n.Child("cond")}
}

// IsMatchOnEnumValue says whether the node is a match or a switch on some ->value.
func (n Node) IsMatchOnEnumValue() bool {
	subject := n.matchSubject()

	return isPropertyRead(subject.Match) && subject.Child("name").Kind() == "Identifier" && subject.Child("name").Name() == "value"
}

// IsInEnum says whether the node sits in an enum.
func (n Node) IsInEnum() bool {
	return n.EnclosingClassLike().Kind() == "Stmt_Enum"
}

// scalarLiteral is a string's value or an integer's digits, when the node is one.
func scalarLiteral(node engine.Match) (string, bool) {
	switch node.Kind() {
	case "Scalar_String":
		return node.Text()
	case "Scalar_Int":
		text, _ := node.Text()

		return text, true
	}

	return "", false
}

// ArgumentArrayLiterals is every string or integer the array literal passed at the position holds.
func (n Node) ArgumentArrayLiterals(position int) []string {
	array := n.Argument(position)
	if array.Kind() != "Expr_Array" {
		return nil
	}
	var literals []string
	for _, item := range array.In("items") {
		if literal, ok := scalarLiteral(item.Child("value")); item.Kind() == "ArrayItem" && ok {
			literals = append(literals, literal)
		}
	}

	return literals
}

// ArmConditionLiterals is every string or integer a match's arms or a switch's cases test for.
func (n Node) ArmConditionLiterals() []string {
	var literals []string
	switch n.Kind() {
	case "Expr_Match":
		for _, arm := range n.In("arms") {
			for _, condition := range (Node{Match: arm}).In("conds") {
				if literal, ok := scalarLiteral(condition); ok {
					literals = append(literals, literal)
				}
			}
		}
	case "Stmt_Switch":
		for _, branch := range n.In("cases") {
			if literal, ok := scalarLiteral(branch.Child("cond")); ok {
				literals = append(literals, literal)
			}
		}
	}

	return literals
}

// defaultArm is a match's default arm; no node when it has none.
func (n Node) defaultArm() Node {
	for _, arm := range n.In("arms") {
		if len((Node{Match: arm}).In("conds")) == 0 {
			return Node{Match: arm}
		}
	}

	return Node{}
}

// IsMatchWithAbsenceDefault says whether the node is a match, not on a boolean literal, whose default hands back an
// absence value.
func (n Node) IsMatchWithAbsenceDefault() bool {
	subject := n.Child("cond")
	onBoolean := subject.Kind() == "Expr_ConstFetch" &&
		(strings.EqualFold(subject.Child("name").Name(), "true") || strings.EqualFold(subject.Child("name").Name(), "false"))
	if n.Kind() != "Expr_Match" || onBoolean {
		return false
	}
	arm := n.defaultArm()

	return arm.Exists() && Node{Match: arm.Child("body")}.IsAbsenceValue()
}

// MatchHandledArmsAdmitNull says whether a match defaulting to null, on a subject that is no enum, has a handled
// arm calling a method declared to return null already.
func (n Node) MatchHandledArmsAdmitNull() bool {
	arm := n.defaultArm()
	if n.Kind() != "Expr_Match" || !arm.Exists() || !(Node{Match: arm.Child("body")}).IsNull() || n.matchSubjectIsEnum() {
		return false
	}
	for _, handled := range n.In("arms") {
		if len((Node{Match: handled}).In("conds")) > 0 && n.declaredReturnAdmitsNull(handled.Child("body")) {
			return true
		}
	}

	return false
}

func (n Node) matchSubjectIsEnum() bool {
	if n.Kind() != "Expr_Match" || !n.EnclosingFunctionLike().Exists() {
		return false
	}

	return ProgramOf(n.Codebase()).IsEnum(TypesOf(n.Codebase()).TypeOf(n.Child("cond")))
}

func (n Node) declaredReturnAdmitsNull(expression engine.Match) bool {
	types := TypesOf(n.Codebase())
	if isMethodSend(expression) {
		name := expression.Child("name")

		return name.Kind() == "Identifier" && methodReturnsNullable(n.Codebase(), ReceiverTypeOf(expression), name.Name())
	}
	if expression.Kind() == "Expr_StaticCall" {
		if owner, method := types.Callee(expression); owner != "" && method != "" {
			return methodReturnsNullable(n.Codebase(), owner, method)
		}
	}

	return false
}

// methodReturnsNullable says whether the class that declares the method, the class itself or an ancestor, declares
// it returning a nullable.
func methodReturnsNullable(codebase *engine.Codebase, class, method string) bool {
	owner := TypesOf(codebase).DeclaringClassOfMethod(class, method)
	declaration, declared := ProgramOf(codebase).Class(owner)
	if !declared {
		return false
	}
	for _, candidate := range Methods(declaration) {
		if strings.EqualFold(candidate.Name(), method) {
			return Written(candidate.Node().Returns).IsNullable()
		}
	}

	return false
}

// IsThisCall says whether the node sends a method to $this.
func (n Node) IsThisCall() bool {
	receiver := n.Child("var")

	return n.Kind() == "Expr_MethodCall" && receiver.Kind() == "Expr_Variable" && receiver.Name() == "this"
}

// Enums is every backed enum of two or more literal cases a codebase declares, by name.
type Enums map[string][]string

var enums = Memoised(func(codebase *engine.Codebase) Enums {
	indexed := Enums{}
	for _, declaration := range codebase.WhereKind("Stmt_Enum").Get() {
		name := declaration.Node().Symbol
		if name == "" {
			name = declaration.Name()
		}
		var values []string
		for _, member := range (Node{Match: declaration}).In("stmts") {
			if literal, ok := scalarLiteral(member.Child("expr")); member.Kind() == "Stmt_EnumCase" && ok {
				values = append(values, literal)
			}
		}
		if name != "" && len(values) >= 2 {
			indexed[name] = values
		}
	}

	return indexed
})

// EnumsOf is the codebase's enums.
func EnumsOf(codebase *engine.Codebase) Enums {
	return enums.Of(codebase)
}

// IsIndexed says whether the class is one of the enums.
func (e Enums) IsIndexed(class string) bool {
	_, ok := e[strings.TrimLeft(class, `\`)]

	return class != "" && ok
}

// MirroredBy says whether two or more distinct non-numeric literals are all the values of one enum.
func (e Enums) MirroredBy(literals []string) bool {
	var distinct []string
	for _, literal := range literals {
		if !isNumeric(literal) && !slices.Contains(distinct, literal) {
			distinct = append(distinct, literal)
		}
	}
	if len(distinct) < 2 {
		return false
	}
	for _, cases := range e {
		if !slices.ContainsFunc(distinct, func(literal string) bool { return !slices.Contains(cases, literal) }) {
			return true
		}
	}

	return false
}

// numeric is a number as PHP's is_numeric reads one: signed, decimal or exponent, white space around it allowed.
var numeric = regexp.MustCompile(`^[ \t\n\r\v\f]*[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?[ \t\n\r\v\f]*$`)

func isNumeric(text string) bool {
	return numeric.MatchString(text)
}
