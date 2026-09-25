package csharp

import (
	"iter"
	"slices"
	"strconv"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Program is the C# part of a codebase read whole: the types, records, enums and methods it declares by symbol,
// how the calls to each method stand, and the vocabularies its calls spell. It answers from the summary of the
// whole program, so a part judged alone reads the program as the whole of it.
type Program struct {
	codebase     *engine.Codebase
	facts        *Summary
	vocabularies map[string][]string
	documented   sync.Map
}

// Of is the C# program the codebase holds, read once: its own summary, unless the codebase is one part of a
// program judged a part at a time.
func Of(codebase *engine.Codebase) *Program {
	return engine.Analysis(codebase, "csharp.program", func(codebase *engine.Codebase) *Program {
		return over(codebase, Summarize(codebase))
	})
}

// Judge makes the codebase one part of a program the summary tells of: its rules read the program the summary
// merges, the whole of it, and the namespaces of the rest beside the part's own.
func Judge(codebase *engine.Codebase, whole *Summary) {
	engine.Keep(codebase, "csharp.kinds", whole.kinds())
	own := Summarize(codebase)
	engine.Keep(codebase, "csharp.program", over(codebase, whole))
	engine.Keep(codebase, "csharp.namespaces", graphOf(codebase, whole, own))
}

func over(codebase *engine.Codebase, facts *Summary) *Program {
	return &Program{codebase: codebase, facts: facts, vocabularies: facts.vocabularies()}
}

// all is every node of every C# file, walked again each time: holding them for the run would hold a second
// reference to every node the codebase already holds.
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

// isComparedAsACase says whether the value is compared as a case where it stands: a side of `==` or `!=`, a `case`
// label, or a constant pattern.
func (n Node) isComparedAsACase() bool {
	parent := n.Parent()
	if parent.Is("ParenthesizedExpression") {
		return parent.isComparedAsACase()
	}

	return parent.Is("EqualsExpression", "NotEqualsExpression", "CaseSwitchLabel", "ConstantPattern")
}

// declares says whether the program declares the method the call reaches, and whether an interface or a base
// class dictates its signature.
func (p *Program) declares(call Node) (inherited, declared bool) {
	if !call.Target().Exists() {
		return false, false
	}
	inherited, declared = p.facts.methods[call.Target().Symbol()]

	return inherited, declared
}

// ConstantNaming is the constant that already names the value in the parameter the call fills at the position,
// `Token.BraceOpen` for `"{"`; empty when that parameter is never spelled by name.
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

// CallsTo is how the calls the compiler resolved to the method, a declared member, stand; none when nothing calls
// it.
func (p *Program) CallsTo(method Node) Callers {
	return p.facts.callers[method.Symbol()]
}

// ReachesOwnSignature says whether the call reaches a method the codebase declares and may change the signature
// of: its own, and not one an interface or a base class dictates.
func (p *Program) ReachesOwnSignature(call Node) bool {
	inherited, declared := p.declares(call)

	return declared && !inherited
}

// IsHandedOut says whether a method named so is handed out as a delegate somewhere, so it has callers no call
// site shows.
func (p *Program) IsHandedOut(name string) bool {
	return p.facts.handedOut[name]
}

// PassesLiteralKey says whether the call hands a string written in the source to a parameter the method it calls
// reads a dictionary by.
func (p *Program) PassesLiteralKey(call Node) bool {
	if !call.Target().Exists() {
		return false
	}
	arguments := call.Arguments()

	return slices.ContainsFunc(p.facts.keys[call.Target().Symbol()], func(position int) bool {
		return position < len(arguments) && arguments[position].IsConstant() && arguments[position].Type().Name() == stringType
	})
}

// EnumMirroredBy says whether the literals, two or more of them, all name members of one enum the codebase
// declares, as a string on the wire would spell them in any case.
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

// ComparesAsACase says whether a constant of the class is compared as a case anywhere. A constant only ever handed
// on as a name is not a case.
func (p *Program) ComparesAsACase(symbol string) bool {
	return slices.Contains(p.facts.cased, symbol)
}

// DeclaresEnum says whether the codebase declares the enum.
func (p *Program) DeclaresEnum(symbol string) bool {
	_, declared := p.facts.enums[symbol]

	return declared
}

// EnumMembers is the member names of the enum the codebase declares; none for any other type.
func (p *Program) EnumMembers(symbol string) []string {
	return p.facts.enums[symbol]
}

// NamesEveryMember says whether the switch names every member of the enum it switches over, one the codebase
// declares.
func (p *Program) NamesEveryMember(switched Node) bool {
	members := p.EnumMembers(switched.At(0).Type().Name())
	named := switched.NamedCases()

	return len(members) > 0 && !slices.ContainsFunc(members, func(member string) bool { return !slices.Contains(named, member) })
}

// FillsRecordWithBlank says whether the creation builds a record the codebase declares with a blank string in one
// of its required `string` slots, by position into a `string` parameter, or by name in its initializer.
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

// TypeDeclared is what the program tells of the type it names so; false for a type it does not declare.
func (p *Program) TypeDeclared(symbol string) (TypeFacts, bool) {
	facts, declared := p.facts.types[symbol]

	return facts, declared
}

// DeclaresRecord says whether the codebase declares the record: a value, compared by what it holds.
func (p *Program) DeclaresRecord(symbol string) bool {
	_, declared := p.facts.records[symbol]

	return declared
}

// DeclaresType says whether the codebase declares the type: a class, record, struct, interface or enum of its own.
func (p *Program) DeclaresType(symbol string) bool {
	_, declared := p.facts.types[symbol]

	return declared
}

// documentable is the declarations and statements a comment in the file may be about, in the order a pre-order
// walk meets them, which is the order they start in.
func (p *Program) documentable(file *engine.File) []Node {
	if held, ok := p.documented.Load(file); ok {
		return held.([]Node)
	}
	var documentable []Node
	for _, match := range file.Match(0).Descendants() {
		if node := (Node{match}); !node.IsExpression() && node.hierarchyRole() != "other" {
			documentable = append(documentable, node)
		}
	}
	held, _ := p.documented.LoadOrStore(file, documentable)

	return held.([]Node)
}
