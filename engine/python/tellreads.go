package python

import (
	"crypto/sha1"
	"encoding/hex"
	"slices"
	"strings"
)

// IsTypeTest says whether the expression is an `isinstance(x, C)` naming the class it tests for.
func (n Node) IsTypeTest() bool {
	arguments := n.Arguments()

	return n.IsInstanceCheck() && len(arguments) == 2 && arguments[1].DottedName() != ""
}

// IsInstanceCheck says whether the expression calls isinstance.
func (n Node) IsInstanceCheck() bool {
	return n.IsCall() && n.Callee().DottedName() == "isinstance"
}

// typeSwitchArms is every if of the call's def that tests the same subject's type as a switch arm: with an else, a
// body that bails out, or as an elif.
func (n Node) typeSwitchArms() []Node {
	if !n.IsTypeTest() {
		return nil
	}
	subject := n.Arguments()[0].ExpressionHash()
	var arms []Node
	for _, test := range n.EnclosingFunction().ExpressionsIn() {
		arm := test.Parent()
		if test.IsTypeTest() && test.Arguments()[0].ExpressionHash() == subject && arm.Kind() == "If" && arm.Child("test") == test && arm.isSwitchArm() {
			arms = append(arms, arm)
		}
	}

	return arms
}

// isSwitchArm says whether the if is an arm of a switch: it has an else, its body bails out, or it is an elif.
func (n Node) isSwitchArm() bool {
	body := n.ChildrenIn("body")

	return len(n.ChildrenIn("orelse")) > 0 || (len(body) > 0 && body[len(body)-1].IsBailOut()) || n.IsElif()
}

// IsTypeSwitchHead says whether the call is the first of two or more isinstance arms over one subject testing two
// or more classes: behaviour picked by type, which each type should answer itself.
func (n Node) IsTypeSwitchHead() bool {
	arms := n.typeSwitchArms()
	var classes []string
	for _, class := range n.TypeSwitchClasses() {
		if !slices.Contains(classes, class) {
			classes = append(classes, class)
		}
	}

	return len(arms) >= 2 && arms[0].Child("test") == n && len(classes) >= 2
}

// TypeSwitchClasses is the class each arm of the switch the call heads tests for.
func (n Node) TypeSwitchClasses() []string {
	var classes []string
	for _, arm := range n.typeSwitchArms() {
		classes = append(classes, arm.Child("test").Arguments()[1].DottedName())
	}

	return classes
}

// IsInDunder says whether the expression sits in a dunder method: a protocol handed a value of any type.
func (n Node) IsInDunder() bool {
	return n.EnclosingFunction().IsDunder()
}

// TypeSwitchTranslatesEveryArm says whether every arm of the switch the call heads only returns a call handed the
// subject: a translation table from types to calls, not behaviour to move.
func (n Node) TypeSwitchTranslatesEveryArm() bool {
	subject := n.Arguments()[0].ExpressionHash()

	return !slices.ContainsFunc(n.typeSwitchArms(), func(arm Node) bool {
		statements := arm.StatementsBeyondText()
		if len(statements) != 1 {
			return true
		}
		value := statements[0].ReturnedValue()

		return !value.IsCall() || !slices.ContainsFunc(value.Arguments(), func(argument Node) bool { return argument.ExpressionHash() == subject })
	})
}

// IsAnd says whether the expression is an `and`.
func (n Node) IsAnd() bool {
	return n.Kind() == "BoolOp" && n.Node().Operator == "and"
}

// isOutermostAnd says whether the expression is an `and` no other `and` holds.
func (n Node) isOutermostAnd() bool {
	return n.IsAnd() && !n.wrapper().IsAnd()
}

// Conjuncts is the operands of an `and`, nested ones spread.
func (n Node) Conjuncts() []Node {
	if !n.IsAnd() {
		return []Node{n}
	}
	var conjuncts []Node
	for _, value := range n.ChildrenIn("values") {
		conjuncts = append(conjuncts, value.Conjuncts()...)
	}

	return conjuncts
}

// resolvedConjuncts is the operands of an `and`, a local assigned once read as the value assigned to it.
func (n Node) resolvedConjuncts() []Node {
	aliases := map[string]Node{}
	for _, sole := range n.EnclosingFunction().SoleAssignments() {
		aliases[sole.Local] = sole.Value
	}
	conjuncts := n.Conjuncts()
	for at, conjunct := range conjuncts {
		if alias, ok := aliases[conjunct.Name()]; conjunct.Kind() == "Name" && ok {
			conjuncts[at] = alias
		}
	}

	return conjuncts
}

// IsSubstantiveGuard says whether the expression is an outermost `and` that reaches two or more attributes across
// its operands and is more than a run of isinstance checks: a question about an object asked from outside it.
func (n Node) IsSubstantiveGuard() bool {
	if !n.isOutermostAnd() {
		return false
	}
	conjuncts := n.resolvedConjuncts()
	reaches := 0
	for _, conjunct := range conjuncts {
		reaches += len(slices.DeleteFunc(conjunct.evaluatedIn(), func(part Node) bool { return part.Kind() != "Attribute" }))
	}

	return slices.ContainsFunc(conjuncts, func(conjunct Node) bool { return !conjunct.IsInstanceCheck() }) && reaches >= 2
}

// IsTypeNarrowingGuard says whether the expression is an outermost `and` of two or more isinstance checks.
func (n Node) IsTypeNarrowingGuard() bool {
	checks := 0
	for _, conjunct := range n.Conjuncts() {
		if conjunct.IsInstanceCheck() {
			checks++
		}
	}

	return n.isOutermostAnd() && checks >= 2
}

// GuardFingerprint is the guard's operands, as they read, in no particular order: two guards asking the same
// questions share it.
func (n Node) GuardFingerprint() string {
	var hashes []string
	for _, conjunct := range n.resolvedConjuncts() {
		hashes = append(hashes, conjunct.ExpressionHash())
	}
	slices.Sort(hashes)
	sum := sha1.Sum([]byte(strings.Join(hashes, "|")))

	return hex.EncodeToString(sum[:])
}

// IsAssignedValue says whether the expression is the value an assignment stores.
func (n Node) IsAssignedValue() bool {
	parent := n.Parent()

	return (parent.Kind() == "Assign" || parent.Kind() == "AnnAssign") && parent.Child("value") == n
}

// IsConstruction says whether the expression builds something: a method call through an attribute, or a dict, list
// or set display.
func (n Node) IsConstruction() bool {
	return (n.IsCall() && n.Callee().Kind() == "Attribute") || n.Kind() == "Dict" || n.Kind() == "List" || n.Kind() == "Set"
}

// ConstructionShape is how the expression builds what it builds, its names kept and its arguments aside:
// `Money.of()`, `dict`.
func (n Node) ConstructionShape() string {
	switch n.Kind() {
	case "Call":
		return n.Callee().ConstructionShape() + "()"
	case "Attribute":
		return n.Child("value").ConstructionShape() + "." + n.Name()
	}

	return strings.ToLower(n.Kind())
}
