package rule

import (
	"bytes"
	"maps"
	"slices"
	"strconv"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// The operators that walk the tree and compare its shapes.
func init() {
	define(map[string]operator{
		"ancestors":      {operands: []valueType{node}, gives: list, says: "every node above the node, nearest first, up to the file, across functions"},
		"enclosing":      {operands: []valueType{node, text, text}, optional: 1, gives: node, says: "the nearest node above that is of the kind or category named, across functions; none when nothing above is, or when a node of the stop kind or category comes first"},
		"descendants":    {operands: []valueType{node, text}, optional: 1, gives: list, says: "every node under the node in source order, into the functions and types written inside it, only those of the kind or category named when one is; unknown under a node the parser recovered from"},
		"ownDescendants": {operands: []valueType{node, text}, optional: 1, gives: list, says: "every node under the node, leaving out what a function, type or comprehension written inside it holds, only those of the kind or category named when one is; empty for no node, unknown for a node the parser recovered from"},
		"children":       {operands: []valueType{node}, gives: list, says: "every node the node holds directly, in source order"},
		"childrenIn":     {operands: []valueType{node, text}, gives: list, says: "the nodes the role named holds; unknown for a role the node's kind does not have, or one the parser recovered from"},
		"after":          {operands: []valueType{node}, gives: list, says: "the nodes after the node in the same role of its parent, in order"},
		"next":           {operands: []valueType{node}, gives: node, says: "the node right after the node in the same role of its parent; none at the end"},
		"previous":       {operands: []valueType{node}, gives: node, says: "the node right before the node in the same role of its parent; none at the start"},
		"follow":         {operands: []valueType{node, text, text}, optional: 1, gives: list, says: "the node and each node its role or link holds in turn, while it is of the kind or category named when one is: an if with its else-ifs, a call with its receivers"},
		"leaves":         {operands: []valueType{node, text, text}, optional: 1, gives: list, says: "what a run of nested nodes of the kind named holds that is not of that kind, only through the role named when one is: the parts of a && b && c"},
		"commonAncestor": {operands: []valueType{node, node}, gives: node, says: "the nearest node that holds both nodes, or is one of them; unknown for nodes of two files"},
		"childToward":    {operands: []valueType{node, node}, gives: node, says: "the node the ancestor holds directly on the way down to the node, whose role says which arm it lies in; none when the first is not above the second"},
		"within":         {operands: []valueType{node, node}, gives: truth, says: "whether the first node is the second or lies under it"},
		"isElseIf":       {operands: []valueType{node}, gives: truth, says: "whether an if continues another as its else-if: else if, elif, elseif"},
		"isSelf":         {operands: []valueType{node}, gives: truth, says: "whether a node is the instance the method runs on: this, self, $this, or a Go method's receiver"},
		"selfMember":     {operands: []valueType{node}, gives: node, says: "the member declaration a node reads or writes through the instance the method runs on; none when it reads none, unknown when the member is not resolved"},
		"receiverChain":  {operands: []valueType{node}, gives: list, says: "the links of a chain a.b().c, the root first, each a record of node, member, owner (the type declaring the member) and nullConditional"},
		"consumer":       {operands: []valueType{node}, gives: anyValue, says: "how an expression's value is used, as a record of use and node: discarded, returned, assigned, argument, receiver, null-safe-receiver, called, condition, compared-to-nothing, coalesced, asserted-present, thrown, yielded or operand"},
		"accessMode":     {operands: []valueType{node}, gives: text, says: "whether a reference reads, writes, or reads and writes the name: read, write or readwrite"},
		"freeVariables":  {operands: []valueType{node}, gives: list, says: "the bindings a function reads or writes that are declared outside it, each once"},
		"declaredIn":     {operands: []valueType{node}, gives: list, says: "the bindings written directly in a node, not inside a block, function, type, loop, handler or comprehension written in it"},
		"fingerprint":    {operands: []valueType{node, text, list}, optional: 1, gives: text, says: "a subtree's structure hashed, in a mode: exact, normalized, renamed, shape, calls, signature or text; with the options literals (a literal read as its kind) and commutative (operands of an order-free operator sorted); unknown where a mode cannot be taken"},
		"equivalent":     {operands: []valueType{node, node, text}, gives: truth, says: "whether two subtrees are the same in the fingerprint mode named"},
		"matchesShape":   {operands: []valueType{node, arms}, gives: anyValue, says: "the captures of a pattern the node matches, as a record; no value when it does not match. A pattern names a kind and the roles and facts it requires: a nested pattern for a role holding one node, positions for a role holding many, ?name to capture what is there (twice: the same subtree twice), _ for anything"},
		"diff":           {operands: []valueType{node, node, text}, gives: list, says: "the pairs of corresponding nodes where two subtrees differ in the fingerprint mode named, the shallowest first, each a record of left and right"},
		"similarity":     {operands: []valueType{node, node}, gives: number, says: "how much of the larger subtree a common run of the kinds and roles of both matches, from 0 to 1"},
		"height":         {operands: []valueType{node}, gives: number, says: "how many nodes lie on the longest path down from the node to a leaf, the node included"},
		"occurs":         {operands: []valueType{node, node, text}, gives: truth, says: "whether a subtree the same as the second, in the fingerprint mode named, lies under the first, the second itself left out"},
		"lineCount":      {operands: []valueType{node}, gives: number, says: "how many lines the node spans"},
		"gap":            {operands: []valueType{node, node}, gives: number, says: "how many blank lines lie between the end of the first node and the start of the second; unknown for nodes of two files"},
		"width":          {operands: []valueType{node}, gives: number, says: "how many bytes the longest line the node spans holds"},
	}, map[string]computation{
		"ancestors": nodeList(func(site engine.Match, _ []value) (value, bool) {
			var above []value
			for at := site.Parent(); at.Exists(); at = at.Parent() {
				above = append(above, known(at))
			}

			return known(above), true
		}),
		"enclosing": func(_ evaluation, operands []value) value {
			site, isNode := nodeOf(operands[0])
			named, isText := operands[1].held.(string)
			stop := ""
			if len(operands) > 2 {
				stop, _ = operands[2].held.(string)
			}
			if !operands[0].known || !isNode || !isText {
				return unknown
			}
			for at := site.Parent(); at.Exists(); at = at.Parent() {
				switch {
				case at.Node().Is(named):
					return known(at)
				case stop != "" && at.Node().Is(stop):
					return known(nil)
				}
			}
			if owner := site.ReceiverType(); named == "type" && owner.Exists() {
				return known(owner)
			}

			return known(nil)
		},
		"descendants":    descendantsOf(false),
		"ownDescendants": descendantsOf(true),
		"children": nodeList(func(site engine.Match, _ []value) (value, bool) {
			return matches(site.Children()), !site.Node().Incomplete
		}),
		"childrenIn": nodeList(func(site engine.Match, operands []value) (value, bool) {
			role, isText := operands[1].held.(string)
			if !isText {
				return unknown, false
			}
			held, err := site.In(role)
			if err != nil || held.Answer == contract.Unknown {
				return unknown, false
			}

			return matches(held.Nodes), true
		}),
		"after": nodeList(func(site engine.Match, _ []value) (value, bool) {
			all := siblings(site, false)
			at := slices.IndexFunc(all, func(each value) bool { held, _ := nodeOf(each); return held.Node() == site.Node() })

			return known(all[at+1:]), at >= 0 && !site.Parent().Node().Incomplete
		}),
		"next":     neighbour(1),
		"previous": neighbour(-1),
		"follow": func(_ evaluation, operands []value) value {
			site, isNode := nodeOf(operands[0])
			role, isRole := operands[1].held.(string)
			kind := ""
			if len(operands) > 2 {
				kind, _ = operands[2].held.(string)
			}
			if !operands[0].known || !isNode || !isRole {
				return unknown
			}

			return known(followed(site, role, kind))
		},
		"leaves": func(_ evaluation, operands []value) value {
			site, isNode := nodeOf(operands[0])
			kind, isKind := operands[1].held.(string)
			role := ""
			if len(operands) > 2 {
				role, _ = operands[2].held.(string)
			}
			if !operands[0].known || !isNode || !isKind || !site.Exists() {
				return unknown
			}

			return known(leaves(site, kind, role))
		},
		"commonAncestor": func(_ evaluation, operands []value) value {
			first, second, read := twoNodes(operands)
			if !read || first.Source() != second.Source() {
				return unknown
			}
			for at := first; at.Exists(); at = at.Parent() {
				if isUnder(second, at) {
					return known(at)
				}
			}

			return known(nil)
		},
		"childToward": func(_ evaluation, operands []value) value {
			ancestor, site, read := twoNodes(operands)
			if !read {
				return unknown
			}
			for at := site; at.Exists(); at = at.Parent() {
				if at.Parent().Exists() && at.Parent().Node() == ancestor.Node() {
					return known(at)
				}
			}

			return known(nil)
		},
		"within": func(_ evaluation, operands []value) value {
			site, outer, read := twoNodes(operands)
			if !read {
				return unknown
			}

			return truthValue(engine.TruthOf(isUnder(site, outer)))
		},
		"isElseIf": nodeTruth(func(site engine.Match) engine.Truth {
			if site.Node().Kind != "if" {
				return engine.False
			}
			parent := site.Parent()

			return engine.TruthOf(parent.Exists() && parent.Node().Kind == "if" && site.Node().InRole() == "else")
		}),
		"isSelf":     nodeTruth(isSelf),
		"selfMember": func(_ evaluation, operands []value) value { return onNode(operands, selfMember) },
		"receiverChain": func(_ evaluation, operands []value) value {
			return onNode(operands, receiverChain)
		},
		"consumer":   func(_ evaluation, operands []value) value { return onNode(operands, consumer) },
		"accessMode": func(_ evaluation, operands []value) value { return onNode(operands, accessMode) },
		"freeVariables": func(_ evaluation, operands []value) value {
			return onNode(operands, freeVariables)
		},
		"declaredIn": func(_ evaluation, operands []value) value {
			return onNode(operands, func(site engine.Match) value { return known(declaredIn(site)) })
		},
		"fingerprint": func(_ evaluation, operands []value) value {
			site, isNode := nodeOf(operands[0])
			how, read := fingerprinting(operands[1:])
			if !operands[0].known || !isNode || !site.Exists() || !read {
				return unknown
			}
			written, taken := site.FingerprintAs(how)
			if !taken {
				return unknown
			}

			return known(written)
		},
		"equivalent": func(_ evaluation, operands []value) value {
			first, second, read := twoNodes(operands)
			how, chosen := fingerprinting(operands[2:])
			if !read || !chosen {
				return unknown
			}

			return truthValue(sameFingerprint(first, second, how))
		},
		"diff": func(_ evaluation, operands []value) value {
			first, second, read := twoNodes(operands)
			how, chosen := fingerprinting(operands[2:])
			if !read || !chosen {
				return unknown
			}
			pairs, complete := differences(first, second, how)
			if !complete {
				return unknown
			}

			return known(pairs)
		},
		"similarity": func(_ evaluation, operands []value) value {
			first, second, read := twoNodes(operands)
			if !read {
				return unknown
			}

			return known(similarity(shapeOf(first), shapeOf(second)))
		},
		"height": func(_ evaluation, operands []value) value {
			return onNode(operands, func(site engine.Match) value {
				if site.Node().Incomplete {
					return unknown
				}

				return known(float64(height(site)))
			})
		},
		"occurs": func(_ evaluation, operands []value) value {
			outer, wanted, read := twoNodes(operands)
			how, chosen := fingerprinting(operands[2:])
			if !read || !chosen {
				return unknown
			}
			answer := engine.False
			for _, each := range append([]engine.Match{outer}, descendantMatches(outer)...) {
				if each.Node() != wanted.Node() {
					if answer = answer.Or(sameFingerprint(each, wanted, how)); answer == engine.True {
						break
					}
				}
			}

			return truthValue(answer)
		},
		"lineCount": func(_ evaluation, operands []value) value {
			return onNode(operands, func(site engine.Match) value {
				return field(spanOf(site), "endLine", func(end float64) value { return known(end - float64(site.Node().Span.Line) + 1) })
			})
		},
		"gap": func(_ evaluation, operands []value) value {
			first, second, read := twoNodes(operands)
			source, readable := first.Source().Source()
			if !read || first.Source() != second.Source() || readable != nil {
				return unknown
			}
			from, to := first.Node().Span.End, second.Node().Span.Start
			if from > to {
				from, to = second.Node().Span.End, first.Node().Span.Start
			}
			blank := 0
			lines := bytes.Split(source[from:to], []byte("\n"))
			for at, line := range lines {
				if at > 0 && at < len(lines)-1 && len(bytes.TrimSpace(line)) == 0 {
					blank++
				}
			}

			return known(float64(blank))
		},
		"width": func(_ evaluation, operands []value) value {
			return onNode(operands, func(site engine.Match) value {
				source, err := site.Source().Source()
				if err != nil {
					return unknown
				}
				start := bytes.LastIndexByte(source[:site.Node().Span.Start], '\n') + 1
				end := site.Node().Span.End
				if next := bytes.IndexByte(source[end:], '\n'); next >= 0 {
					end += next
				} else {
					end = len(source)
				}
				widest := 0
				for _, line := range bytes.Split(source[start:end], []byte("\n")) {
					widest = max(widest, len(bytes.TrimRight(line, "\r")))
				}

				return known(float64(widest))
			})
		},
	})
}

func matches(held []engine.Match) value {
	items := make([]value, len(held))
	for at, each := range held {
		items[at] = known(each)
	}

	return known(items)
}

// onNode answers for a node that is there; unknown for an unknown value, no value for no node.
func onNode(operands []value, answer func(engine.Match) value) value {
	site, isNode := nodeOf(operands[0])
	if !operands[0].known || !isNode {
		return unknown
	}
	if !site.Exists() {
		return known(nil)
	}

	return answer(site)
}

func nodeTruth(answer func(engine.Match) engine.Truth) computation {
	return func(_ evaluation, operands []value) value {
		site, isNode := nodeOf(operands[0])
		if !operands[0].known || !isNode {
			return unknown
		}
		if !site.Exists() {
			return truthValue(engine.False)
		}

		return truthValue(answer(site))
	}
}

// nodeList is a list read from a node: empty for no node, unknown where the reader says it is not complete.
func nodeList(read func(engine.Match, []value) (value, bool)) computation {
	return func(_ evaluation, operands []value) value {
		site, isNode := nodeOf(operands[0])
		if !operands[0].known || !isNode {
			return unknown
		}
		if !site.Exists() {
			return known([]value{})
		}
		held, complete := read(site, operands)
		if !complete {
			return unknown
		}

		return held
	}
}

func twoNodes(operands []value) (engine.Match, engine.Match, bool) {
	first, isFirst := nodeOf(operands[0])
	second, isSecond := nodeOf(operands[1])

	return first, second, operands[0].known && operands[1].known && isFirst && isSecond && first.Exists() && second.Exists()
}

func isUnder(site, outer engine.Match) bool {
	for at := site; at.Exists(); at = at.Parent() {
		if at.Node() == outer.Node() {
			return true
		}
	}

	return false
}

func neighbour(step int) computation {
	return func(_ evaluation, operands []value) value {
		return onNode(operands, func(site engine.Match) value {
			if site.Parent().Exists() && site.Parent().Node().Incomplete {
				return unknown
			}
			all := siblings(site, false)
			at := slices.IndexFunc(all, func(each value) bool { held, _ := nodeOf(each); return held.Node() == site.Node() })
			if at < 0 || at+step < 0 || at+step >= len(all) {
				return known(nil)
			}

			return all[at+step]
		})
	}
}

// descendantsOf is the operator listing a node's descendants: its own, or every one into nested functions and types.
func descendantsOf(own bool) computation {
	return func(_ evaluation, operands []value) value {
		site, isNode := nodeOf(operands[0])
		kind := ""
		if len(operands) > 1 {
			kind, _ = operands[1].held.(string)
		}
		if !operands[0].known || !isNode {
			return unknown
		}
		if !site.Exists() {
			return known([]value{})
		}
		if site.Node().Incomplete {
			return unknown
		}
		var under []value
		if own {
			under = ownDescendants(site)
		} else {
			under = matches(descendantMatches(site)).held.([]value)
		}
		if kind == "" {
			return known(under)
		}

		return known(slices.DeleteFunc(under, func(each value) bool { held, _ := nodeOf(each); return !held.Node().Is(kind) }))
	}
}

func descendantMatches(site engine.Match) []engine.Match {
	var found []engine.Match
	for _, child := range site.Children() {
		found = append(found, child)
		found = append(found, descendantMatches(child)...)
	}

	return found
}

// isSelf says whether a node is the instance the method runs on: a reference written as this or self, or one bound to
// a Go method's receiver.
func isSelf(site engine.Match) engine.Truth {
	node := site.Node()
	if node.Kind != "reference" {
		return engine.False
	}
	if node.Form == "self" {
		return engine.True
	}
	if bound := node.Linked("binding"); len(bound) == 1 {
		binding := site.Source().Match(bound[0])
		parameter := binding.Parent()

		return engine.TruthOf(parameter.Exists() && parameter.Node().Kind == "parameter" && parameter.Node().InRole() == "receiver")
	}

	return engine.False
}

// selfMember is the declaration of the member a node reaches through the instance the method runs on.
func selfMember(site engine.Match) value {
	member := engine.Match{}
	switch node := site.Node(); {
	case node.Kind == "access" && isSelf(site.Child("subject")) == engine.True:
		member = site.Child("member")
	case node.Kind == "call" && site.Child("callee").Node().Kind == "access" && isSelf(site.Child("callee").Child("subject")) == engine.True:
		member = site.Child("callee").Child("member")
	default:
		return known(nil)
	}
	owner := site.EnclosingType()
	if !owner.Exists() {
		return known(nil)
	}
	if symbol := member.Node().Refers; symbol != "" {
		if declared := site.Codebase().Declarations(symbol); len(declared) > 0 {
			return known(declared[0])
		}

		return unknown
	}
	for _, declared := range memberDeclarations(owner) {
		if declared.Node().Name == member.Node().Name {
			return known(declared)
		}
	}
	if len(owner.ChildrenIn("bases"))+len(owner.ChildrenIn("mixins")) > 0 {
		return unknown
	}

	return known(nil)
}

// memberDeclarations is each declaration a type writes as a member: its functions, and the binding of each field,
// constant and promoted parameter.
func memberDeclarations(owner engine.Match) []engine.Match {
	var found []engine.Match
	for _, member := range append(owner.ChildrenIn("writtenMembers"), linkedMatches(owner, "implicitMembers")...) {
		switch member.Node().Kind {
		case "function":
			found = append(found, member)
		case "field", "constant":
			for _, declarator := range member.ChildrenIn("declarators") {
				found = append(found, declarator.Child("binding"))
			}
		case "parameter":
			found = append(found, member.Child("binding"))
		}
	}

	return found
}

func linkedMatches(site engine.Match, link string) []engine.Match {
	var found []engine.Match
	for _, id := range site.Node().Linked(link) {
		found = append(found, site.Source().Match(id))
	}

	return found
}

// receiverChain is each link of a chain of calls, accesses and indexes, the root first.
func receiverChain(site engine.Match) value {
	var links []value
	at := site
	for at.Exists() {
		if at.Node().Incomplete {
			return unknown
		}
		node := at.Node()
		link := record{"node": known(at), "member": known(""), "owner": known(""), "nullConditional": known(node.NullConditional)}
		switch node.Kind {
		case "call":
			callee := at.Child("callee")
			if callee.Node().Kind != "access" {
				links = append(links, known(link))
				at = engine.Match{}

				continue
			}
			link["member"] = known(callee.Child("member").Node().Name)
			link["nullConditional"] = known(node.NullConditional || callee.Node().NullConditional)
			if node.Target != nil {
				link["owner"] = known(node.Target.Type)
			}
			links = append(links, known(link))
			at = callee.Child("subject")
		case "access":
			link["member"] = known(at.Child("member").Node().Name)
			if resolved := at.Child("subject").Typed(); resolved != nil {
				link["owner"] = known(resolved.Name)
			}
			links = append(links, known(link))
			at = at.Child("subject")
		case "index":
			links = append(links, known(link))
			at = at.Child("subject")
		default:
			links = append(links, known(link))
			at = engine.Match{}
		}
	}
	slices.Reverse(links)

	return known(links)
}

// consumer is how an expression's value is used by what holds it.
func consumer(site engine.Match) value {
	parent := site.Parent()
	if !parent.Exists() {
		return known(nil)
	}
	if parent.Node().Incomplete {
		return unknown
	}
	role, holder := site.Node().InRole(), parent.Node()
	use, user := "operand", parent
	switch {
	case holder.Kind == "expression-statement":
		use = "discarded"
	case holder.Kind == "return":
		use = "returned"
	case (holder.Kind == "assignment" && role == "values") || (holder.Kind == "declarator" && role == "initializer"):
		use = "assigned"
	case holder.Kind == "argument":
		use, user = "argument", parent.Parent()
	case (holder.Kind == "access" || holder.Kind == "index") && role == "subject" && holder.NullConditional:
		use = "null-safe-receiver"
	case (holder.Kind == "access" || holder.Kind == "index") && role == "subject":
		use = "receiver"
	case holder.Kind == "call" && role == "callee":
		use = "called"
	case role == "condition":
		use = "condition"
	case holder.Kind == "comparison" && slices.ContainsFunc(parent.ChildrenIn("operands"), func(other engine.Match) bool {
		return other.Node() != site.Node() && emptyValue(other, "nothing") == engine.True
	}):
		use = "compared-to-nothing"
	case holder.Kind == "fallback" && role == "subject":
		use = "coalesced"
	case holder.Kind == "unary" && holder.Operator == "assert-present":
		use = "asserted-present"
	case holder.Kind == "throw":
		use = "thrown"
	case holder.Kind == "yield":
		use = "yielded"
	}

	return known(record{"use": known(use), "node": known(user)})
}

// accessMode is whether a reference reads, writes, or reads and writes the name it names.
func accessMode(site engine.Match) value {
	at := site
	for at.Parent().Exists() && at.Parent().Node().Kind == "pattern" {
		at = at.Parent()
	}
	parent := at.Parent()
	if !parent.Exists() {
		return known("read")
	}
	holder := parent.Node()
	switch {
	case holder.Kind == "assignment" && at.Node().InRole() == "targets":
		if holder.Mode == "assign" || holder.Mode == "define" || holder.Mode == "" {
			return known("write")
		}

		return known("readwrite")
	case holder.Kind == "unary" && holder.Operator == "delete":
		return known("write")
	case holder.Kind == "argument" && holder.Passing == "out":
		return known("write")
	case holder.Kind == "argument" && holder.Passing == "reference":
		return known("readwrite")
	}

	return known("read")
}

// freeVariables is each binding a function's references reach that is declared outside it.
func freeVariables(site engine.Match) value {
	if !site.Source().Knows("refers") {
		return unknown
	}
	var free []value
	seen := map[int]bool{}
	for _, each := range descendantMatches(site) {
		bound := each.Node().Linked("binding")
		if each.Node().Kind != "reference" || len(bound) != 1 || seen[bound[0]] {
			continue
		}
		binding := site.Source().Match(bound[0])
		if !isUnder(binding, site) {
			seen[bound[0]] = true
			free = append(free, known(binding))
		}
	}

	return known(free)
}

// scopes are the kinds whose bindings declaredIn leaves to them.
var scopes = []string{"block", "function", "type", "loop", "handler", "comprehension"}

func declaredIn(site engine.Match) []value {
	var found []value
	var walk func(at engine.Match)
	walk = func(at engine.Match) {
		for _, child := range at.Children() {
			switch {
			case child.Node().Kind == "binding":
				found = append(found, known(child))
			case slices.Contains(scopes, child.Node().Kind):
			default:
				walk(child)
			}
		}
	}
	walk(site)

	return found
}

// fingerprinting is the mode and options a fingerprint operator names: exact when none is named.
func fingerprinting(operands []value) (engine.Fingerprinting, bool) {
	how := engine.Fingerprinting{Mode: "exact"}
	if len(operands) == 0 {
		return how, true
	}
	mode, isText := operands[0].held.(string)
	if !operands[0].known || !isText || !slices.Contains(engine.FingerprintModes, mode) {
		return how, false
	}
	how.Mode = mode
	if len(operands) > 1 {
		options, read := textItems(operands[1])
		if !read {
			return how, false
		}
		how.LiteralKinds, how.Commutative = slices.Contains(options, "literals"), slices.Contains(options, "commutative")
	}

	return how, true
}

func sameFingerprint(first, second engine.Match, how engine.Fingerprinting) engine.Truth {
	left, takenLeft := first.FingerprintAs(how)
	right, takenRight := second.FingerprintAs(how)
	if !takenLeft || !takenRight {
		return engine.Unknown
	}

	return engine.TruthOf(left == right)
}

// differences is each pair of corresponding nodes where two subtrees part, found by walking them side by side; a pair
// whose own kind, role or child count differs is reported whole, and the walk goes no deeper there.
func differences(first, second engine.Match, how engine.Fingerprinting) ([]value, bool) {
	same := sameFingerprint(first, second, how)
	switch same {
	case engine.Unknown:
		return nil, false
	case engine.True:
		return nil, true
	}
	left, right := first.Children(), second.Children()
	if first.Node().Kind != second.Node().Kind || len(left) != len(right) || !sameOwnLabel(first, second, how) {
		return []value{known(record{"left": known(first), "right": known(second)})}, true
	}
	var pairs []value
	for at := range left {
		inner, complete := differences(left[at], right[at], how)
		if !complete {
			return nil, false
		}
		pairs = append(pairs, inner...)
	}
	if len(pairs) == 0 {
		return []value{known(record{"left": known(first), "right": known(second)})}, true
	}
	slices.SortStableFunc(pairs, func(a, b value) int {
		return depthOf(a) - depthOf(b)
	})

	return pairs, true
}

func depthOf(pair value) int {
	left, _ := nodeOf(pair.held.(record)["left"])
	depth := 0
	for at := left.Parent(); at.Exists(); at = at.Parent() {
		depth++
	}

	return depth
}

// sameOwnLabel says whether two nodes are alike in themselves, their children left aside: in the shape mode by kind and
// role alone, else by name, operator, form and value too.
func sameOwnLabel(first, second engine.Match, how engine.Fingerprinting) bool {
	a, b := first.Node(), second.Node()
	if a.InRole() != b.InRole() {
		return false
	}
	if how.Mode == "shape" || len(first.Children()) > 0 {
		return true
	}
	solo := engine.Fingerprinting{Mode: how.Mode, LiteralKinds: how.LiteralKinds}

	return sameFingerprint(first, second, solo) == engine.True
}

// shapeOf is a subtree's kinds and roles in pre-order.
func shapeOf(site engine.Match) []string {
	shape := []string{site.Node().Kind + "|" + site.Node().InRole()}
	for _, child := range site.Children() {
		shape = append(shape, shapeOf(child)...)
	}

	return shape
}

// similarity is the share of the longer sequence that a longest common subsequence of both matches.
func similarity(first, second []string) float64 {
	if len(first) == 0 && len(second) == 0 {
		return 1
	}
	previous := make([]int, len(second)+1)
	for _, left := range first {
		current := make([]int, len(second)+1)
		for column, right := range second {
			if left == right {
				current[column+1] = previous[column] + 1
			} else {
				current[column+1] = max(current[column], previous[column+1])
			}
		}
		previous = current
	}

	return float64(previous[len(second)]) / float64(max(len(first), len(second)))
}

func height(site engine.Match) int {
	tallest := 0
	for _, child := range site.Children() {
		tallest = max(tallest, height(child))
	}

	return tallest + 1
}

// spanOf is where a node lies: its byte start and end and first line, and from the file's bytes its last line and the
// columns it starts and ends at, unknown where the file cannot be read.
func spanOf(site engine.Match) record {
	span := site.Node().Span
	at := record{"start": known(float64(span.Start)), "end": known(float64(span.End)), "line": known(float64(span.Line)),
		"endLine": unknown, "column": unknown, "endColumn": unknown}
	source, err := site.Source().Source()
	if err != nil || span.End > len(source) {
		return at
	}
	at["endLine"] = known(float64(span.Line + bytes.Count(source[span.Start:span.End], []byte("\n"))))
	at["column"] = known(float64(span.Start - bytes.LastIndexByte(source[:span.Start], '\n') - 1))
	at["endColumn"] = known(float64(span.End - bytes.LastIndexByte(source[:span.End], '\n') - 1))

	return at
}

func field(fields record, name string, then func(float64) value) value {
	number, isNumber := fields[name].held.(float64)
	if !fields[name].known || !isNumber {
		return unknown
	}

	return then(number)
}

// matchesShape is the captures of a pattern a node matches; no value when it does not, unknown when a role it names is.
func (e evaluation) matchesShape(site engine.Match, pattern map[string]Expr, bound bindings) value {
	captured := record{}
	switch e.shapeHolds(site, pattern, captured, bound) {
	case engine.True:
		return known(captured)
	case engine.False:
		return known(nil)
	}

	return unknown
}

func (e evaluation) shapeHolds(site engine.Match, pattern map[string]Expr, captured record, bound bindings) engine.Truth {
	if !site.Exists() {
		return engine.False
	}
	answer := engine.True
	for _, name := range slices.Sorted(maps.Keys(pattern)) {
		wanted := pattern[name]
		if name == "kind" {
			kind, _ := wanted.Value.(string)
			if !site.Node().Is(kind) {
				return engine.False
			}

			continue
		}
		answer = answer.And(e.partHolds(readNode(site, name), wanted, captured, bound))
		if answer == engine.False {
			return engine.False
		}
	}

	return answer
}

// partHolds says whether what a node holds under one name fits its part of the pattern.
func (e evaluation) partHolds(held value, wanted Expr, captured record, bound bindings) engine.Truth {
	if written, isText := wanted.Value.(string); isText && !wanted.IsReference() && !wanted.IsCall() && len(written) > 0 && (written == "_" || written[0] == '?') {
		if written == "_" {
			return engine.True
		}
		if !held.known {
			return engine.Unknown
		}
		name := written[1:]
		earlier, seen := captured[name]
		if !seen {
			captured[name] = held
			return engine.True
		}
		first, isFirst := nodeOf(earlier)
		second, isSecond := nodeOf(held)
		if isFirst && isSecond && first.Exists() && second.Exists() {
			return sameFingerprint(first, second, engine.Fingerprinting{Mode: "exact"})
		}

		return equal(earlier, held)
	}
	if wanted.IsArms() {
		if items, isList := listOf(held); isList && held.held != nil {
			return e.listHolds(items, wanted.Arms, captured, bound)
		}
		site, isNode := nodeOf(held)
		if !held.known || !isNode {
			return engine.Unknown
		}

		return e.shapeHolds(site, wanted.Arms, captured, bound)
	}

	return equal(held, e.evaluate(wanted, bound))
}

// listHolds says whether a list fits a pattern written by position, with count for how many it holds.
func (e evaluation) listHolds(items []value, pattern map[string]Expr, captured record, bound bindings) engine.Truth {
	answer := engine.True
	for _, name := range slices.Sorted(maps.Keys(pattern)) {
		wanted := pattern[name]
		if name == "count" {
			answer = answer.And(equal(known(float64(len(items))), e.evaluate(wanted, bound)))
			continue
		}
		at := -1
		for position := range items {
			if name == strconv.Itoa(position) {
				at = position
			}
		}
		if at < 0 {
			return engine.False
		}
		answer = answer.And(e.partHolds(items[at], wanted, captured, bound))
		if answer == engine.False {
			return engine.False
		}
	}

	return answer
}
