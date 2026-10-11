package csharp

// initializers are the nodes a collection written out in the source is held in, however it was built: an array's
// braces, and the braces of a collection built through a constructor.
var initializers = []string{"ArrayInitializerExpression", "CollectionInitializerExpression", "InitializerExpression"}

// IsFieldDeclarator says whether the node declares a field of a type rather than a local of a method.
func (n Node) IsFieldDeclarator() bool {
	return n.Kind() == "VariableDeclarator" && !n.EnclosingFunction().Exists()
}

// declared is the collection the field is declared with, written out: the braces themselves, wherever they sit —
// an array's, or those of a set or list built through a constructor. Every node beneath is walked through All,
// since braces are an expression and this package's Children and Descendants keep expressions out.
func (n Node) declared() Node {
	for _, below := range n.All() {
		for _, kind := range initializers {
			if below.Kind() == kind {
				return below
			}
		}

		if found := below.declared(); found.Exists() {
			return found
		}
	}

	return Node{}
}

// StringMembers is every member of the declared collection when ALL of them are string literals: the shape a
// collection of names takes. Nothing where it holds anything else.
func (n Node) StringMembers() []string {
	var names []string

	for _, member := range n.declared().All() {
		text := member.Text()
		if text == "" {
			return nil
		}
		names = append(names, text)
	}

	return names
}

// InstanceNames is the name each member of the declared collection is constructed with — its first argument,
// where that is a string literal — when every member constructs the SAME type: the shape a registry of a type's
// instances takes. Nothing where the members are not all such constructions, or fewer than two.
func (n Node) InstanceNames() (string, []string) {
	members := n.declared().All()
	if len(members) < 2 {
		return "", nil
	}

	constructed, names := "", []string{}
	for _, member := range members {
		if member.Kind() != "ObjectCreationExpression" {
			return "", nil
		}

		built := member
		if named := built.typeConstructed(); constructed != "" && named != constructed {
			return "", nil
		} else if named == "" {
			return "", nil
		} else {
			constructed = named
		}

		first := built.firstArgumentText()
		if first == "" {
			return "", nil
		}
		names = append(names, first)
	}

	return constructed, names
}

// typeConstructed is the name of the type a construction names.
func (n Node) typeConstructed() string {
	for _, child := range n.All() {
		if child.IsTypeNode() {
			return child.Name()
		}
	}

	return ""
}

// firstArgumentText is the string a construction passes first, empty where it passes none or passes something
// that is not written out.
func (n Node) firstArgumentText() string {
	for _, child := range n.All() {
		if child.Kind() != "ArgumentList" {
			continue
		}

		for _, argument := range child.All() {
			for _, written := range append([]Node{argument}, argument.All()...) {
				if text := written.Text(); text != "" {
					return text
				}
			}

			return ""
		}
	}

	return ""
}
