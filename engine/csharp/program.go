package csharp

import (
	"slices"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Program is the C# part of a codebase read whole: the types, records, enums and methods it declares by symbol,
// who calls each method, and the vocabularies its calls spell.
type Program struct {
	nodes        []Node
	types        map[string]Node
	records      map[string]Node
	enums        map[string][]string
	enumOrder    []string
	methods      map[string]Node
	callers      map[string][]Node
	keys         map[string][]int
	handedOut    map[string]bool
	cased        []string
	vocabularies map[string][]Node
}

// Of is the C# program the codebase holds, read once.
func Of(codebase *engine.Codebase) *Program {
	return engine.Analysis(codebase, "csharp.program", build)
}

func build(codebase *engine.Codebase) *Program {
	program := &Program{types: map[string]Node{}, records: map[string]Node{}, enums: map[string][]string{}, methods: map[string]Node{}, callers: map[string][]Node{}, keys: map[string][]int{}, handedOut: map[string]bool{}}
	for _, file := range codebase.Of(contract.CSharp).Files() {
		for _, match := range file.Match(0).Descendants() {
			program.nodes = append(program.nodes, Node{match})
		}
	}
	for _, node := range program.nodes {
		program.enrol(node)
	}
	program.findHandedOut()
	program.findVocabularies()

	return program
}

// enrol files the node under every index it belongs to.
func (p *Program) enrol(node Node) {
	switch {
	case node.IsTypeDeclaration():
		if _, held := p.types[node.Symbol()]; !held {
			p.types[node.Symbol()] = node
		}
	case node.Is("MethodDeclaration"):
		p.methods[node.Symbol()] = node
		p.enrolKeys(node)
	case node.IsCall() && node.Target().Exists():
		p.callers[node.Target().Symbol()] = append(p.callers[node.Target().Symbol()], node)
	case node.Is("SimpleMemberAccessExpression") && node.IsConstant() && node.isComparedAsACase():
		if owner := node.At(0).Type().Name(); !slices.Contains(p.cased, owner) {
			p.cased = append(p.cased, owner)
		}
	}
	if node.IsRecord() {
		p.records[node.Symbol()] = node
	}
	if node.Is("EnumDeclaration") {
		var members []string
		for _, member := range node.All() {
			if member.Is("EnumMemberDeclaration") {
				members = append(members, member.Name())
			}
		}
		p.enums[node.Symbol()] = members
		p.enumOrder = append(p.enumOrder, node.Symbol())
	}
}

// enrolKeys files the positions of the method's parameters it reads a dictionary by.
func (p *Program) enrolKeys(method Node) {
	var names []string
	for _, parameter := range method.firstChild("ParameterList").All() {
		names = append(names, parameter.Name())
	}
	var positions []int
	for _, read := range method.expressionParts() {
		arguments := read.Arguments()
		if !read.IsKeyedRead() || len(arguments) == 0 || !arguments[0].Is("IdentifierName") || !read.ReadsDictionaryNamed(names) {
			continue
		}
		if position := slices.Index(names, arguments[0].Name()); position >= 0 && !slices.Contains(positions, position) {
			positions = append(positions, position)
		}
	}
	if method.Symbol() != "" && len(positions) > 0 {
		p.keys[method.Symbol()] = positions
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

// findHandedOut files the names of the methods handed out as delegates: named without being called, and without a
// type the compiler gave the name.
func (p *Program) findHandedOut() {
	named := map[*contract.Node]bool{}
	for _, node := range p.nodes {
		switch {
		case node.IsCall():
			named[node.At(0).Node()] = true
		case node.Is("SimpleMemberAccessExpression"):
			named[node.At(1).Node()] = true
		}
	}
	for _, node := range p.nodes {
		if node.Is("IdentifierName", "SimpleMemberAccessExpression") && node.IsExpression() && !node.Type().Exists() && !named[node.Node()] {
			p.handedOut[node.ReferencedName()] = true
		}
	}
}

// findVocabularies files, for every parameter of the codebase's own methods, the types whose string constants some
// call fills it with. A library method's parameter takes values from every vocabulary at once.
func (p *Program) findVocabularies() {
	p.vocabularies = map[string][]Node{}
	for _, call := range p.nodes {
		if !call.IsCall() || !call.Target().Exists() || !call.PassesByPosition() || !p.DeclarationOf(call).Exists() {
			continue
		}
		for position, argument := range call.Arguments() {
			if !argument.Is("SimpleMemberAccessExpression") {
				continue
			}
			owner := p.TypeDeclared(strings.TrimSuffix(argument.At(0).Type().Name(), "?"))
			slot := call.Target().Symbol() + "#" + strconv.Itoa(position)
			if owner.Exists() && owner.holdsStringConstantNamed(argument.At(1).Name()) && !slices.ContainsFunc(p.vocabularies[slot], func(held Node) bool { return held.Node() == owner.Node() }) {
				p.vocabularies[slot] = append(p.vocabularies[slot], owner)
			}
		}
	}
}

// holdsStringConstantNamed says whether the type declares a string constant under the name.
func (n Node) holdsStringConstantNamed(name string) bool {
	return slices.ContainsFunc(n.StringConstants(), func(constant StringConstant) bool { return constant.Name == name })
}

// DeclarationOf is the method the call reaches, when the codebase declares it; no node for a library's method, or
// a call the compiler did not resolve.
func (p *Program) DeclarationOf(call Node) Node {
	if !call.Target().Exists() {
		return Node{}
	}

	return p.methods[call.Target().Symbol()]
}

// ConstantNaming is the constant that already names the value in the parameter the call fills at the position,
// `Token.BraceOpen` for `"{"`; empty when that parameter is never spelled by name.
func (p *Program) ConstantNaming(call Node, position int, value string) string {
	for _, owner := range p.vocabularies[call.Target().Symbol()+"#"+strconv.Itoa(position)] {
		for _, constant := range owner.StringConstants() {
			if constant.Value == value {
				return owner.Name() + "." + constant.Name
			}
		}
	}

	return ""
}

// CallersOf is every call the compiler resolved to the method, a declared member; none when nothing calls it.
func (p *Program) CallersOf(method Node) []Node {
	return p.callers[method.Symbol()]
}

// ReachesOwnSignature says whether the call reaches a method the codebase declares and may change the signature
// of: its own, and not one an interface or a base class dictates.
func (p *Program) ReachesOwnSignature(call Node) bool {
	method := p.DeclarationOf(call)

	return method.Exists() && !method.IsInherited()
}

// IsHandedOut says whether a method named so is handed out as a delegate somewhere, so it has callers no call
// site shows.
func (p *Program) IsHandedOut(name string) bool {
	return p.handedOut[name]
}

// PassesLiteralKey says whether the call hands a string written in the source to a parameter the method it calls
// reads a dictionary by.
func (p *Program) PassesLiteralKey(call Node) bool {
	if !call.Target().Exists() {
		return false
	}
	arguments := call.Arguments()

	return slices.ContainsFunc(p.keys[call.Target().Symbol()], func(position int) bool {
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

	return slices.ContainsFunc(p.enumOrder, func(enum string) bool {
		var cases []string
		for _, member := range p.enums[enum] {
			cases = append(cases, strings.ToLower(member))
		}

		return !slices.ContainsFunc(names, func(name string) bool { return !slices.Contains(cases, name) })
	})
}

// ComparesAsACase says whether a constant of the class is compared as a case anywhere. A constant only ever handed
// on as a name is not a case.
func (p *Program) ComparesAsACase(symbol string) bool {
	return slices.Contains(p.cased, symbol)
}

// DeclaresEnum says whether the codebase declares the enum.
func (p *Program) DeclaresEnum(symbol string) bool {
	_, declared := p.enums[symbol]

	return declared
}

// EnumMembers is the member names of the enum the codebase declares; none for any other type.
func (p *Program) EnumMembers(symbol string) []string {
	return p.enums[symbol]
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
	record, declared := p.records[creation.Type().Name()]
	if !declared {
		return false
	}
	parameters := p.ParametersOf(creation)
	for _, position := range creation.BlankArgumentPositions() {
		if position < len(parameters) && parameters[position] == stringType {
			return true
		}
	}
	required := record.RequiredTextNames()

	return slices.ContainsFunc(creation.MembersInitializedBlank(), func(name string) bool { return slices.Contains(required, name) })
}

// TypeDeclared is the declaration of the type the codebase names so; no node for a type it does not declare.
func (p *Program) TypeDeclared(symbol string) Node {
	return p.types[symbol]
}

// DeclaresRecord says whether the codebase declares the record: a value, compared by what it holds.
func (p *Program) DeclaresRecord(symbol string) bool {
	_, declared := p.records[symbol]

	return declared
}

// DeclaresType says whether the codebase declares the type: a class, record, struct, interface or enum of its own.
func (p *Program) DeclaresType(symbol string) bool {
	_, declared := p.types[symbol]

	return declared
}

// ParametersOf is the types of the parameters the call fills, by position: the declaration's, when the codebase
// declares the method, less the `this` an extension method called on a receiver fills with it; the method's own as
// its symbol spells them otherwise.
func (p *Program) ParametersOf(call Node) []string {
	method := p.DeclarationOf(call)
	if !method.Exists() {
		return call.Target().Parameters()
	}
	parameters := method.Parameters()
	if len(parameters) > 0 && parameters[0].HasModifier("this") && call.At(0).Is("SimpleMemberAccessExpression", "MemberBindingExpression") {
		parameters = parameters[1:]
	}
	types := []string{}
	for _, parameter := range parameters {
		types = append(types, parameter.Type().Name())
	}

	return types
}
