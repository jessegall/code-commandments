package rule

import (
	"slices"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// The operators over control flow, choices and failures, each read from the structure of the tree.
func init() {
	define(map[string]operator{
		"completes":      {operands: []valueType{node}, gives: truth, says: "whether control can leave a statement by running off its end: false when every path through it returns, throws, breaks, continues or jumps, and for a loop that never ends without a break; unknown under a node the parser recovered from"},
		"reachable":      {operands: []valueType{node}, gives: truth, says: "whether some path from the start of its function reaches the node: false after a statement that never completes, and in the arm of an if whose condition is a literal that rules it out"},
		"exitsOf":        {operands: []valueType{node}, gives: list, says: "the returns and throws under a node, and the breaks, continues and gotos under it that leave it, its own functions left out"},
		"precedes":       {operands: []valueType{node, node}, gives: truth, says: "whether on some path the first node runs before the second: written before it and not in another arm of one choice, or both inside one loop; unknown for nodes of two functions"},
		"dominates":      {operands: []valueType{node, node}, gives: truth, says: "whether every path to the second node passes the first: the first runs on every path through what holds both, before the part that holds the second; unknown for nodes of two functions"},
		"pathConditions": {operands: []valueType{node}, gives: list, says: "the conditions that must hold for a node to run, each a record of condition and holds (true or false): the if, else, loop, ternary, and and or around it, and each guard before it that returns when its condition holds; unknown in a function with a goto"},
		"complexity":     {operands: []valueType{node, text}, gives: number, says: "the node's cyclomatic complexity (one plus each decision: if, loop, case, handler, ternary, and, or) or cognitive complexity (each break in the flow, weighed by how deeply it is nested)"},
		"exclusive":      {operands: []valueType{node, node}, gives: truth, says: "whether no run executes both nodes: they lie in different arms of one if, ternary or dispatch whose arms do not fall through, or in two handlers of one try"},
		"carriesCause":   {operands: []valueType{node}, gives: truth, says: "whether a throw inside a handler carries the failure it caught: a rethrow, a raise from, or a new failure built from the caught one"},
		"catchesAll":     {operands: []valueType{node}, gives: truth, says: "whether a handler catches every failure: a catch-all, or one whose caught type is the language's root failure type"},
		"failureType":    {operands: []valueType{anyValue, text}, gives: truth, says: "whether a type is one of the language's failure types of the class named: root (Throwable, BaseException, System.Exception, Error) or generic (Exception, RuntimeError, ApplicationException and the like); unknown for a type the compiler did not resolve"},
		"buildsText":     {operands: []valueType{node}, gives: truth, says: "whether an expression writes text on the spot: a text literal, an interpolation, or a joining or format call with a text part"},
		"testsType":      {operands: []valueType{node}, gives: anyValue, says: "what a type test tests, as a record of subject, types and negated: instanceof, is, isinstance, a type switch's case; no value for another expression"},
		"membershipTest": {operands: []valueType{node}, gives: anyValue, says: "what a membership test compares, as a record of subject and candidates: in, a library's contains or includes, case a, b:, and an or of equalities over one subject; no value for another expression, unknown where a candidate is computed"},
		"dispatchArms":   {operands: []valueType{node}, gives: list, says: "the arms of a choice, each a record of tests, body and isDefault: a dispatch's arms, or an if with each of its else-ifs and its final else"},
	}, map[string]computation{
		"completes": nodeTruth(completes),
		"reachable": nodeTruth(reachable),
		"exitsOf": nodeList(func(site engine.Match, _ []value) (value, bool) {
			return known(exitsOf(site)), true
		}),
		"precedes": func(_ evaluation, operands []value) value {
			first, second, read := twoNodes(operands)
			if !read || first.EnclosingFunction().Node() != second.EnclosingFunction().Node() {
				return unknown
			}
			for at := commonAncestorOf(first, second); at.Exists() && !at.Node().Is("function-like"); at = at.Parent() {
				if at.Node().Kind == "loop" {
					return truthValue(engine.True)
				}
			}

			return truthValue(engine.TruthOf(first.Node().Span.Start < second.Node().Span.Start).And(exclusive(first, second).Not()))
		},
		"dominates": func(_ evaluation, operands []value) value {
			first, second, read := twoNodes(operands)
			if !read || first.EnclosingFunction().Node() != second.EnclosingFunction().Node() {
				return unknown
			}

			return truthValue(dominates(first, second))
		},
		"pathConditions": nodeList(func(site engine.Match, _ []value) (value, bool) {
			conditions, complete := pathConditions(site)

			return known(conditions), complete
		}),
		"complexity": func(_ evaluation, operands []value) value {
			site, isNode := nodeOf(operands[0])
			measure, isText := operands[1].held.(string)
			if !operands[0].known || !isNode || !isText || !site.Exists() || site.Node().Incomplete {
				return unknown
			}
			switch measure {
			case "cyclomatic":
				return known(float64(1 + decisions(site)))
			case "cognitive":
				return known(float64(cognitive(site, 0)))
			}

			return unknown
		},
		"exclusive": func(_ evaluation, operands []value) value {
			first, second, read := twoNodes(operands)
			if !read || first.Source() != second.Source() {
				return unknown
			}

			return truthValue(exclusive(first, second))
		},
		"carriesCause": nodeTruth(carriesCause),
		"catchesAll": func(e evaluation, operands []value) value {
			site, isNode := nodeOf(operands[0])
			if !operands[0].known || !isNode || !site.Exists() || site.Node().Kind != "handler" {
				return unknown
			}
			if site.Node().CatchForm == "catch-all" {
				return truthValue(engine.True)
			}
			answer := engine.False
			for _, caught := range site.ChildrenIn("caughtTypes") {
				answer = answer.Or(e.failureType(known(caught), "failure-root"))
			}

			return truthValue(answer)
		},
		"failureType": func(e evaluation, operands []value) value {
			class, isText := operands[1].held.(string)
			if !operands[1].known || !isText || (class != "root" && class != "generic") {
				return unknown
			}

			return truthValue(e.failureType(operands[0], "failure-"+class))
		},
		"buildsText": nodeTruth(buildsText),
		"testsType": func(_ evaluation, operands []value) value {
			return onNode(operands, func(site engine.Match) value {
				if site.Node().Kind != "type-test" {
					return known(nil)
				}
				tested := site.Child("testedType")
				types := []value{known(tested)}
				if members := tested.ChildrenIn("members"); len(members) > 0 && tested.Node().Form == "union" {
					types = matches(members).held.([]value)
				}

				return known(record{"subject": known(site.Child("subject")), "types": known(types), "negated": known(site.Node().Negated)})
			})
		},
		"membershipTest": func(_ evaluation, operands []value) value { return onNode(operands, membershipTest) },
		"dispatchArms":   func(_ evaluation, operands []value) value { return onNode(operands, dispatchArms) },
	})
}

// completes says whether control can run off the end of a statement.
func completes(site engine.Match) engine.Truth {
	node := site.Node()
	if node.Incomplete || node.Kind == "unsupported" {
		return engine.Unknown
	}
	switch node.Kind {
	case "return", "throw", "break", "continue", "goto":
		return engine.False
	case "block":
		answer := engine.True
		for _, statement := range site.ChildrenIn("statements") {
			if answer = answer.And(completes(statement)); answer == engine.False {
				return engine.False
			}
		}

		return answer
	case "if":
		otherwise := site.Child("else")
		if !otherwise.Exists() {
			return engine.True
		}

		return completes(site.Child("then")).Or(completes(otherwise))
	case "loop":
		condition := site.Child("condition")
		endless := node.Form == "infinite" || (!condition.Exists() && node.Form == "for") || literalTruth(condition) == engine.True
		if !endless {
			return engine.True
		}

		return engine.TruthOf(len(breaksOutOf(site)) > 0)
	case "dispatch":
		arms := site.ChildrenIn("arms")
		hasDefault := slices.ContainsFunc(arms, func(arm engine.Match) bool { return arm.Node().Default })
		exhaustive := node.Exhaustive != nil && *node.Exhaustive
		if (!hasDefault && !exhaustive) || len(breaksOutOf(site)) > 0 {
			return engine.True
		}
		answer := engine.False
		for _, arm := range arms {
			answer = answer.Or(completes(arm.Child("body")))
		}

		return answer
	case "try":
		path := completes(site.Child("body"))
		if otherwise := site.Child("normalCompletion"); otherwise.Exists() {
			path = path.And(completes(otherwise))
		}
		for _, handler := range site.ChildrenIn("handlers") {
			path = path.Or(completes(handler.Child("body")))
		}
		if finally := site.Child("finally"); finally.Exists() {
			return completes(finally).And(path)
		}

		return path
	case "label":
		if statement := site.Child("statement"); statement.Exists() {
			return completes(statement)
		}
	case "resource-scope":
		return completes(site.Child("body"))
	case "expression-statement":
		if site.Child("expression").Node().Kind == "throw" {
			return engine.False
		}
	}

	return engine.True
}

// literalTruth is the truth a condition writes as a literal or the compiler folded it to; unknown for any other.
func literalTruth(condition engine.Match) engine.Truth {
	if !condition.Exists() {
		return engine.Unknown
	}
	held := literalOf(condition)
	truthful, isTruth := held.held.(bool)
	if !held.known || !isTruth {
		return engine.Unknown
	}

	return engine.TruthOf(truthful)
}

// breaksOutOf is each break that leaves the loop or dispatch: one whose destination it is.
func breaksOutOf(site engine.Match) []engine.Match {
	var found []engine.Match
	for _, each := range descendantMatches(site) {
		if each.Node().Kind == "break" && breakTarget(each).Node() == site.Node() {
			found = append(found, each)
		}
	}

	return found
}

// breakTarget is the statement a break or continue leaves, as the reader links it.
func breakTarget(jump engine.Match) engine.Match {
	if destination := linkedMatches(jump, "destination"); len(destination) == 1 {
		return destination[0]
	}

	return engine.Match{}
}

// reachable says whether some path from the start of its function reaches a node.
func reachable(site engine.Match) engine.Truth {
	answer := engine.True
	for at := site; at.Parent().Exists() && !at.Node().Is("function-like"); at = at.Parent() {
		parent := at.Parent()
		switch parent.Node().Kind {
		case "block":
			for _, earlier := range siblings(at, true) {
				held, _ := nodeOf(earlier)
				if answer = answer.And(completes(held)); answer == engine.False {
					return engine.False
				}
			}
		case "if":
			condition := literalTruth(parent.Child("condition"))
			if (at.Node().InRole() == "then" && condition == engine.False) || (at.Node().InRole() == "else" && condition == engine.True) {
				return engine.False
			}
		case "loop":
			if at.Node().InRole() == "body" && parent.Node().Form != "do-while" && literalTruth(parent.Child("condition")) == engine.False {
				return engine.False
			}
		}
	}

	return answer
}

// exitsOf is each exit under a node that leaves it.
func exitsOf(site engine.Match) []value {
	var exits []value
	for _, each := range ownDescendants(site) {
		held, _ := nodeOf(each)
		switch held.Node().Kind {
		case "return", "throw":
			exits = append(exits, each)
		case "break", "continue":
			if target := breakTarget(held); !target.Exists() || target.Node() == site.Node() || !isUnder(target, site) {
				exits = append(exits, each)
			}
		case "goto":
			if label := linkedMatches(held, "label"); len(label) != 1 || !isUnder(label[0], site) {
				exits = append(exits, each)
			}
		}
	}

	return exits
}

func commonAncestorOf(first, second engine.Match) engine.Match {
	for at := first; at.Exists(); at = at.Parent() {
		if isUnder(second, at) {
			return at
		}
	}

	return engine.Match{}
}

// mayNotRun says whether a part, in the role it fills, can be skipped when what holds it runs: an arm of a choice, the
// body of a loop that can run no turn, the right side of and or or, a function written inside.
func mayNotRun(part engine.Match) bool {
	holder := part.Parent().Node()
	switch role := part.Node().InRole(); {
	case slices.Contains([]string{"then", "else", "arms", "handlers", "consequent", "alternative", "replacement", "normalCompletion", "steps"}, role):
		return true
	case role == "right" && holder.Kind == "logical":
		return true
	case role == "body" && !(holder.Kind == "loop" && holder.Form == "do-while") && holder.Kind != "try" && holder.Kind != "resource-scope" && holder.Kind != "label":
		return true
	case part.Node().Is("function-like"):
		return true
	}

	return false
}

// dominates says whether every path to the second node passes the first.
func dominates(first, second engine.Match) engine.Truth {
	if first.Node() == second.Node() {
		return engine.True
	}
	common := commonAncestorOf(first, second)
	if common.Node() == first.Node() {
		return engine.False
	}
	for at := first; at.Node() != common.Node(); at = at.Parent() {
		if at.Node().Incomplete {
			return engine.Unknown
		}
		if mayNotRun(at) {
			return engine.False
		}
	}
	toFirst, toSecond := childTowardOf(common, first), childTowardOf(common, second)
	if common.Node().Kind == "loop" && toFirst.Node().InRole() == "steps" {
		return engine.False
	}

	return engine.TruthOf(toFirst.Node().Span.Start < toSecond.Node().Span.Start || (common.Node().Kind == "loop" && toSecond.Node().InRole() == "steps"))
}

func childTowardOf(ancestor, site engine.Match) engine.Match {
	for at := site; at.Exists(); at = at.Parent() {
		if at.Parent().Node() == ancestor.Node() {
			return at
		}
	}

	return engine.Match{}
}

// pathConditions is each condition that must hold for a node to run, with the truth it must have.
func pathConditions(site engine.Match) ([]value, bool) {
	function := site.EnclosingFunction()
	if function.Exists() && slices.ContainsFunc(descendantMatches(function), func(at engine.Match) bool { return at.Node().Kind == "goto" }) {
		return nil, false
	}
	var conditions []value
	var add func(condition engine.Match, holds bool)
	add = func(condition engine.Match, holds bool) {
		conditions = append(conditions, known(record{"condition": known(condition), "holds": truthValue(engine.TruthOf(holds))}))
		joined := condition.Node()
		if joined.Kind == "logical" && ((holds && joined.Operator == "and") || (!holds && joined.Operator == "or")) {
			add(condition.Child("left"), holds)
			add(condition.Child("right"), holds)
		}
	}
	for at := site; at.Parent().Exists() && !at.Node().Is("function-like"); at = at.Parent() {
		parent := at.Parent()
		role := at.Node().InRole()
		switch holder := parent.Node(); {
		case holder.Kind == "if" && (role == "then" || role == "else"):
			add(parent.Child("condition"), role == "then")
		case holder.Kind == "conditional" && (role == "consequent" || role == "alternative"):
			add(parent.Child("condition"), role == "consequent")
		case holder.Kind == "loop" && (role == "body" || role == "steps") && parent.Child("condition").Exists() && holder.Form != "do-while":
			add(parent.Child("condition"), true)
		case holder.Kind == "logical" && role == "right":
			add(parent.Child("left"), holder.Operator == "and")
		case holder.Kind == "arm" && role == "body" && parent.Child("guard").Exists():
			add(parent.Child("guard"), true)
		case holder.Kind == "block":
			for _, earlier := range siblings(at, true) {
				guard, _ := nodeOf(earlier)
				if guard.Node().Kind == "if" && !guard.Child("else").Exists() && completes(guard.Child("then")) == engine.False {
					add(guard.Child("condition"), false)
				}
			}
		}
	}

	return conditions, true
}

// decisions is how many decisions a node holds, its own functions left out.
func decisions(site engine.Match) int {
	count := 0
	for _, each := range ownDescendants(site) {
		held, _ := nodeOf(each)
		switch node := held.Node(); node.Kind {
		case "if", "loop", "handler", "conditional", "fallback", "logical":
			count++
		case "arm":
			if !node.Default {
				count++
			}
		}
	}

	return count
}

// cognitive is a node's cognitive complexity: one for each break in the linear flow, and as much again as it is nested.
func cognitive(site engine.Match, nesting int) int {
	total := 0
	for _, child := range site.Children() {
		node := child.Node()
		switch {
		case node.Kind == "if" && node.InRole() == "else" && site.Node().Kind == "if":
			total += 1 + cognitive(child, nesting)
		case slices.Contains([]string{"if", "loop", "dispatch", "handler", "conditional"}, node.Kind):
			total += 1 + nesting + cognitive(child, nesting+1)
			if otherwise := child.Child("else"); node.Kind == "if" && otherwise.Exists() && otherwise.Node().Kind != "if" {
				total++
			}
		case node.Kind == "logical" && site.Node().Kind != "logical":
			total += logicalRuns(child) + cognitive(child, nesting)
		case node.Kind == "goto" || ((node.Kind == "break" || node.Kind == "continue") && child.Child("label").Exists()):
			total++
		case node.Is("function-like"):
			total += cognitive(child, nesting+1)
		default:
			total += cognitive(child, nesting)
		}
	}

	return total
}

// logicalRuns is how many runs of one logical operator a nest of and and or holds: a && b || c holds two.
func logicalRuns(site engine.Match) int {
	runs := 1
	for _, child := range site.Children() {
		if child.Node().Kind == "logical" {
			runs += logicalRuns(child)
			if child.Node().Operator == site.Node().Operator {
				runs--
			}
		}
	}

	return runs
}

// exclusive says whether no run executes both nodes.
func exclusive(first, second engine.Match) engine.Truth {
	common := commonAncestorOf(first, second)
	if !common.Exists() || common.Node() == first.Node() || common.Node() == second.Node() {
		return engine.False
	}
	toFirst, toSecond := childTowardOf(common, first), childTowardOf(common, second)
	roles := []string{toFirst.Node().InRole(), toSecond.Node().InRole()}
	slices.Sort(roles)
	switch common.Node().Kind {
	case "if":
		return engine.TruthOf(roles[0] == "else" && roles[1] == "then")
	case "conditional":
		return engine.TruthOf(roles[0] == "alternative" && roles[1] == "consequent")
	case "try":
		return engine.TruthOf(roles[0] == "handlers" && roles[1] == "handlers")
	case "dispatch":
		if roles[0] != "arms" || roles[1] != "arms" {
			return engine.False
		}
		arms := common.ChildrenIn("arms")
		from := slices.IndexFunc(arms, func(arm engine.Match) bool { return arm.Node() == toFirst.Node() })
		to := slices.IndexFunc(arms, func(arm engine.Match) bool { return arm.Node() == toSecond.Node() })
		from, to = min(from, to), max(from, to)
		for _, arm := range arms[from:to] {
			if arm.Node().FallsThrough {
				return engine.False
			}
		}

		return engine.True
	}

	return engine.False
}

// carriesCause says whether a throw inside a handler carries the failure the handler caught.
func carriesCause(site engine.Match) engine.Truth {
	if site.Node().Kind != "throw" {
		return engine.False
	}
	if !site.Child("value").Exists() || site.Child("cause").Exists() {
		return engine.True
	}
	handler := site.Parent()
	for handler.Exists() && handler.Node().Kind != "handler" && !handler.Node().Is("function-like") {
		handler = handler.Parent()
	}
	if !handler.Exists() || handler.Node().Kind != "handler" || !handler.Child("binding").Exists() {
		return engine.False
	}
	if !site.Source().Knows("refers") {
		return engine.Unknown
	}
	caught := handler.Child("binding").Node()

	return engine.TruthOf(slices.ContainsFunc(descendantMatches(site.Child("value")), func(at engine.Match) bool {
		bound := at.Node().Linked("binding")
		return at.Node().Kind == "reference" && len(bound) == 1 && bound[0] == caught.ID
	}) || slices.Contains(site.Child("value").Node().Linked("binding"), caught.ID))
}

// failureType says whether a type is exactly one of the failure types an identity names.
func (e evaluation) failureType(v value, identity string) engine.Truth {
	name := ""
	switch held := v.held.(type) {
	case string:
		name = held
	case engine.Match:
		if resolved := held.Typed(); resolved != nil {
			name = resolved.Name
		} else if held.Exists() {
			name = held.Node().Refers
		}
	case *contract.Type:
		name = held.Name
	}
	if !v.known || name == "" {
		return engine.Unknown
	}

	return engine.TruthOf(slices.Contains(engine.PackageIdentitiesNamed(identity), name))
}

// buildsText says whether an expression writes text on the spot.
func buildsText(site engine.Match) engine.Truth {
	node := site.Node()
	switch node.Kind {
	case "literal":
		return engine.TruthOf(node.LiteralKind == "string")
	case "interpolation":
		return engine.True
	case "binary":
		if node.Operator != "concatenate" && node.Operator != "add" {
			return engine.False
		}

		return buildsText(site.Child("left")).Or(buildsText(site.Child("right")))
	case "call":
		formats := site.HasEffect("formats-text")
		if formats != engine.True {
			return formats
		}

		return engine.TruthOf(slices.ContainsFunc(site.ChildrenIn("arguments"), func(argument engine.Match) bool {
			return buildsText(argument.Child("value")) == engine.True
		}))
	}

	return engine.False
}

// membershipTest is what a membership test compares.
func membershipTest(site engine.Match) value {
	node := site.Node()
	candidatesOf := func(haystack engine.Match) value {
		if haystack.Node().Kind != "collection" {
			return unknown
		}
		var candidates []value
		for _, entry := range haystack.ChildrenIn("entries") {
			candidates = append(candidates, known(entry.Child("value")))
		}

		return known(candidates)
	}
	tested := func(subject engine.Match, candidates value) value {
		if !candidates.known {
			return unknown
		}

		return known(record{"subject": known(subject), "candidates": candidates})
	}
	switch node.Kind {
	case "comparison":
		operands := site.ChildrenIn("operands")
		if node.Operator == "in" && len(operands) == 2 {
			return tested(operands[0], candidatesOf(operands[1]))
		}
	case "call":
		membership := site.HasEffect("tests-membership")
		arguments := site.ChildrenIn("arguments")
		switch {
		case membership == engine.Unknown:
			return unknown
		case membership == engine.True && site.Child("callee").Node().Kind == "access" && len(arguments) > 0:
			return tested(arguments[0].Child("value"), candidatesOf(site.Child("callee").Child("subject")))
		case membership == engine.True && len(arguments) > 1:
			return tested(arguments[0].Child("value"), candidatesOf(arguments[1].Child("value")))
		}
	case "arm":
		if patterns := site.ChildrenIn("patterns"); len(patterns) > 1 {
			return tested(site.Parent().Child("subject"), matches(patterns))
		}
	case "logical":
		if node.Operator != "or" {
			break
		}
		var subject engine.Match
		var candidates []value
		for _, part := range leaves(site, "logical", "") {
			test, _ := nodeOf(part)
			operands := test.ChildrenIn("operands")
			if test.Node().Kind != "comparison" || len(operands) != 2 || (test.Node().Operator != "equal" && test.Node().Operator != "identical") {
				return known(nil)
			}
			if subject.Exists() && sameFingerprint(subject, operands[0], engine.Fingerprinting{Mode: "exact"}) != engine.True {
				return known(nil)
			}
			subject = operands[0]
			candidates = append(candidates, known(operands[1]))
		}

		return tested(subject, known(candidates))
	}

	return known(nil)
}

// dispatchArms is the arms of a choice in order.
func dispatchArms(site engine.Match) value {
	arm := func(tests []engine.Match, body engine.Match, isDefault bool) value {
		return known(record{"tests": matches(tests), "body": known(body), "isDefault": truthValue(engine.TruthOf(isDefault))})
	}
	var arms []value
	switch site.Node().Kind {
	case "dispatch":
		for _, each := range site.ChildrenIn("arms") {
			if each.Node().Kind == "unsupported" || each.Node().Incomplete {
				return unknown
			}
			tests := each.ChildrenIn("patterns")
			if guard := each.Child("guard"); guard.Exists() {
				tests = append(tests, guard)
			}
			arms = append(arms, arm(tests, each.Child("body"), each.Node().Default))
		}
	case "if":
		at := site
		for at.Exists() && at.Node().Kind == "if" {
			arms = append(arms, arm([]engine.Match{at.Child("condition")}, at.Child("then"), false))
			at = at.Child("else")
		}
		if at.Exists() {
			arms = append(arms, arm(nil, at, true))
		}
	default:
		return known(nil)
	}

	return known(arms)
}
