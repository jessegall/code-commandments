package csharp

import (
	"slices"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Summary is what the C# of one part of a program tells the rest: every fact a rule asks of the whole program,
// held as names, counts and flags rather than as trees. A solution too large to hold whole is judged a project at
// a time, each project with the summary of every project merged, so a rule reading the program answers as it
// would over the whole solution at once.
type Summary struct {
	types      map[string]TypeFacts
	records    map[string][]string
	enums      map[string][]string
	enumOrder  []string
	methods    map[string]bool
	callers    map[string]Callers
	keys       map[string][]int
	handedOut  map[string]bool
	cased      []string
	spellings  []spelling
	homes      map[string]string
	references map[reference]int
}

// TypeFacts is what the program asks of a type it declares: what kind of declaration it is, its name, the state
// it keeps and the string constants it declares.
type TypeFacts struct {
	Kind      string
	Name      string
	State     []StateFact
	Constants []StringConstant
}

// StateFact is one piece of state a type keeps, by name and by the name of its type; no type name when it has none.
type StateFact struct {
	Name string
	Type string
}

// Callers is how the calls to one method stand: how many are made outside the tests, and how many of those assert
// the result is there.
type Callers struct {
	OutsideTests int
	Asserting    int
}

// spelling is a call filling a parameter, by position, with a constant of a type: `Emit(Token.BraceOpen)`.
type spelling struct {
	slot     string
	owner    string
	constant string
}

// reference is a node in a namespace reaching the types a sorted set of type keys names.
type reference struct {
	from    string
	reached string
}

func newSummary() *Summary {
	return &Summary{types: map[string]TypeFacts{}, records: map[string][]string{}, enums: map[string][]string{}, methods: map[string]bool{}, callers: map[string]Callers{}, keys: map[string][]int{}, handedOut: map[string]bool{}, homes: map[string]string{}, references: map[reference]int{}}
}

// kindsOf is the kind of declaration of every type the program declares, by symbol, the first declaration of each:
// read from the declarations alone, without asking any node its type, since a type asks it whether it is a value.
func kindsOf(codebase *engine.Codebase) map[string]string {
	return engine.Analysis(codebase, "csharp.kinds", func(codebase *engine.Codebase) map[string]string {
		kinds := map[string]string{}
		for node := range (&Program{codebase: codebase}).all() {
			if _, held := kinds[node.Symbol()]; node.IsTypeDeclaration() && !held {
				kinds[node.Symbol()] = node.Kind()
			}
		}

		return kinds
	})
}

// kinds is the kind of declaration of every type the summary tells of, by symbol.
func (s *Summary) kinds() map[string]string {
	kinds := map[string]string{}
	for symbol, facts := range s.types {
		kinds[symbol] = facts.Kind
	}

	return kinds
}

// Summarize is the summary of the codebase's C#.
func Summarize(codebase *engine.Codebase) *Summary {
	summary := newSummary()
	program := &Program{codebase: codebase}
	named := map[*contract.Node]bool{}
	for node := range program.all() {
		summary.enrol(node, named)
	}
	for node := range program.all() {
		if node.Is("IdentifierName", "SimpleMemberAccessExpression") && node.IsExpression() && !node.Type().Exists() && !named[node.Node()] {
			summary.handedOut[node.ReferencedName()] = true
		}
	}
	for _, file := range codebase.Of(contract.CSharp).Files() {
		summary.walkReferences(Node{file.Match(0)}, "")
	}

	return summary
}

// Merge adds what another part of the program tells to this summary.
func (s *Summary) Merge(other *Summary) {
	for symbol, facts := range other.types {
		if _, held := s.types[symbol]; !held {
			s.types[symbol] = facts
		}
	}
	for symbol, members := range other.enums {
		if _, held := s.enums[symbol]; !held {
			s.enumOrder = append(s.enumOrder, symbol)
		}
		s.enums[symbol] = members
	}
	for symbol, callers := range other.callers {
		held := s.callers[symbol]
		s.callers[symbol] = Callers{held.OutsideTests + callers.OutsideTests, held.Asserting + callers.Asserting}
	}
	for held, count := range other.references {
		s.references[held] += count
	}
	for _, owner := range other.cased {
		if !slices.Contains(s.cased, owner) {
			s.cased = append(s.cased, owner)
		}
	}
	s.spellings = append(s.spellings, other.spellings...)
	merge(s.records, other.records)
	merge(s.methods, other.methods)
	merge(s.keys, other.keys)
	merge(s.handedOut, other.handedOut)
	merge(s.homes, other.homes)
}

func merge[V any](into, from map[string]V) {
	for key, value := range from {
		into[key] = value
	}
}

// enrol files the node under every fact it adds; named gathers the nodes a call or a member access names.
func (s *Summary) enrol(node Node, named map[*contract.Node]bool) {
	switch {
	case node.IsTypeDeclaration():
		if _, held := s.types[node.Symbol()]; !held {
			s.types[node.Symbol()] = factsOf(node)
		}
		s.homes[typeKey(node.Symbol())] = node.Parent().namespaceAround()
	case node.Is("MethodDeclaration"):
		s.methods[node.Symbol()] = node.IsInherited()
		s.enrolKeys(node)
	case node.IsCall() && node.Target().Exists():
		s.enrolCall(node)
	case node.Is("SimpleMemberAccessExpression") && node.IsConstant() && node.isComparedAsACase():
		if owner := node.At(0).Type().Name(); !slices.Contains(s.cased, owner) {
			s.cased = append(s.cased, owner)
		}
	}
	switch {
	case node.IsCall():
		named[node.At(0).Node()] = true
	case node.Is("SimpleMemberAccessExpression"):
		named[node.At(1).Node()] = true
	}
	if node.IsRecord() {
		s.records[node.Symbol()] = node.RequiredTextNames()
	}
	if node.Is("EnumDeclaration") {
		var members []string
		for _, member := range node.All() {
			if member.Is("EnumMemberDeclaration") {
				members = append(members, member.Name())
			}
		}
		s.enums[node.Symbol()] = members
		s.enumOrder = append(s.enumOrder, node.Symbol())
	}
}

// factsOf is what the program asks of the type declaration.
func factsOf(declaration Node) TypeFacts {
	facts := TypeFacts{Kind: declaration.Kind(), Name: declaration.Name(), Constants: declaration.StringConstants()}
	for _, state := range declaration.StateTypes() {
		held := StateFact{Name: state.Name}
		if state.Type.Exists() {
			held.Type = state.Type.Name()
		}
		facts.State = append(facts.State, held)
	}

	return facts
}

// enrolCall counts the call against the method it reaches, and files the constants it fills parameters with.
func (s *Summary) enrolCall(call Node) {
	symbol := call.Target().Symbol()
	if !call.IsInTest() {
		held := s.callers[symbol]
		held.OutsideTests++
		if call.ResultIsAssertedPresent() {
			held.Asserting++
		}
		s.callers[symbol] = held
	}
	if !call.PassesByPosition() {
		return
	}
	for position, argument := range call.Arguments() {
		if argument.Is("SimpleMemberAccessExpression") {
			s.spellings = append(s.spellings, spelling{slot: symbol + "#" + strconv.Itoa(position), owner: strings.TrimSuffix(argument.At(0).Type().Name(), "?"), constant: argument.At(1).Name()})
		}
	}
}

// enrolKeys files the positions of the method's parameters it reads a dictionary by.
func (s *Summary) enrolKeys(method Node) {
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
		s.keys[method.Symbol()] = positions
	}
}

// walkReferences counts, for every node under the one given that stands in a namespace, the types it reaches.
func (s *Summary) walkReferences(node Node, namespace string) {
	here := namespace
	if node.Is("NamespaceDeclaration", "FileScopedNamespaceDeclaration") {
		here = node.NamespaceName()
	}
	if reached := reachedKeys(node); here != "" && len(reached) > 0 {
		s.references[reference{from: here, reached: strings.Join(reached, "\x00")}]++
	}
	for _, child := range node.All() {
		s.walkReferences(child, here)
	}
}

// reachedKeys is the keys of the types the node reaches, sorted: the types its type is made of, and the type
// declaring what it calls.
func reachedKeys(node Node) []string {
	var symbols []string
	if typed := node.Type(); typed.Exists() {
		symbols = append(symbols, typed.NamedTypes()...)
	}
	if target := node.Target(); target.Exists() {
		symbols = append(symbols, target.Type())
	}
	var keys []string
	for _, symbol := range symbols {
		if key := typeKey(symbol); !slices.Contains(keys, key) {
			keys = append(keys, key)
		}
	}
	slices.Sort(keys)

	return keys
}

// vocabularies is, for every parameter of the program's own methods, the types whose string constants some call
// fills it with.
func (s *Summary) vocabularies() map[string][]string {
	vocabularies := map[string][]string{}
	for _, spelled := range s.spellings {
		method, _, _ := strings.Cut(spelled.slot, "#")
		if _, declared := s.methods[method]; !declared {
			continue
		}
		owner, declared := s.types[spelled.owner]
		if !declared || !slices.ContainsFunc(owner.Constants, func(constant StringConstant) bool { return constant.Name == spelled.constant }) {
			continue
		}
		if !slices.Contains(vocabularies[spelled.slot], spelled.owner) {
			vocabularies[spelled.slot] = append(vocabularies[spelled.slot], spelled.owner)
		}
	}

	return vocabularies
}

// arrowsBeside is the references between namespaces this summary counts beyond the one given, as the count of
// references from each namespace to each other: what the rest of a program adds to a part's own arrows.
func (s *Summary) arrowsBeside(own *Summary) engine.ArrowCounts {
	counts := engine.ArrowCounts{}
	for held, count := range s.references {
		count -= own.references[held]
		if count <= 0 {
			continue
		}
		var homes []string
		for _, key := range strings.Split(held.reached, "\x00") {
			if home := s.homes[key]; home != "" && home != held.from && !slices.Contains(homes, home) {
				homes = append(homes, home)
			}
		}
		for _, home := range homes {
			counts[engine.Pair{From: held.from, To: home}] += count
		}
	}

	return counts
}
