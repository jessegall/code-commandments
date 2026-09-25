package php

import (
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// hashRules is how PHP code is fingerprinted, as the PHP engine's StructuralHash reads it: every node counts and
// weighs one, a variable is the local name normalising blanks, a function-like's and a parameter's own name are
// left out when normalising, and a string or a number is data.
type hashRules struct{}

// Hashing is the PHP engine's rules for the shared syntax fingerprint.
var Hashing engine.HashRules = hashRules{}

func (hashRules) Counts(engine.Match) bool { return true }

func (hashRules) Weight(engine.Match) int { return 1 }

func (hashRules) IsName(node engine.Match) bool {
	return node.Kind() == "Expr_Variable" && node.Name() != ""
}

func (hashRules) Declares(node engine.Match) bool {
	named := Node{Match: node}

	return named.IsFunctionLike() || node.Kind() == "Param" || node.Node().Field == "name" && named.Up().IsFunctionLike()
}

func (hashRules) IsCallee(engine.Match) bool { return false }

func (hashRules) Literal(node engine.Match, normalize bool) (string, bool) {
	switch {
	case node.Kind() == "Scalar_String":
		if normalize {
			return "String_", true
		}
		text, _ := node.Text()

		return "String_:" + text, true
	case node.Kind() == "Scalar_Int" || node.Kind() == "Scalar_Float":
		if normalize {
			return "Num", true
		}
		text, _ := node.Text()

		return strings.TrimPrefix(node.Kind(), "Scalar_") + ":" + text, true
	}

	return "", false
}

func (hashRules) Leaf(engine.Match) (string, bool) { return "", false }

// StructuralHash is the node's fingerprint, formatting and position aside: two subtrees written alike hash alike.
func StructuralHash(node engine.Match) string {
	return engine.SyntaxHash([]engine.Match{node}, Hashing, false)
}

// NormalizedHash is the node's fingerprint with local names and data literals blanked: a type-2 clone hashes alike.
func NormalizedHash(node engine.Match) string {
	return engine.SyntaxHash([]engine.Match{node}, Hashing, true)
}
