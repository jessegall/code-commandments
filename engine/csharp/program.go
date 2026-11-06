package csharp

import (
	"iter"
	"slices"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Program is the C# part of a codebase read whole: the types, records, enums and methods it declares by symbol, how the calls to each method stand, and the vocabularies its calls spell.
type Program struct {
	codebase     *engine.Codebase
	facts        *Summary
	vocabularies map[string][]string
}

func Of(codebase *engine.Codebase) *Program {
	return engine.Analysis(codebase, "csharp.program", func(codebase *engine.Codebase) *Program {
		return over(codebase, Summarize(codebase))
	})
}

func Judge(codebase *engine.Codebase, whole *Summary) {
	engine.Keep(codebase, "csharp.kinds", whole.kinds())
	own := Summarize(codebase)
	engine.Keep(codebase, "csharp.program", over(codebase, whole))
	engine.Keep(codebase, "csharp.namespaces", graphOf(codebase, whole, own))
}

func over(codebase *engine.Codebase, facts *Summary) *Program {
	return &Program{codebase: codebase, facts: facts, vocabularies: facts.vocabularies()}
}

func (p *Program) all() iter.Seq[Node] {
	return func(yield func(Node) bool) {
		for _, file := range p.codebase.Of(contract.CSharp).Files() {
			for _, match := range file.Match(0).Descendants() {
				if !yield(Node{match}) {
					return
				}
			}
		}
	}
}

func (n Node) isComparedAsACase() bool {
	parent := n.Parent()
	if parent.Is("ParenthesizedExpression") {
		return parent.isComparedAsACase()
	}

	return parent.Is("EqualsExpression", "NotEqualsExpression", "CaseSwitchLabel", "ConstantPattern")
}

func (p *Program) declares(call Node) (inherited, declared bool) {
	if !call.Target().Exists() {
		return false, false
	}
	inherited, declared = p.facts.methods[call.Target().Symbol()]

	return inherited, declared
}

func (p *Program) ConstantNaming(call Node, position int, value string) string {
	for _, owner := range p.vocabularies[call.Target().Symbol()+"#"+strconv.Itoa(position)] {
		facts := p.facts.types[owner]
		for _, constant := range facts.Constants {
			if constant.Value == value {
				return facts.Name + "." + constant.Name
			}
		}
	}

	return ""
}

func (p *Program) CallsTo(method Node) Callers {
	return p.facts.callers[method.Symbol()]
}

func (p *Program) EveryCallerAccumulates(method Node) bool {
	callers := p.CallsTo(method)

	return callers.OutsideTests > 0 && callers.Accumulating == callers.OutsideTests
}

func (p *Program) ReachesOwnSignature(call Node) bool {
	inherited, declared := p.declares(call)

	return declared && !inherited
}

func (p *Program) IsHandedOut(name string) bool {
	return p.facts.handedOut[name]
}

func (p *Program) PassesLiteralKey(call Node) bool {
	if !call.Target().Exists() {
		return false
	}
	arguments := call.Arguments()

	return slices.ContainsFunc(p.facts.keys[call.Target().Symbol()], func(position int) bool {
		return position < len(arguments) && arguments[position].IsConstant() && arguments[position].Type().Name() == stringType
	})
}

func (p *Program) EnumMirroredBy(literals []string) bool {
	var names []string
	for _, literal := range literals {
		if lower := strings.ToLower(literal); !slices.Contains(names, lower) {
			names = append(names, lower)
		}
	}
	if len(names) < 2 {
		return false
	}

	return slices.ContainsFunc(p.facts.enumOrder, func(enum string) bool {
		var cases []string
		for _, member := range p.facts.enums[enum] {
			cases = append(cases, strings.ToLower(member))
		}

		return !slices.ContainsFunc(names, func(name string) bool { return !slices.Contains(cases, name) })
	})
}

func (p *Program) ComparesAsACase(symbol string) bool {
	return slices.Contains(p.facts.cased, symbol)
}

func (p *Program) DeclaresEnum(symbol string) bool {
	_, declared := p.facts.enums[symbol]

	return declared
}

func (p *Program) EnumMembers(symbol string) []string {
	return p.facts.enums[symbol]
}

func (p *Program) NamesEveryMember(switched Node) bool {
	members := p.EnumMembers(switched.At(0).Type().Name())
	named := switched.NamedCases()

	return len(members) > 0 && !slices.ContainsFunc(members, func(member string) bool { return !slices.Contains(named, member) })
}

func (p *Program) FillsRecordWithBlank(creation Node) bool {
	required, declared := p.facts.records[creation.Type().Name()]
	if !declared {
		return false
	}
	parameters := creation.Target().Parameters()
	for _, position := range creation.BlankArgumentPositions() {
		if position < len(parameters) && parameters[position] == stringType {
			return true
		}
	}

	return slices.ContainsFunc(creation.MembersInitializedBlank(), func(name string) bool { return slices.Contains(required, name) })
}

func (p *Program) TypeDeclared(symbol string) (TypeFacts, bool) {
	facts, declared := p.facts.types[symbol]

	return facts, declared
}

func (p *Program) DeclaresRecord(symbol string) bool {
	_, declared := p.facts.records[symbol]

	return declared
}

func (p *Program) DeclaresType(symbol string) bool {
	_, declared := p.facts.types[symbol]

	return declared
}
