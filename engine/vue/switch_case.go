package vue

import (
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/typescript"
)

// switchCases is how many equality branches a chain needs before it is a dispatch on one value.
const switchCases = 2

// SwitchCaseTag is the tag of the published <SwitchCase>, which renders the one slot its value names.
const SwitchCaseTag = "SwitchCase"

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
	Branches []SwitchCaseBranch
}

// SwitchCaseBranch is one element of a chain and the key it matches; the v-else that ends a chain matches none.
type SwitchCaseBranch struct {
	Element
	Key      string
	Fallback bool
}

// Slot is the name of the slot the branch becomes: its key, or `default` for the fallback.
func (b SwitchCaseBranch) Slot() string {
	if b.Fallback {
		return "default"
	}

	return b.Key
}

// Span is the chain's source, from its head's start to its last branch's end.
func (c SwitchCaseChain) Span() (engine.Span, error) {
	span, err := c.Head.Span()
	if err != nil {
		return span, err
	}
	span.End = c.Branches[len(c.Branches)-1].Node().Span.End

	return span, nil
}

// SwitchCaseChain is the chain the element heads; false unless it re-tests one subject at least twice.
func (e Element) SwitchCaseChain() (SwitchCaseChain, bool) {
	test, ok := equality(e.Directive(If))
	if !ok {
		return SwitchCaseChain{}, false
	}
	chain := SwitchCaseChain{Subject: test.Subject, Head: e, Branches: []SwitchCaseBranch{{Element: e, Key: test.Key}}}
	cases := 1
	for _, sibling := range e.Following() {
		if !sibling.Has(ElseIf) {
			if sibling.Has(Else) {
				chain.Branches = append(chain.Branches, SwitchCaseBranch{Element: sibling, Fallback: true})
			}
			break
		}
		next, ok := equality(sibling.Directive(ElseIf))
		if !ok || next.Subject != test.Subject {
			return SwitchCaseChain{}, false
		}
		chain.Branches = append(chain.Branches, SwitchCaseBranch{Element: sibling, Key: next.Key})
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

// IsSwitchCase says whether the element is the published <SwitchCase>.
func (e Element) IsSwitchCase() bool {
	return e.Tag() == SwitchCaseTag
}

// IsDispatch says whether the element is one decision on a value: a <SwitchCase>, or the head of a chain of
// conditionals re-testing one subject.
func (e Element) IsDispatch() bool {
	return e.IsSwitchCase() || e.HeadsSwitchCase()
}

// Cases is each case a dispatch renders: a <SwitchCase>'s slots, or a chain's branches; none for any other element.
func (e Element) Cases() []Element {
	if e.IsSwitchCase() {
		return e.Elements()
	}
	chain, ok := e.SwitchCaseChain()
	if !ok {
		return nil
	}
	cases := make([]Element, 0, len(chain.Branches))
	for _, branch := range chain.Branches {
		cases = append(cases, branch.Element)
	}

	return cases
}

// ViewCases is the cases of a dispatch that each render a whole view.
func (e Element) ViewCases() []Element {
	var views []Element
	for _, dispatched := range e.Cases() {
		if dispatched.HoldsView() {
			views = append(views, dispatched)
		}
	}

	return views
}
