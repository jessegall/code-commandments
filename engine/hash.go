package engine

import (
	"crypto/sha1"
	"encoding/hex"
	"strings"
)

// HashRules is what a language says about fingerprinting its code: which nodes count at all, how much each one weighs, which expression is a local name, how a literal reads, and which nodes read whole.
type HashRules interface {
	Counts(node Match) bool
	Weight(node Match) int
	IsName(node Match) bool
	Declares(node Match) bool
	IsCallee(node Match) bool
	Literal(node Match, normalize bool) (string, bool)
	Leaf(node Match) (string, bool)
}

// Unwrapping is what a language may add to its HashRules when a node is layout around another: C# braces around a single statement, since `if (x) { break; }` is `if (x) break;`.
type Unwrapping interface {
	Unwrap(node Match) (Match, bool)
}

// Marking is what a language may add to its HashRules when what a node is, not what it holds, tells two spellings apart: TypeScript's `a?.b` and `a.b`.
type Marking interface {
	Mark(node Match) string
}

func unwrapped(node Match, rules HashRules) Match {
	unwrapping, ok := rules.(Unwrapping)
	if !ok {
		return node
	}
	for {
		inner, ok := unwrapping.Unwrap(node)
		if !ok {
			return node
		}
		node = inner
	}
}

func SyntaxHash(nodes []Match, rules HashRules, normalize bool) string {
	var hashed strings.Builder
	for _, node := range nodes {
		if node = unwrapped(node, rules); rules.Counts(node) {
			hashed.WriteString(fingerprint(node, rules, normalize))
		}
	}
	sum := sha1.Sum([]byte(hashed.String()))

	return hex.EncodeToString(sum[:])
}

func SyntaxFingerprint(node Match, rules HashRules, normalize bool) string {
	return fingerprint(node, rules, normalize)
}

func SyntaxWeight(nodes []Match, rules HashRules) int {
	weight := 0
	for _, node := range nodes {
		if node = unwrapped(node, rules); rules.Counts(node) {
			weight += rules.Weight(node) + SyntaxWeight(node.Children(), rules)
		}
	}

	return weight
}

func fingerprint(node Match, rules HashRules, normalize bool) string {
	if leaf, ok := rules.Leaf(node); ok {
		return leaf
	}
	if normalize && rules.IsName(node) && !rules.IsCallee(node) {
		return "id"
	}
	if literal, ok := rules.Literal(node, normalize); ok {
		return literal
	}
	facts := node.Node()
	parts := []string{facts.Kind}
	if !normalize || !rules.Declares(node) {
		parts = append(parts, facts.Name)
	}
	parts = append(parts, facts.Operator, strings.Join(facts.Modifiers, " "), strings.Join(facts.Flags, " "))
	if marking, ok := rules.(Marking); ok {
		if mark := marking.Mark(node); mark != "" {
			parts = append(parts, mark)
		}
	}
	for _, child := range node.Children() {
		if inner := unwrapped(child, rules); rules.Counts(inner) {
			parts = append(parts, child.Node().Field+"="+fingerprint(inner, rules, normalize))
		}
	}

	return "(" + strings.Join(parts, "|") + ")"
}
