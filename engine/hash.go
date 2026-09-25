package engine

import (
	"crypto/sha1"
	"encoding/hex"
	"strings"
)

// HashRules is what a language says about its nodes when a subtree is fingerprinted: which nodes are names a
// shape ignores, how a literal reads, and which nodes are read whole as a leaf.
type HashRules interface {
	// IsName says whether the node is a name a shape reads as any name: a variable, a parameter.
	IsName(Match) bool
	// Literal is how a literal reads, its data blanked in a shape; false when the node is no literal.
	Literal(m Match, shape bool) (string, bool)
	// Leaf is how a node read whole reads, a written type for one; false when it is walked.
	Leaf(Match) (string, bool)
}

// Hash is the subtree's fingerprint: two subtrees share it when they are the same code, formatting and
// comments aside.
func Hash(m Match, rules HashRules) string {
	return fingerprint(canonical(m, rules, false))
}

// ShapeHash is the subtree's shape: two subtrees share it when they differ only in the names they use and
// the data their literals hold.
func ShapeHash(m Match, rules HashRules) string {
	return fingerprint(canonical(m, rules, true))
}

func canonical(m Match, rules HashRules, shape bool) string {
	if leaf, ok := rules.Leaf(m); ok {
		return leaf
	}
	if shape && rules.IsName(m) {
		return "id"
	}
	if literal, ok := rules.Literal(m, shape); ok {
		return literal
	}
	node := m.Node()
	parts := []string{node.Kind, node.Field, node.Operator, strings.Join(node.Modifiers, " "), strings.Join(node.Flags, " ")}
	// A declaration repeats its name child's name as its own; a shape that blanks the one blanks both.
	if !shape || !rules.IsName(m.Child("name")) {
		parts = append(parts, node.Name)
	}
	for _, child := range m.Children() {
		parts = append(parts, canonical(child, rules, shape))
	}

	return "(" + strings.Join(parts, "|") + ")"
}

func fingerprint(canonical string) string {
	sum := sha1.Sum([]byte(canonical))

	return hex.EncodeToString(sum[:])
}
