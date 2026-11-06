package python

import "slices"

func (n Node) IsModuleLevelAssign() bool {
	return n.Kind() == "Assign" && n.Parent().Kind() == "Module"
}

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

var builders = []string{"set", "frozenset", "tuple", "list"}

func (n Node) assigned() Node {
	children := n.Children()
	if len(children) != 2 {
		return Node{}
	}

	value := children[1]
	if !value.IsCall() || !slices.Contains(builders, value.Callee().DottedName()) {
		return value
	}

	if arguments := value.Arguments(); len(arguments) == 1 {
		return arguments[0]
	}

	return value
}

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

func isSequence(kind string) bool {
	return kind == "Set" || kind == "Tuple" || kind == "List"
}
