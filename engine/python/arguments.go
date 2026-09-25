package python

import (
	"maps"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

const (
	// reassembled is how many pieces of one object a call must hand a def before it could take the object whole.
	reassembled = 3
	// convertedShare is the share of a parameter's callers that must convert what they hand it the same way.
	convertedShare = 0.5
)

// DerivedArgumentCalls is every evaluated call that fills a parameter with a piece of an object it also hands over,
// or with one of three or more pieces of one object, when every call of that def fills the parameter so: the def
// should take the object and read the piece itself. A def a call of the same member name cannot be resolved to is
// left alone, since that call may fill it whole.
func (p *Program) DerivedArgumentCalls() []Node {
	redundant := map[string]map[string][]Node{}
	supplied := map[string]int{}
	unresolved := map[string]bool{}
	var slots []string
	for _, call := range p.evaluatedCalls() {
		target, resolved := p.TargetOf(call)
		bound, bindable := p.ArgumentsAt(call)
		if !resolved || !bindable {
			unresolved[call.MemberCalled()] = true
			continue
		}
		if call.BuildsItsOwnClass(target) {
			continue
		}
		for name := range bound {
			supplied[slotOf(target, name)]++
		}
		for _, name := range p.RedundantParameters(call, target, bound) {
			key := slotOf(target, name)
			if redundant[key] == nil {
				redundant[key] = map[string][]Node{}
				slots = append(slots, key)
			}
			redundant[key][target.Name()] = append(redundant[key][target.Name()], call)
		}
	}
	var derived []Node
	for _, key := range slots {
		for name, calls := range redundant[key] {
			if len(calls) != supplied[key] || unresolved[name] {
				continue
			}
			for _, call := range calls {
				if !slices.Contains(derived, call) {
					derived = append(derived, call)
				}
			}
		}
	}

	return derived
}

// ConvertedArgumentCalls is every evaluated call that fills a scalar parameter through one conversion, when half or
// more of the calls filling that parameter convert what they hand it the same way: the parameter should take what
// they convert.
func (p *Program) ConvertedArgumentCalls() []Node {
	calls := p.evaluatedCalls()
	supplied := map[string]int{}
	for _, call := range calls {
		target, _ := p.TargetOf(call)
		for name := range p.ScalarArguments(call) {
			supplied[slotOf(target, name)]++
		}
	}
	var converted []Node
	key := func(match engine.Match) (string, bool) { return p.ConversionKey(Node{match}) }
	for _, bucket := range engine.RecurringBuckets(nodeMatches(calls), key, 2) {
		read, _ := key(bucket[0])
		if float64(len(bucket))/float64(supplied[strings.SplitN(read, "=", 2)[0]]) >= convertedShare {
			converted = append(converted, nodes(bucket)...)
		}
	}

	return converted
}

// evaluatedCalls is every call the program evaluates, in module and pre-order.
func (p *Program) evaluatedCalls() []Node {
	var calls []Node
	for _, module := range p.modules {
		for _, node := range module.Nodes() {
			if node.IsCall() && node.IsEvaluated() {
				calls = append(calls, node)
			}
		}
	}

	return calls
}

// slotOf is where a parameter of the def is declared: `declaration#parameter`.
func slotOf(def Node, name string) string {
	return DeclarationOf(def) + "#" + name
}

// MemberCalled is the member name a call calls through an attribute; empty for a call of a plain name.
func (n Node) MemberCalled() string {
	if callee := n.Callee(); callee.Kind() == "Attribute" {
		return callee.Name()
	}

	return ""
}

// BuildsItsOwnClass says whether the call is its own class's __init__, called from inside the class.
func (n Node) BuildsItsOwnClass(target Node) bool {
	class := n.EnclosingFunction().Parent()

	return target.Name() == "__init__" && class.Kind() == "ClassDef" && class.Initializer() == target
}

// RedundantParameters is the parameters the call fills with a piece of an object it also hands whole, or with one
// of three or more pieces of one object the def could take whole.
func (p *Program) RedundantParameters(call, target Node, bound map[string]Node) []string {
	parameters := map[string]Node{}
	for _, parameter := range target.Parameters() {
		parameters[parameter.Name()] = parameter
	}
	for name := range bound {
		if parameters[name].TakesAnything() {
			return nil
		}
	}
	receiver := call.ReceiverName()
	whole := map[string]bool{}
	pieces := map[string]map[string]Node{}
	for name, argument := range bound {
		if argument.Kind() == "Name" && argument.Name() != receiver {
			whole[argument.Name()] = true
			continue
		}
		if root := argument.ProjectionRoot(); root != "" && root != receiver && parameters[name].IsScalarParameter() {
			if pieces[root] == nil {
				pieces[root] = map[string]Node{}
			}
			pieces[root][name] = argument
		}
	}
	var names []string
	for root, arguments := range pieces {
		if !whole[root] && (len(arguments) < reassembled || !p.couldTakeWhole(target, slices.Collect(maps.Values(arguments)), root)) {
			continue
		}
		names = append(names, slices.Collect(maps.Keys(arguments))...)
	}

	return names
}

// couldTakeWhole says whether the def could take the object the pieces come from: mypy types the name they are read
// off as a class of the program, and importing that class's module into the def's would close no cycle.
func (p *Program) couldTakeWhole(target Node, pieces []Node, root string) bool {
	named, ok := pieces[0].NameRead(root)
	if !ok {
		return false
	}
	class, typed := named.ResolvedClass()
	subject, held := p.ModuleOfClass(class)

	return typed && held && !p.WouldCloseACycle(p.ModuleOf(target), subject)
}

// NameRead is the first place the expression reads the name.
func (n Node) NameRead(name string) (Node, bool) {
	for _, part := range append([]Node{n}, n.Descendants()...) {
		if part.Kind() == "Name" && part.Name() == name {
			return part, true
		}
	}

	return Node{}, false
}

// ScalarArguments is what the call hands each scalar parameter of the def it reaches.
func (p *Program) ScalarArguments(call Node) map[string]Node {
	target, resolved := p.TargetOf(call)
	bound, bindable := p.ArgumentsAt(call)
	if !resolved || !bindable {
		return nil
	}
	scalars := map[string]Node{}
	for _, parameter := range target.Parameters() {
		if argument, ok := bound[parameter.Name()]; ok && parameter.IsScalarParameter() {
			scalars[parameter.Name()] = argument
		}
	}

	return scalars
}

// ConversionKey is the first scalar parameter the call fills, in the order it writes its arguments, through a
// conversion other than to its own class, with that conversion: `declaration#parameter=Class.method`.
func (p *Program) ConversionKey(call Node) (string, bool) {
	target, _ := p.TargetOf(call)
	bound := p.ScalarArguments(call)
	names := slices.Collect(maps.Keys(bound))
	slices.SortFunc(names, func(a, b string) int { return bound[a].Node().Span.Start - bound[b].Node().Span.Start })
	for _, name := range names {
		if conversion, ok := call.ConversionIn(bound[name]); ok && !call.IsCallersOwn(conversion) {
			return slotOf(target, name) + "=" + conversion, true
		}
	}

	return "", false
}

// IsCallersOwn says whether the conversion belongs to the calling class itself, or a class nested in it.
func (n Node) IsCallersOwn(conversion string) bool {
	class := n.EnclosingFunction().Parent()
	if class.Kind() != "ClassDef" {
		return false
	}
	module := class.Node().Symbol[:strings.LastIndex(class.Node().Symbol, ".")]

	return strings.HasPrefix(conversion+".", module+"."+class.Name()+".")
}

// CallersAllAskOneObject says whether two or more calls reach the method, one of them from outside its class, and
// every one computes every argument from one and the same class of object: the method should take that object.
func (p *Program) CallersAllAskOneObject(method Node, minimum int) bool {
	class := method.Parent()
	callers := p.CallersOf(method)
	var subjects []string
	fromOutside := false
	for _, call := range callers {
		subject, ok := call.ArgumentSubjectType()
		if !ok {
			return false
		}
		if !slices.Contains(subjects, subject) {
			subjects = append(subjects, subject)
		}
		fromOutside = fromOutside || call.EnclosingFunction().Parent() != class
	}

	return fromOutside && len(callers) >= minimum && len(subjects) == 1
}

// NamedCallKey is the call's target and the shape of what each of its keywords builds, when one of them builds
// something and the target takes **kwargs: two calls with one key make the same call.
func (p *Program) NamedCallKey(call Node) (string, bool) {
	keywords := slices.DeleteFunc(call.Keywords(), func(keyword Node) bool { return keyword.Name() == "" })
	target, ok := p.TargetOf(call)
	if !ok || !target.TakesKeywordRest() || !slices.ContainsFunc(keywords, func(keyword Node) bool { return keyword.Child("value").IsConstruction() }) {
		return "", false
	}
	var slots []string
	for _, keyword := range keywords {
		slots = append(slots, keyword.Name()+"="+keyword.Child("value").ConstructionShape())
	}
	slices.Sort(slots)

	return DeclarationOf(target) + "#" + strings.Join(slots, ","), true
}

// PhantomNullableFields is the declaration of each optional field of the class nothing sets back to None that one
// or more reads assume is there and none guards: optional only on paper.
func (p *Program) PhantomNullableFields(class Node) []Node {
	var fields []Node
	for _, field := range class.FieldNames() {
		annotation, ok := class.AttributeAnnotation(field)
		if _, optional := annotation.OptionalOf(); !ok || !optional || class.ResetsToNone(field) {
			continue
		}
		verdict := p.AttributeFlow(class, field)
		if declaration := class.FieldDeclaration(field); verdict.Assume >= 1 && verdict.Guard == 0 && declaration.Exists() {
			fields = append(fields, declaration)
		}
	}

	return fields
}

// UnnamedVocabularyLiterals is each string literal the call hands a parameter that other calls fill with a named
// constant holding the same string.
func (p *Program) UnnamedVocabularyLiterals(call Node) []Node {
	var literals []Node
	for _, argument := range append(call.Arguments(), call.Keywords()...) {
		literal := argument.argumentValue()
		if _, named := p.ConstantFor(call, literal); named && literal.Node().Literal == "string" {
			literals = append(literals, literal)
		}
	}

	return literals
}

// LayerViolations is the first import each module makes into each module its declared layer may not use.
func (p *Program) LayerViolations(stack engine.LayerStack) []Node {
	var violations []Node
	for _, module := range p.modules {
		from := stack.LayerOf(module.Name)
		if from == "" {
			continue
		}
		reached := map[string]bool{}
		for _, imported := range module.Imports() {
			target := imported.Module.Name
			if stack.LayerOf(target) == "" || stack.MayReference(from, target) || reached[target] {
				continue
			}
			reached[target] = true
			violations = append(violations, imported.Statement)
		}
	}

	return violations
}
