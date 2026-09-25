package vue

import "github.com/jessegall/code-commandments/typescript"

// switchCases is how many equality branches a chain needs before it is a dispatch on one value.
const switchCases = 2

// EqualityTest is a condition that tests one subject against a literal: status === 'paid'.
type EqualityTest struct {
	Subject string
	Key     string
}

// SwitchCaseChain is a v-if and the v-else-ifs after it that equality-test one subject, and a v-else that
// ends it: one decision written as several conditionals.
type SwitchCaseChain struct {
	Subject  string
	Head     Element
	Branches []Element
}

// SwitchCaseChain is the chain the element heads; false unless it re-tests one subject at least twice.
func (e Element) SwitchCaseChain() (SwitchCaseChain, bool) {
	test, ok := equality(e.Directive(If))
	if !ok {
		return SwitchCaseChain{}, false
	}
	chain := SwitchCaseChain{Subject: test.Subject, Head: e, Branches: []Element{e}}
	cases := 1
	for _, sibling := range e.Following() {
		if !sibling.Has(ElseIf) {
			if sibling.Has(Else) {
				chain.Branches = append(chain.Branches, sibling)
			}
			break
		}
		next, ok := equality(sibling.Directive(ElseIf))
		if !ok || next.Subject != test.Subject {
			return SwitchCaseChain{}, false
		}
		chain.Branches = append(chain.Branches, sibling)
		cases++
	}

	return chain, cases >= switchCases
}

// HeadsSwitchCase says whether the element heads a chain of conditionals that is one dispatch on a value.
func (e Element) HeadsSwitchCase() bool {
	_, ok := e.SwitchCaseChain()

	return ok
}

// equality is the directive's condition as a test of a subject against a literal; false for any other condition.
func equality(directive Directive) (EqualityTest, bool) {
	condition := typescript.Of(directive.Value())
	if !condition.IsEquality() {
		return EqualityTest{}, false
	}
	subject, key := condition.Left(), condition.Right()
	if !key.IsLiteral() || !subject.IsReference() {
		return EqualityTest{}, false
	}

	return EqualityTest{Subject: subject.Source(), Key: key.LiteralValue()}, true
}
