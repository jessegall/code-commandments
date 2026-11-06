package csharp

var initializers = []string{"ArrayInitializerExpression", "CollectionInitializerExpression", "InitializerExpression"}

func (n Node) IsFieldDeclarator() bool {
	return n.Kind() == "VariableDeclarator" && !n.EnclosingFunction().Exists()
}

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

func (n Node) typeConstructed() string {
	for _, child := range n.All() {
		if child.IsTypeNode() {
			return child.Name()
		}
	}

	return ""
}

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

var membershipQuestions = []string{"Contains", "ContainsKey"}

func (n Node) AsksAboutOne(held string) bool {
	if n.Kind() != "InvocationExpression" {
		return false
	}

	for _, access := range n.All() {
		if access.Kind() != "SimpleMemberAccessExpression" {
			continue
		}

		asked, named := false, false
		for _, part := range access.All() {
			for _, question := range membershipQuestions {
				asked = asked || part.Name() == question
			}
			named = named || part.Name() == held
		}

		if asked && named {
			return true
		}
	}

	return false
}
