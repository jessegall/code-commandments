package engine

import (
	"crypto/sha1"
	"encoding/hex"
	"strings"
)

// HashRules is what a language says about fingerprinting its code: which nodes count at all, how much each one
// weighs, which expression is a local name, and how a literal reads.
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
}

// SyntaxHash is a formatting-blind fingerprint of the nodes, read from the tree and never from the source text,
// so spacing, comments and quote style do not count. Normalising also blanks local names and data literals, for
// type-2 clone detection, keeping what is called and which members are read.
func SyntaxHash(nodes []Match, rules HashRules, normalize bool) string {
	var hashed strings.Builder
	for _, node := range nodes {
		if rules.Counts(node) {
			hashed.WriteString(fingerprint(node, rules, normalize))
		}
	}
	sum := sha1.Sum([]byte(hashed.String()))

	return hex.EncodeToString(sum[:])
}

// SyntaxWeight is how many nodes make up the nodes' subtrees, as the language weighs them: the size a clone rule
// floors trivial bodies by.
func SyntaxWeight(nodes []Match, rules HashRules) int {
	weight := 0
	for _, node := range nodes {
		if rules.Counts(node) {
			weight += rules.Weight(node) + SyntaxWeight(node.Children(), rules)
		}
	}

	return weight
}

// fingerprint is one node's fingerprint: its kind and slot, its name unless normalising blanks it, its operator
// and flags, its literal, and its counted children's.
func fingerprint(node Match, rules HashRules, normalize bool) string {
	if normalize && rules.IsName(node) && !rules.IsCallee(node) {
		return "id"
	}
	if literal, ok := rules.Literal(node, normalize); ok {
		return literal
	}
	facts := node.Node()
	parts := []string{facts.Kind, facts.Field}
	if !normalize || !rules.Declares(node) {
		parts = append(parts, facts.Name)
	}
	parts = append(parts, facts.Operator, strings.Join(facts.Flags, " "))
	for _, child := range node.Children() {
		if rules.Counts(child) {
			parts = append(parts, fingerprint(child, rules, normalize))
		}
	}

	return "(" + strings.Join(parts, "|") + ")"
}
