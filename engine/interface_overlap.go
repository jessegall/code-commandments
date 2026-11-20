package engine

import (
	"slices"
)

// InterfaceReuse identifies the existing interface the declaration could reuse.
type InterfaceReuse struct {
	Contract Match
	Exact    bool
}

// interfaceMethods holds a declaration and the complete signatures it offers.
type interfaceMethods struct {
	declaration Match
	methods     []string
}

type interfaceIndex struct {
	declarations map[string]interfaceMethods
	byMethod     map[string][]interfaceMethods
}

func interfacesOf(codebase *Codebase) interfaceIndex {
	return Analysis(codebase, "interface-methods", func(codebase *Codebase) interfaceIndex {
		index := interfaceIndex{map[string]interfaceMethods{}, map[string][]interfaceMethods{}}
		for _, declaration := range codebase.Reporting().WhereTypeDeclaration().Where(Match.IsInterfaceType).Reject(Match.IsTest).Get() {
			read := declaration.grammar().InterfaceSignatures
			if read == nil || declaration.Identity() == "" {
				continue
			}
			methods := read(declaration)
			if len(methods) < 2 {
				continue
			}
			one := interfaceMethods{declaration, methods}
			index.declarations[declaration.Identity()] = one
			for _, method := range methods {
				index.byMethod[method] = append(index.byMethod[method], one)
			}
		}
		return index
	})
}

func (m Match) ReusableInterface(layers LayerStack, public map[string]bool) InterfaceReuse {
	if m.IsTest() || m.IsInDependency() || public[m.Identity()] {
		return InterfaceReuse{}
	}
	index := interfacesOf(m.Codebase())
	current, known := index.declarations[m.Identity()]
	if !known {
		return InterfaceReuse{}
	}
	var best interfaceMethods
	seen := map[string]bool{m.Identity(): true}
	for _, method := range current.methods {
		for _, candidate := range index.byMethod[method] {
			identity := candidate.declaration.Identity()
			if seen[identity] {
				continue
			}
			seen[identity] = true
			count := len(candidate.methods)
			if count > len(current.methods) || count*2 <= len(current.methods) ||
				candidate.declaration.Source().Language() != m.Source().Language() ||
				!layers.canReuseInterface(m, candidate.declaration, public[identity], count == len(current.methods)) ||
				slices.ContainsFunc(candidate.methods, func(method string) bool { return !slices.Contains(current.methods, method) }) ||
				separateContractRoles(m, candidate.declaration) {
				continue
			}
			if count > len(best.methods) || count == len(best.methods) && identity < best.declaration.Identity() {
				best = candidate
			}
		}
	}
	return InterfaceReuse{best.declaration, len(best.methods) == len(current.methods)}
}

func separateContractRoles(left, right Match) bool {
	uses := left.grammar().UsesContract
	if uses == nil {
		return false
	}
	types, known := left.Types()
	if !known {
		return false
	}
	leftImplementations := projectImplementers(types, left.Identity())
	rightImplementations := projectImplementers(types, right.Identity())
	if len(leftImplementations) == 0 || len(rightImplementations) == 0 {
		return false
	}
	for _, implementations := range []struct {
		own   []Match
		other Match
	}{{leftImplementations, right}, {rightImplementations, left}} {
		if slices.ContainsFunc(implementations.own, func(one Match) bool {
			return !slices.Contains(types.Contracts(one.Identity()), implementations.other.Identity()) && uses(one, implementations.other)
		}) {
			return true
		}
	}

	return false
}

func projectImplementers(types TypeSystem, symbol string) []Match {
	return slices.DeleteFunc(Implementers(types, symbol), func(implementation Match) bool {
		return implementation.IsInterfaceType() || implementation.IsTest() || implementation.IsInDependency()
	})
}

func (s LayerStack) canReuseInterface(from, target Match, published, exact bool) bool {
	source, destination := from.Namespace(), target.Namespace()
	if s.separator == "" || source == "" || destination == "" {
		return false
	}
	equalNamespace := s.within(source, destination) && s.within(destination, source)
	if !equalNamespace && s.within(destination, source) {
		return false
	}
	if visible := target.grammar().InterfaceVisible; visible != nil && !visible(target, from) {
		return false
	}
	sourceLayer, destinationLayer := s.LayerOf(source), s.LayerOf(destination)
	if sourceLayer != destinationLayer {
		if sourceLayer == "" || destinationLayer == "" || s.within(destinationLayer, sourceLayer) ||
			!s.allowsContractLayer(sourceLayer, destination, destinationLayer) {
			return false
		}
		return s.within(sourceLayer, destinationLayer) || !s.allowsContractLayer(destinationLayer, source, sourceLayer)
	}
	if equalNamespace {
		return !exact || published || target.Identity() < from.Identity()
	}

	return s.within(source, destination) || published && sourceLayer != ""
}

func (s LayerStack) allowsContractLayer(from, namespace, layer string) bool {
	return slices.ContainsFunc(s.layers[from], func(allowed string) bool {
		return allowed != "" && s.LayerOf(allowed) == layer && s.within(namespace, allowed)
	})
}
