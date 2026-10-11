package python

// IsModuleLevelAssign says whether the assignment stands at the module's own level, not inside a def or a class.
func (n Node) IsModuleLevelAssign() bool {
	return n.Kind() == "Assign" && n.Parent().Kind() == "Module"
}

// AssignedName is the single name an assignment binds, empty where it binds none or several.
func (n Node) AssignedName() string {
	if !n.IsModuleLevelAssign() {
		return ""
	}

	children := n.Children()
	if len(children) != 2 || children[0].Kind() != "Name" {
		return ""
	}

	return children[0].Name()
}

// assigned is the value an assignment binds.
func (n Node) assigned() Node {
	children := n.Children()
	if len(children) != 2 {
		return Node{}
	}

	return children[1]
}

// StringMembers is every member of an assigned set, tuple or list when ALL of them are string literals: the
// shape a collection of names takes. Nothing where the value is not one of those, or holds anything else.
func (n Node) StringMembers() []string {
	value := n.assigned()
	if !isSequence(value.Kind()) {
		return nil
	}

	var names []string
	for _, member := range value.Children() {
		text, literal := member.Text()
		if member.Kind() != "Constant" || !literal || text == "" {
			return nil
		}
		names = append(names, text)
	}

	return names
}

// InstanceNames is the name each member of an assigned sequence is constructed with, when every member calls
// the SAME thing and names itself in its first argument or a `name=` keyword: the shape a registry of a type's
// instances takes. Nothing where the members are not all such calls, or fewer than two.
func (n Node) InstanceNames() (string, []string) {
	value := n.assigned()
	if !isSequence(value.Kind()) || len(value.Children()) < 2 {
		return "", nil
	}

	constructed, names := "", []string{}
	for _, member := range value.Children() {
		call := member
		if !call.IsCall() {
			return "", nil
		}

		callee := call.Callee().DottedName()
		if constructed != "" && callee != constructed {
			return "", nil
		}
		constructed = callee

		named := call.named()
		if named == "" {
			return "", nil
		}
		names = append(names, named)
	}

	return constructed, names
}

// named is the name a construction gives its instance: its first positional argument, else a `name=` keyword,
// where that argument is a string literal.
func (n Node) named() string {
	if arguments := n.Arguments(); len(arguments) > 0 {
		if text, literal := arguments[0].Text(); literal && arguments[0].Kind() == "Constant" {
			return text
		}
	}

	keyword := n.Keyword("name")
	if text, literal := keyword.Text(); literal && keyword.Kind() == "Constant" {
		return text
	}

	return ""
}

// isSequence says whether the kind is a set, tuple or list written out in the source.
func isSequence(kind string) bool {
	return kind == "Set" || kind == "Tuple" || kind == "List"
}
