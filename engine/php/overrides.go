package php

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// outsideSymbols is every declaration outside the scan the bridge read, by lower-cased name.
var outsideSymbols = Memoised(func(codebase *engine.Codebase) map[string]contract.OutsideSymbol {
	symbols := map[string]contract.OutsideSymbol{}
	if program, ok := codebase.Program(contract.PHP); ok {
		for _, symbol := range program.Symbols {
			symbols[strings.ToLower(strings.TrimLeft(symbol.Symbol, `\`))] = symbol
		}
	}

	return symbols
})

// outside is the declaration outside the scan the name refers to.
func (p *Program) outside(name string) (contract.OutsideSymbol, bool) {
	symbol, ok := outsideSymbols.Of(p.codebase)[strings.ToLower(strings.TrimLeft(name, `\`))]

	return symbol, ok
}

// OverridesMethod says whether an ancestor of the class — a parent or a contract, in the scan or outside it —
// declares the method, itself or through a trait, so the class's own method of that name answers to it.
func (p *Program) OverridesMethod(class, method string) bool {
	if class == "" || method == "" {
		return false
	}
	seen := map[string]bool{}
	var climb func(name string) bool
	climb = func(name string) bool {
		for _, ancestor := range p.directAncestors(name) {
			key := strings.ToLower(ancestor)
			if seen[key] {
				continue
			}
			seen[key] = true
			if p.declaresMethod(ancestor, method, map[string]bool{}) || climb(ancestor) {
				return true
			}
		}

		return false
	}

	return climb(strings.TrimLeft(class, `\`))
}

// directAncestors is the class's parent and the contracts it names itself.
func (p *Program) directAncestors(name string) []string {
	if _, declared := p.Declaration(name); declared {
		ancestors := slices.Clone(p.contracts[name])
		if parent, ok := p.parents[name]; ok {
			ancestors = append([]string{parent}, ancestors...)
		}

		return ancestors
	}
	symbol, _ := p.outside(name)

	return append(slices.Clone(symbol.Extends), symbol.Implements...)
}

// declaresMethod says whether the class-like declares the method, itself or through a trait it uses.
func (p *Program) declaresMethod(name, method string, seen map[string]bool) bool {
	key := strings.ToLower(strings.TrimLeft(name, `\`))
	if seen[key] {
		return false
	}
	seen[key] = true
	if declaration, declared := p.Declaration(name); declared {
		for _, own := range Methods(declaration) {
			if strings.EqualFold(own.Name(), method) {
				return true
			}
		}

		return slices.ContainsFunc(traitsOf(declaration.Node()), func(trait string) bool { return p.declaresMethod(trait, method, seen) })
	}
	symbol, ok := p.outside(name)
	if !ok {
		return false
	}
	for _, member := range symbol.Members {
		if member.Kind == "method" && strings.EqualFold(member.Name, method) {
			return true
		}
	}

	return slices.ContainsFunc(symbol.Uses, func(trait string) bool { return p.declaresMethod(trait, method, seen) })
}
