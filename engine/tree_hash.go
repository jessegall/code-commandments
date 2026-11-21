package engine

// TreeHashing fingerprints code of every language through the generic tree alone: every node counts and weighs one;
// normalising, a local name reads as a name, a declared name is left out, a called or member name stays, and a
// literal reads as its kind of literal.
var TreeHashing HashRules = treeHashing{}

type treeHashing struct{}

func (treeHashing) Counts(Match) bool { return true }

func (treeHashing) Weight(Match) int { return 1 }

func (treeHashing) IsName(node Match) bool {
	return node.Kind() == "reference" && node.Name() != ""
}

func (treeHashing) Declares(node Match) bool {
	return node.Kind() == "binding" || node.Is(Function)
}

func (treeHashing) IsCallee(node Match) bool {
	role := node.Node().InRole()

	return role == "callee" || role == "member"
}

func (treeHashing) Literal(node Match, normalize bool) (string, bool) {
	if node.Kind() != "literal" {
		return "", false
	}
	if normalize {
		return "literal:" + node.LiteralKind(), true
	}
	text, _ := node.Text()

	return "literal:" + node.LiteralKind() + ":" + text, true
}

func (treeHashing) Leaf(Match) (string, bool) { return "", false }
