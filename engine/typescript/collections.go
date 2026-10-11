package typescript

// IsModuleLevelDeclaration says whether the variable is declared at the module's own level, not inside a function.
func (n Node) IsModuleLevelDeclaration() bool {
	return n.Kind() == "VariableDeclaration" && !n.EnclosingFunction().Exists()
}

// declared is the value the variable is declared with, through a `new Set([…])` or `new Map([…])` to the array
// written inside it: what a module writes a collection of names as.
func (n Node) declared() Node {
	for _, child := range n.Children() {
		if child.Kind() == "ArrayLiteralExpression" {
			return Node{Match: child}
		}

		if child.Kind() == "NewExpression" {
			for _, held := range child.Children() {
				if held.Kind() == "ArrayLiteralExpression" {
					return Node{Match: held}
				}
			}
		}
	}

	return Node{}
}

// StringMembers is every member of the declared collection when ALL of them are string literals: the shape a
// collection of names takes. Nothing where the value is not written out, or holds anything else.
func (n Node) StringMembers() []string {
	var names []string

	for _, member := range n.declared().Children() {
		text, literal := member.Text()
		if member.Kind() != "StringLiteral" || !literal || text == "" {
			return nil
		}
		names = append(names, text)
	}

	return names
}

// KeyedInstanceNames is, for each property every object of the declared collection carries as a string, the value
// each gives it: the ways a registry of objects names its instances, found rather than assumed, so no key has to
// be spelled out here. Nothing where the members are not all objects, or fewer than two.
func (n Node) KeyedInstanceNames() map[string][]string {
	objects := n.declared().Children()
	if len(objects) < 2 {
		return nil
	}

	keyed := map[string][]string{}
	for _, object := range objects {
		if object.Kind() != "ObjectLiteralExpression" {
			return nil
		}

		for _, property := range object.Children() {
			value := property.Child("initializer")
			text, literal := value.Text()

			if property.Kind() != "PropertyAssignment" || !literal || text == "" {
				continue
			}
			keyed[property.Name()] = append(keyed[property.Name()], text)
		}
	}

	for key, values := range keyed {
		if len(values) != len(objects) {
			delete(keyed, key)
		}
	}

	return keyed
}
