package engine

import (
	"crypto/sha1"
	"encoding/hex"
	"strings"
)

// HashRules is what a language says about fingerprinting its code: which nodes count at all, how much each one
// weighs, which expression is a local name, how a literal reads, and which nodes read whole. Every language's
// clone rules share the one fingerprint below through these answers.
type HashRules interface {
	// Counts says whether the node counts: a Python docstring runs nothing, so it never does.
	Counts(node Match) bool
	// Weight is what the node itself adds to a subtree's size, its children aside.
	Weight(node Match) int
	// IsName says whether the node is a local name, the thing normalising blanks.
	IsName(node Match) bool
	// Declares says whether the node declares its name, which normalising leaves out: two names for one body are
	// the same code.
	Declares(node Match) bool
	// IsCallee says whether the node is what a call calls, whose name normalising keeps: calling different
	// functions does different things.
	IsCallee(node Match) bool
	// Literal is how a literal reads, blanked when normalising if it is data; false when the node is no literal.
	Literal(node Match, normalize bool) (string, bool)
	// Leaf is how a node that reads whole reads, never walked or blanked: a TypeScript written type, whose names
	// say what the code is; false when the node is walked.
	Leaf(node Match) (string, bool)
}

// Unwrapping is what a language may add to its HashRules when a node is layout around another: C# braces around a
// single statement, since `if (x) { break; }` is `if (x) break;`. The node reads as the one it wraps, wherever it
// stands.
type Unwrapping interface {
	// Unwrap is the node this one reads as; false when it reads as itself.
	Unwrap(node Match) (Match, bool)
}

// unwrapped is the node as the rules read it: what it wraps, when it is layout around another.
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

// SyntaxHash is a formatting-blind fingerprint of the nodes, read from the tree and never from the source text,
// so spacing, comments and quote style do not count. Normalising also blanks local names and data literals, for
// type-2 clone detection, keeping what is called and which members are read.
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

// SyntaxFingerprint is one node's fingerprint unhashed: what a language's rules read a stand-in as when it must
// fingerprint exactly like the code it stands for, such as a local read as the expression it was assigned.
func SyntaxFingerprint(node Match, rules HashRules, normalize bool) string {
	return fingerprint(node, rules, normalize)
}

// SyntaxWeight is how many nodes make up the nodes' subtrees, as the language weighs them: the size a clone rule
// floors trivial bodies by.
func SyntaxWeight(nodes []Match, rules HashRules) int {
	weight := 0
	for _, node := range nodes {
		if node = unwrapped(node, rules); rules.Counts(node) {
			weight += rules.Weight(node) + SyntaxWeight(node.Children(), rules)
		}
	}

	return weight
}

// fingerprint is one node's fingerprint: its kind, its name unless normalising blanks it, its operator, modifiers
// and flags, its literal, and each counted child's slot and fingerprint. A node's own slot is its parent's to say,
// so the same expression reads alike wherever it stands.
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
	for _, child := range node.Children() {
		if inner := unwrapped(child, rules); rules.Counts(inner) {
			parts = append(parts, child.Node().Field+"="+fingerprint(inner, rules, normalize))
		}
	}

	return "(" + strings.Join(parts, "|") + ")"
}
