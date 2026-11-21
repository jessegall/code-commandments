package php

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

var outsideSymbols = engine.Memoised(func(codebase *engine.Codebase) map[string]contract.OutsideSymbol {
	symbols := map[string]contract.OutsideSymbol{}
	if program, ok := codebase.Program(contract.PHP); ok {
		for _, symbol := range program.Symbols {
			symbols[strings.ToLower(strings.TrimLeft(symbol.Symbol, `\`))] = symbol
		}
	}

	return symbols
})

func (p *Program) outside(name string) (contract.OutsideSymbol, bool) {
	symbol, ok := outsideSymbols.Of(p.codebase)[strings.ToLower(strings.TrimLeft(name, `\`))]

	return symbol, ok
}

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

func (p *Program) HasTraitArrayReturnContract(class, method string) bool {
	seen := map[string]bool{}
	var requiresArray func(string) bool
	requiresArray = func(owner string) bool {
		if seen[owner] {
			return false
		}
		seen[owner] = true
		if declaration, found := p.Declaration(owner); found {
			if candidate, found := Method(declaration, method); found && candidate.HasModifier("abstract") &&
				Written(candidate.Node().Written).SimpleName() == "array" {
				return true
			}
		} else if symbol, found := p.outside(owner); found {
			for _, candidate := range symbol.Members {
				if candidate.Kind == "method" && strings.EqualFold(candidate.Name, method) &&
					slices.Contains(candidate.Modifiers, "abstract") && Written(candidate.Returns).SimpleName() == "array" {
					return true
				}
			}
		}
		return slices.ContainsFunc(p.traitsUsedBy(owner), requiresArray)
	}
	if slices.ContainsFunc(p.traitsUsedBy(class), requiresArray) {
		return true
	}

	usersChecked := map[string]bool{}
	var suppliesOverride func(string) bool
	suppliesOverride = func(trait string) bool {
		if usersChecked[trait] {
			return false
		}
		usersChecked[trait] = true
		for _, user := range p.UsersOfTrait(trait) {
			declaration, found := p.Declaration(user)
			if !found {
				continue
			}
			if _, shadows := Method(declaration, method); shadows {
				continue
			}
			if slices.ContainsFunc(p.traitsUsedBy(user), func(other string) bool {
				return !strings.EqualFold(other, trait) && p.declaresMethod(other, method, map[string]bool{})
			}) {
				continue
			}
			if p.OverridesMethod(user, method) || suppliesOverride(user) {
				return true
			}
		}
		return false
	}

	return suppliesOverride(class)
}

func (p *Program) traitsUsedBy(name string) []string {
	if declaration, found := p.Declaration(name); found {
		return traitsOf(declaration.Node())
	}
	symbol, _ := p.outside(name)
	return symbol.Uses
}

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
