package python

import (
	"crypto/sha1"
	"encoding/hex"
	"slices"
	"strings"
)

func (n Node) IsTypeTest() bool {
	arguments := n.Arguments()

	return n.IsInstanceCheck() && len(arguments) == 2 && arguments[1].DottedName() != ""
}

func (n Node) IsInstanceCheck() bool {
	return n.IsCall() && n.Callee().DottedName() == "isinstance"
}

func (n Node) typeSwitchArms() []Node {
	if !n.IsTypeTest() {
		return nil
	}
	function := n.EnclosingFunction()
	if !function.Exists() {
		return nil
	}
	subject := n.Arguments()[0].ExpressionHash()
	var arms []Node
	for _, test := range function.ExpressionsIn() {
		arm := test.Parent()
		if test.IsTypeTest() && test.Arguments()[0].ExpressionHash() == subject && arm.Kind() == "If" && arm.Child("test") == test && arm.isSwitchArm() {
			arms = append(arms, arm)
		}
	}

	return arms
}

func (n Node) isSwitchArm() bool {
	body := n.ChildrenIn("body")

	return len(n.ChildrenIn("orelse")) > 0 || (len(body) > 0 && body[len(body)-1].IsBailOut()) || n.IsElif()
}

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

func (n Node) TypeSwitchClasses() []string {
	var classes []string
	for _, arm := range n.typeSwitchArms() {
		classes = append(classes, arm.Child("test").Arguments()[1].DottedName())
	}

	return classes
}

func (n Node) IsInDunder() bool {
	return n.EnclosingFunction().IsDunder()
}

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

func (n Node) IsAnd() bool {
	return n.Kind() == "BoolOp" && n.Node().Operator == "and"
}

func (n Node) isOutermostAnd() bool {
	return n.IsAnd() && !n.wrapper().IsAnd()
}

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

func (n Node) IsTypeNarrowingGuard() bool {
	checks := 0
	for _, conjunct := range n.Conjuncts() {
		if conjunct.IsInstanceCheck() {
			checks++
		}
	}

	return n.isOutermostAnd() && checks >= 2
}

func (n Node) GuardFingerprint() string {
	var hashes []string
	for _, conjunct := range n.resolvedConjuncts() {
		hashes = append(hashes, conjunct.askedOf()+conjunct.ExpressionHash())
	}
	slices.Sort(hashes)
	sum := sha1.Sum([]byte(strings.Join(hashes, "|")))

	return hex.EncodeToString(sum[:])
}

func (n Node) askedOf() string {
	object := n
	if n.Kind() == "Attribute" || n.Kind() == "Call" {
		object = n.Child("value")
	}
	if !object.Exists() {
		return ""
	}
	if object.DottedName() == "self" {
		if class := object.EnclosingClass(); class.Exists() {
			return class.Node().Symbol
		}

		return ""
	}
	if resolved := object.Node().Resolved; resolved != nil && resolved.Kind == "named" {
		return resolved.Name
	}

	return ""
}

func (n Node) IsAssignedValue() bool {
	parent := n.Parent()

	return (parent.Kind() == "Assign" || parent.Kind() == "AnnAssign") && parent.Child("value") == n
}

func (n Node) IsConstruction() bool {
	return (n.IsCall() && n.Callee().Kind() == "Attribute") || n.Kind() == "Dict" || n.Kind() == "List" || n.Kind() == "Set"
}

func (n Node) ConstructionShape() string {
	switch n.Kind() {
	case "Call":
		return n.Callee().ConstructionShape() + "()"
	case "Attribute":
		return n.Child("value").ConstructionShape() + "." + n.Name()
	}

	return strings.ToLower(n.Kind())
}
