package python

import (
	"maps"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

const (
	reassembled    = 3
	convertedShare = 0.5
)

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
				if slices.Contains(derived, call) {
					continue
				}
				derived = append(derived, call)
			}
		}
	}

	return derived
}

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

func slotOf(def Node, name string) string {
	return DeclarationOf(def) + "#" + name
}

func (n Node) MemberCalled() string {
	if callee := n.Callee(); callee.Kind() == "Attribute" {
		return callee.Name()
	}

	return ""
}

func (n Node) BuildsItsOwnClass(target Node) bool {
	class := n.EnclosingFunction().Parent()

	return target.Name() == "__init__" && class.Kind() == "ClassDef" && class.Initializer() == target
}

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

func (p *Program) couldTakeWhole(target Node, pieces []Node, root string) bool {
	named, ok := pieces[0].NameRead(root)
	if !ok {
		return false
	}
	class, typed := named.ResolvedClass()
	subject, held := p.ModuleOfClass(class)

	return typed && held && !p.WouldCloseACycle(p.ModuleOf(target), subject)
}

func (n Node) NameRead(name string) (Node, bool) {
	for _, part := range append([]Node{n}, n.Descendants()...) {
		if part.Kind() == "Name" && part.Name() == name {
			return part, true
		}
	}

	return Node{}, false
}

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

func (n Node) IsCallersOwn(conversion string) bool {
	class := n.EnclosingFunction().Parent()
	if class.Kind() != "ClassDef" {
		return false
	}
	module := class.Node().Symbol[:strings.LastIndex(class.Node().Symbol, ".")]

	return strings.HasPrefix(conversion+".", module+"."+class.Name()+".")
}

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

func (p *Program) LayerViolations(stack engine.LayerStack) []Node {
	var violations []Node
	for _, module := range p.modules {
		from := stack.LayerOf(module.Name)
		if from == "" || module.File.Match(0).IsTest() {
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
