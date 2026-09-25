package python

import (
	"slices"
	"strings"
)

// IsScalarConstantClass says whether the class is a plain class of two or more names bound to single-line string
// or number literals and nothing else: a closed set of values written without an enum.
func (n Node) IsScalarConstantClass() bool {
	if n.Kind() != "ClassDef" || len(n.Decorators()) > 0 || len(n.ChildrenIn("keywords")) > 0 {
		return false
	}
	if slices.ContainsFunc(n.ChildrenIn("bases"), func(base Node) bool { return base.DottedName() != "object" }) {
		return false
	}
	statements := n.StatementsBeyondText()

	return len(statements) >= 2 && !slices.ContainsFunc(statements, func(statement Node) bool {
		targets := statement.ChildrenIn("targets")

		return statement.Kind() != "Assign" || len(targets) != 1 || targets[0].Kind() != "Name" || !statement.Child("value").isScalarValue()
	})
}

// isScalarValue says whether the expression is a string or number literal on one line.
func (n Node) isScalarValue() bool {
	literal := n.Node().Literal
	text, _ := n.Text()

	return n.Kind() == "Constant" && (literal == "string" || literal == "int" || literal == "float") && !strings.Contains(text, "\n")
}

// OrChainedCaseClass is the enum an `or` chain tests one subject against case after case of: `s == Colour.RED or
// s == Colour.BLUE`; empty when it is no such chain.
func (p *Program) OrChainedCaseClass(n Node) string {
	if n.Kind() != "BoolOp" || n.Node().Operator != "or" {
		return ""
	}
	links := n.orLinks()
	var subject Node
	var class string
	for at, link := range links {
		tested, enum := p.caseTest(link)
		if enum == "" || (at > 0 && (enum != class || !tested.IsSame(subject))) {
			return ""
		}
		subject, class = tested, enum
	}

	return class
}

// orLinks is the operands of an `or`, nested ones spread.
func (n Node) orLinks() []Node {
	if n.Kind() != "BoolOp" || n.Node().Operator != "or" {
		return []Node{n}
	}
	var links []Node
	for _, value := range n.ChildrenIn("values") {
		links = append(links, value.orLinks()...)
	}

	return links
}

// caseTest is what a comparison tests against a case of an enum the program declares, with `==` or `is`, and the
// enum's name.
func (p *Program) caseTest(n Node) (Node, string) {
	left, right, ok := n.comparedWith("==", "is")
	switch {
	case !ok:
		return Node{}, ""
	case p.isMemberOf(right):
		return left, right.Child("value").DottedName()
	case p.isMemberOf(left):
		return right, left.Child("value").DottedName()
	}

	return Node{}, ""
}

// isMemberOf says whether the expression names a case of an enum the program declares: `Colour.RED`.
func (p *Program) isMemberOf(n Node) bool {
	owner := n.Child("value")

	return n.Kind() == "Attribute" && owner.Kind() == "Name" && p.IsEnum(owner.DottedName())
}

// IsInsideOr says whether the expression is an operand of an `or`.
func (n Node) IsInsideOr() bool {
	around := n.wrapper()

	return around.Kind() == "BoolOp" && around.Node().Operator == "or"
}

// MembershipLiteralKeys is the literal keys an `in` or `not in` test checks membership among, when they are two
// or more strings in a list, tuple or set display; empty otherwise.
func (n Node) MembershipLiteralKeys() []string {
	_, set, ok := n.comparedWith("in", "not in")
	if !ok || !set.isDisplay() || set.Kind() == "Dict" {
		return nil
	}
	elements := set.Children()
	var keys []string
	for _, element := range elements {
		if element.Node().Literal != "string" {
			return nil
		}
		keys = append(keys, element.LiteralKey())
	}
	if len(keys) < 2 {
		return nil
	}

	return keys
}

// IsMatchOnEnumValue says whether the statement matches an enum's `.value` against literals that all name one
// enum's members: a match that should be on the enum itself.
func (p *Program) IsMatchOnEnumValue(n Node) bool {
	subject := n.Child("subject")

	return n.Kind() == "Match" && subject.Kind() == "Attribute" && subject.Name() == "value" && subject.RootName() != "self" && p.EnumsHoldAll(n.caseKeys(isLiteralPattern))
}

// IsStringMatchMirroringEnum says whether the statement matches a subject against strings that all name one enum's
// members: a match that mirrors an enum it should use.
func (p *Program) IsStringMatchMirroringEnum(n Node) bool {
	subject := n.Child("subject")
	readsValue := subject.Kind() == "Attribute" && subject.Name() == "value"

	return n.Kind() == "Match" && !readsValue && p.EnumsHoldAll(n.caseKeys(isTextPattern))
}

// IsEnumMatchWithAbsentWildcard says whether the statement matches one enum's members case by case and answers
// absence only in its wildcard: every member handled, and the leftover `case _` made up.
func (p *Program) IsEnumMatchWithAbsentWildcard(n Node) bool {
	classes := n.memberCaseClasses()

	return n.Kind() == "Match" && len(classes) == 1 && p.IsEnum(classes[0]) && n.onlyTheWildcardAnswersAbsence()
}

// caseKeys is the literal key of every alternative a match's cases test, a wildcard aside, when every one of them
// is a pattern the check admits; empty otherwise.
func (n Node) caseKeys(admits func(Node) bool) []string {
	var keys []string
	for _, match := range n.ChildrenIn("cases") {
		for _, pattern := range match.Child("pattern").alternatives() {
			if pattern.isWildcardPattern() {
				continue
			}
			if !admits(pattern) {
				return nil
			}
			keys = append(keys, pattern.patternKey())
		}
	}

	return keys
}

// alternatives is the patterns an or-pattern tries, nested ones spread; the pattern itself otherwise.
func (n Node) alternatives() []Node {
	if n.Kind() != "MatchOr" {
		return []Node{n}
	}
	var alternatives []Node
	for _, pattern := range n.ChildrenIn("patterns") {
		alternatives = append(alternatives, pattern.alternatives()...)
	}

	return alternatives
}

// isWildcardPattern says whether the pattern is `_`.
func (n Node) isWildcardPattern() bool {
	return n.Kind() == "MatchAs" && n.Name() == "" && len(n.Children()) == 0
}

// isLiteralPattern says whether the pattern is a literal: a value pattern holding one, or None, True or False.
func isLiteralPattern(n Node) bool {
	return n.Kind() == "MatchSingleton" || (n.Kind() == "MatchValue" && n.Child("value").Kind() == "Constant")
}

// isTextPattern says whether the pattern is a string literal.
func isTextPattern(n Node) bool {
	return n.Kind() == "MatchValue" && n.Child("value").Node().Literal == "string"
}

// patternKey is a literal pattern as a comparable key.
func (n Node) patternKey() string {
	if n.Kind() == "MatchSingleton" {
		if n.Written() == "None" {
			return "none:None"
		}

		return "bool:" + n.Written()
	}

	return n.Child("value").LiteralKey()
}

// memberCaseClasses is the one or more names the cases of a match test members of, `Colour` for `case
// Colour.RED`, when every alternative but the wildcard is such a member; empty otherwise.
func (n Node) memberCaseClasses() []string {
	var alternatives []Node
	for _, match := range n.ChildrenIn("cases") {
		if !match.isWildcardCase() {
			alternatives = append(alternatives, match.Child("pattern").alternatives()...)
		}
	}
	var classes []string
	for _, pattern := range alternatives {
		member := pattern.Child("value")
		if pattern.Kind() != "MatchValue" || member.Kind() != "Attribute" || member.Child("value").Kind() != "Name" {
			return nil
		}
		if owner := member.Child("value").DottedName(); !slices.Contains(classes, owner) {
			classes = append(classes, owner)
		}
	}

	return classes
}

// isWildcardCase says whether a match case is `case _:` with no guard.
func (n Node) isWildcardCase() bool {
	return n.Child("pattern").isWildcardPattern() && !n.Child("guard").Exists()
}

// onlyTheWildcardAnswersAbsence says whether a match's wildcard returns an absent value and none of its other
// cases does.
func (n Node) onlyTheWildcardAnswersAbsence() bool {
	wildcard, handled := false, false
	for _, match := range n.ChildrenIn("cases") {
		body := match.ChildrenIn("body")
		absent := len(body) > 0 && body[len(body)-1].returnsAbsence()
		if match.isWildcardCase() {
			wildcard = wildcard || absent
		} else {
			handled = handled || absent
		}
	}

	return wildcard && !handled
}

// returnsAbsence says whether the statement returns nothing, None, False, an empty string or an empty collection.
func (n Node) returnsAbsence() bool {
	if n.Kind() != "Return" {
		return false
	}
	value := n.ReturnedValue()

	return !value.Exists() || value.IsNone() || value.IsEmptyCollection() || (value.Node().Literal == "bool" && value.Written() == "False") || value.IsBlankString()
}
