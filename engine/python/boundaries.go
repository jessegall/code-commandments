package python

import "slices"

// ReadsFeedOneConstruction says whether every read of the mapping this one reads ends up in ONE construction of a
// class the project declares. That is the boundary conversion the rule asks for — loose data off the wire becoming
// a value object — and flagging it would flag the fix. A mapping read in several places, read and then handed on as
// a mapping, or read into no declared type at all, is the sin it was written for.
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
		// The mapping itself must go nowhere else: one that is read key by key AND handed on, returned or stored
		// is not being converted, it is being passed around, which is the sin.
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

// readOrBoundAsTheMapping says whether this reading of the mapping's name is a read of what it holds, or the line
// that binds the mapping itself: anything else hands the mapping on.
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

// oneDeclaredConstruction is the single construction of a class the project declares in the function, and whether
// there is exactly one: two of them are no longer one value built out of the data.
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

// readsMapping says whether the node reads the mapping by a key or asks whether it holds one: `bag["k"]`,
// `bag.get("k")` and `"k" in bag` are each a read of it.
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

// sitsIn says whether the node stands inside the other.
func (n Node) sitsIn(other Node) bool {
	for held := n.Match; held.Exists(); held = held.Parent() {
		if held.Node() == other.Node() {
			return true
		}
	}

	return false
}

// boundTo is the name the statement the node sits in binds, walking out through whatever expression holds it:
// `realm = bag["realm"].encode()` binds realm, and so does the conditional form.
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

// argumentNames are the names the call is handed, positional and keyword alike.
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
