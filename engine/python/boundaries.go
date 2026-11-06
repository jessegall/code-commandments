package python

import "slices"

func (p *Program) ReadsFeedOneConstruction(read Node) bool {
	base, keyed := read.StringKeyBase()
	function := read.EnclosingFunction()
	if !keyed || base.Kind() != "Name" || !function.Exists() {
		return false
	}

	built, one := p.oneDeclaredConstruction(function)
	if !one {
		return false
	}

	handed := argumentNames(built)
	for _, held := range function.Descendants() {
		if held.Kind() == "Name" && held.Name() == base.Name() && !held.readOrBoundAsTheMapping() {
			return false
		}
		if !held.readsMapping(base.Name()) || held.sitsIn(built) {
			continue
		}
		if bound := held.boundTo(); bound != "" && slices.Contains(handed, bound) {
			continue
		}

		return false
	}

	return true
}

func (n Node) readOrBoundAsTheMapping() bool {
	for held := n; held.Exists(); held = held.Parent() {
		if held.Kind() == "arg" || held.readsMapping(n.Name()) {
			return true
		}
		if targets := held.ChildrenIn("targets"); slices.ContainsFunc(targets, func(target Node) bool { return target.Node() == n.Node() }) {
			return true
		}
		if held.Kind() == "FunctionDef" || held.Kind() == "AsyncFunctionDef" {
			return false
		}
	}

	return false
}

func (p *Program) oneDeclaredConstruction(function Node) (Node, bool) {
	var built Node
	for _, held := range function.Descendants() {
		if !held.IsCall() || !p.DeclaresClass(held.Callee().DottedName()) {
			continue
		}
		if built.Exists() {
			return Node{}, false
		}
		built = held
	}

	return built, built.Exists()
}

func (n Node) readsMapping(name string) bool {
	if base, keyed := n.StringKeyBase(); keyed {
		return base.Kind() == "Name" && base.Name() == name
	}
	if n.Kind() != "Compare" {
		return false
	}

	return slices.ContainsFunc(n.ChildrenIn("comparators"), func(side Node) bool {
		return side.Kind() == "Name" && side.Name() == name
	})
}

func (n Node) sitsIn(other Node) bool {
	for held := n.Match; held.Exists(); held = held.Parent() {
		if held.Node() == other.Node() {
			return true
		}
	}

	return false
}

func (n Node) boundTo() string {
	for held := n; held.Exists(); held = held.Parent() {
		if held.Kind() != "Assign" && held.Kind() != "AnnAssign" {
			continue
		}
		targets := held.ChildrenIn("targets")
		if held.Kind() == "AnnAssign" {
			targets = []Node{held.Child("target")}
		}
		if len(targets) == 1 && targets[0].Kind() == "Name" {
			return targets[0].Name()
		}

		return ""
	}

	return ""
}

func argumentNames(call Node) []string {
	var handed []string
	for _, argument := range call.Arguments() {
		if argument.Kind() == "Name" {
			handed = append(handed, argument.Name())
		}
	}
	for _, keyword := range call.Keywords() {
		if value := keyword.Child("value"); value.Kind() == "Name" {
			handed = append(handed, value.Name())
		}
	}

	return handed
}
