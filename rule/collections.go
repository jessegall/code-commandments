package rule

import (
	"cmp"
	"math"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// The operators over lists, sets and graphs, over symbols and modules, and over the rows of the find's relations.
func init() {
	define(map[string]operator{
		"rest":              {operands: []valueType{list}, gives: list, says: "the list without its first item"},
		"take":              {operands: []valueType{list, number}, gives: list, says: "the first n items of a list"},
		"drop":              {operands: []valueType{list, number}, gives: list, says: "the list without its first n items"},
		"slice":             {operands: []valueType{anyValue, number, number}, gives: anyValue, says: "the items or characters from the first position up to the second, a negative one counted from the end, positions past the end clamped"},
		"append":            {operands: []valueType{list, list}, repeating: true, gives: list, says: "the lists one after another"},
		"distinct":          {operands: []valueType{list}, gives: list, says: "the first of each equal item, in order"},
		"distinctBy":        {operands: []valueType{list, iteratorName, anyValue}, gives: list, says: "the first item of each key the body gives, in order; unknown when a key is"},
		"groupItems":        {operands: []valueType{list, iteratorName, anyValue}, gives: list, says: "the items grouped by the key the body gives, each group a record of key and items, in the order each key first appears; unknown when a key is"},
		"sortBy":            {operands: []valueType{list, iteratorName, anyValue}, gives: list, says: "the items ordered by the key the body gives: numbers by size, anything else by its written form"},
		"minBy":             {operands: []valueType{list, iteratorName, number}, gives: anyValue, says: "the item whose body gives the smallest number; no value for an empty list"},
		"maxBy":             {operands: []valueType{list, iteratorName, number}, gives: anyValue, says: "the item whose body gives the largest number; no value for an empty list"},
		"union":             {operands: []valueType{list, list}, gives: list, says: "every item of either list once, in order: values by equality, nodes by identity"},
		"intersect":         {operands: []valueType{list, list}, gives: list, says: "the items of the first list the second holds too"},
		"difference":        {operands: []valueType{list, list}, gives: list, says: "the items of the first list the second does not hold; unknown when an item of either is"},
		"subset":            {operands: []valueType{list, list}, gives: truth, says: "whether every item of the first list is in the second, order and repeats aside"},
		"disjoint":          {operands: []valueType{list, list}, gives: truth, says: "whether no item of the first list is in the second"},
		"sameItems":         {operands: []valueType{list, list}, gives: truth, says: "whether two lists hold the same items, order and repeats aside"},
		"zip":               {operands: []valueType{list, list}, gives: list, says: "each item paired with the item at its position in the other list, as a record of left and right, for as long as the shorter list"},
		"indexOf":           {operands: []valueType{anyValue, anyValue}, gives: number, says: "the position of the first item equal to the value, or of the first place a text holds the part; no value when there is none"},
		"min":               {operands: []valueType{anyValue}, repeating: true, gives: number, says: "the smallest of a list of numbers, or of the numbers given"},
		"max":               {operands: []valueType{anyValue}, repeating: true, gives: number, says: "the largest of a list of numbers, or of the numbers given"},
		"average":           {operands: []valueType{list}, gives: number, says: "the mean of a list of numbers; unknown for an empty one"},
		"median":            {operands: []valueType{list}, gives: number, says: "the middle of a list of numbers, the mean of the two middle ones for an even count; unknown for an empty one"},
		"dominant":          {operands: []valueType{list, number}, gives: anyValue, says: "the value at least the share given of a list's items hold; unknown when none does"},
		"windows":           {operands: []valueType{list, number}, gives: list, says: "every run of n items in a row, each a list"},
		"jaccard":           {operands: []valueType{list, list}, gives: number, says: "how many items two lists share against how many they hold together, from 0 to 1"},
		"reaches":           {operands: []valueType{list, anyValue, anyValue}, gives: truth, says: "whether edges, each a record of from and to, lead from the first value to the second"},
		"reachableFrom":     {operands: []valueType{list, anyValue, number}, gives: list, says: "every value the edges lead to from the one given, in at most the steps given (0 for no limit), nearest first"},
		"components":        {operands: []valueType{list}, gives: list, says: "the cycles of a graph of edges, each a list of the values that reach one another, a value alone only when it has an edge to itself"},
		"inCycle":           {operands: []valueType{list, anyValue}, gives: truth, says: "whether a value lies on a cycle of the edges"},
		"supertypes":        {operands: []valueType{anyValue}, gives: list, says: "the symbols of every type a type declaration extends, implements or mixes in, nearest first, through the declarations the codebase holds"},
		"knownSupertypes":   {operands: []valueType{anyValue}, gives: list, says: "the symbols of every type a type declaration extends, implements or mixes in, nearest first, as far as the codebase declares them: a type it does not declare is listed and not walked past"},
		"subtypes":          {operands: []valueType{anyValue}, gives: list, says: "every type declaration of the codebase that extends, implements or mixes in the type, at any depth"},
		"isSubtype":         {operands: []valueType{anyValue, anyValue}, gives: truth, says: "whether the first type is the second or reaches it through what it extends, implements or mixes in; unknown past a type the codebase does not declare"},
		"symbolOf":          {operands: []valueType{node}, gives: text, says: "the symbol a node is or names: its own symbol, what it refers to, or its call's target; unknown where none is resolved"},
		"sameSymbol":        {operands: []valueType{anyValue, anyValue}, gives: truth, says: "whether two nodes or symbols name the same declaration: for local names by the binding they reach, never by spelling"},
		"declarationsOf":    {operands: []valueType{anyValue}, gives: list, says: "every declaration of a symbol, or of what a node names: partial classes and overloads each once"},
		"origin":            {operands: []valueType{anyValue}, gives: text, says: "where a node's declaration lies: project, library or unknown"},
		"moduleOf":          {operands: []valueType{anyValue}, gives: text, says: "the module of a node, or of the declaration a symbol names: its namespace, package or folder; no value for a symbol the project does not declare"},
		"sameModule":        {operands: []valueType{anyValue, anyValue}, gives: truth, says: "whether two nodes or symbols lie in one module"},
		"layerOf":           {operands: []valueType{text}, gives: text, says: "the layer the project's configuration places a module in; unknown for a module in no layer"},
		"reusableInterface": {operands: []valueType{node}, gives: anyValue, says: "the contract an interface could reuse instead: one of its own language with no signature it lacks, more than half of its signatures, that it may depend on under the project's layers, as a record of contract and exact (whether the signatures are the same); no value when there is none"},
		"reached":           {operands: []valueType{node}, gives: list, says: "the symbols a function or type calls, constructs and names, each once: the resources it reaches"},
		"commentsOf":        {operands: []valueType{node}, gives: list, says: "the comments of a node, the ones above it and inside it, each a comment record as select comment reads them"},
		"rowsOf":            {operands: []valueType{text, anyValue}, optional: 1, gives: list, says: "the rows of one of the find's relations whose fields equal a record's: every row when no record is given"},
		"hasRow":            {operands: []valueType{text, anyValue}, gives: truth, says: "whether one of the find's relations holds a row whose fields equal a record's"},
		"countOf":           {operands: []valueType{text, anyValue}, gives: number, says: "how many rows of one of the find's relations have fields equal to a record's"},
		"rowOf":             {operands: []valueType{text, anyValue}, gives: anyValue, says: "the one row of a relation whose fields equal a record's; unknown when none or several do"},
	}, map[string]computation{
		"rest": listed(func(items []value, _ []value) value { return known(items[min(1, len(items)):]) }),
		"take": listed(func(items []value, operands []value) value {
			count, read := wholeNumber(operands[1])
			if !read {
				return unknown
			}

			return known(items[:clamp(count, len(items))])
		}),
		"drop": listed(func(items []value, operands []value) value {
			count, read := wholeNumber(operands[1])
			if !read {
				return unknown
			}

			return known(items[clamp(count, len(items)):])
		}),
		"slice": func(_ evaluation, operands []value) value {
			from, readFrom := wholeNumber(operands[1])
			to, readTo := wholeNumber(operands[2])
			if !readFrom || !readTo {
				return unknown
			}
			if text, isText := operands[0].held.(string); operands[0].known && isText {
				runes := []rune(text)
				start, end := bounds(from, to, len(runes))

				return known(string(runes[start:end]))
			}
			items, isList := listOf(operands[0])
			if !isList {
				return unknown
			}
			start, end := bounds(from, to, len(items))

			return known(items[start:end])
		},
		"append": func(_ evaluation, operands []value) value {
			var joined []value
			for _, each := range operands {
				items, isList := listOf(each)
				if !isList {
					return unknown
				}
				joined = append(joined, items...)
			}

			return known(joined)
		},
		"distinct": listed(func(items []value, _ []value) value {
			kept, read := distinctItems(items)
			if !read {
				return unknown
			}

			return known(kept)
		}),
		"union": twoLists(func(first, second []value) value {
			kept, read := distinctItems(append(slices.Clone(first), second...))
			if !read {
				return unknown
			}

			return known(kept)
		}),
		"intersect":  twoLists(func(first, second []value) value { return keptWhere(first, second, engine.True) }),
		"difference": twoLists(func(first, second []value) value { return keptWhere(first, second, engine.False) }),
		"subset":     twoLists(func(first, second []value) value { return truthValue(subset(first, second)) }),
		"disjoint": twoLists(func(first, second []value) value {
			answer := engine.True
			for _, item := range first {
				if answer = answer.And(heldIn(second, item).Not()); answer == engine.False {
					break
				}
			}

			return truthValue(answer)
		}),
		"sameItems": twoLists(func(first, second []value) value {
			return truthValue(subset(first, second).And(subset(second, first)))
		}),
		"zip": twoLists(func(first, second []value) value {
			pairs := make([]value, min(len(first), len(second)))
			for at := range pairs {
				pairs[at] = known(record{"left": first[at], "right": second[at]})
			}

			return known(pairs)
		}),
		"indexOf": func(_ evaluation, operands []value) value {
			if text, isText := operands[0].held.(string); operands[0].known && isText {
				part, isPart := operands[1].held.(string)
				if !operands[1].known || !isPart {
					return unknown
				}
				if at := strings.Index(text, part); at >= 0 {
					return known(float64(len([]rune(text[:at]))))
				}

				return known(nil)
			}
			items, isList := listOf(operands[0])
			if !isList {
				return unknown
			}
			for at, item := range items {
				switch equal(item, operands[1]) {
				case engine.True:
					return known(float64(at))
				case engine.Unknown:
					return unknown
				}
			}

			return known(nil)
		},
		"min":     numbersFolded(func(numbers []float64) (float64, bool) { return slices.Min(numbers), len(numbers) > 0 }),
		"max":     numbersFolded(func(numbers []float64) (float64, bool) { return slices.Max(numbers), len(numbers) > 0 }),
		"average": numbersFolded(func(numbers []float64) (float64, bool) { return sum(numbers) / float64(len(numbers)), len(numbers) > 0 }),
		"median": numbersFolded(func(numbers []float64) (float64, bool) {
			if len(numbers) == 0 {
				return 0, false
			}
			sorted := slices.Sorted(slices.Values(numbers))
			middle := len(sorted) / 2
			if len(sorted)%2 == 0 {
				return (sorted[middle-1] + sorted[middle]) / 2, true
			}

			return sorted[middle], true
		}),
		"dominant": listed(func(items []value, operands []value) value {
			share, isNumber := operands[1].held.(float64)
			if !operands[1].known || !isNumber || len(items) == 0 {
				return unknown
			}
			counts := map[string]int{}
			for _, item := range items {
				if !item.known {
					return unknown
				}
				counts[item.String()]++
			}
			for _, item := range items {
				if float64(counts[item.String()]) >= share*float64(len(items)) {
					return item
				}
			}

			return unknown
		}),
		"windows": listed(func(items []value, operands []value) value {
			size, read := wholeNumber(operands[1])
			if !read || size < 1 {
				return unknown
			}
			var runs []value
			for at := 0; at+size <= len(items); at++ {
				runs = append(runs, known(items[at:at+size]))
			}

			return known(runs)
		}),
		"jaccard": twoLists(func(first, second []value) value {
			together, read := distinctItems(append(slices.Clone(first), second...))
			shared := keptWhere(first, second, engine.True)
			sharedItems, isList := listOf(shared)
			if !read || !isList {
				return unknown
			}
			distinctShared, _ := distinctItems(sharedItems)
			if len(together) == 0 {
				return known(1.0)
			}

			return known(float64(len(distinctShared)) / float64(len(together)))
		}),
		"reaches": func(_ evaluation, operands []value) value {
			edges, read := edgesOf(operands[0])
			if !read || !operands[1].known || !operands[2].known {
				return unknown
			}
			return truthValue(engine.TruthOf(slices.Contains(reachedFrom(edges, operands[1].String(), 0), operands[2].String())))
		},
		"reachableFrom": func(_ evaluation, operands []value) value {
			edges, read := edgesOf(operands[0])
			steps, readSteps := wholeNumber(operands[2])
			if !read || !readSteps || !operands[1].known {
				return unknown
			}
			var found []value
			for _, each := range reachedFrom(edges, operands[1].String(), steps) {
				found = append(found, edges.values[each])
			}

			return known(found)
		},
		"components": func(_ evaluation, operands []value) value {
			edges, read := edgesOf(operands[0])
			if !read {
				return unknown
			}
			var cycles []value
			for _, component := range edges.components() {
				members := make([]value, len(component))
				for at, each := range component {
					members[at] = edges.values[each]
				}
				cycles = append(cycles, known(members))
			}

			return known(cycles)
		},
		"inCycle": func(_ evaluation, operands []value) value {
			edges, read := edgesOf(operands[0])
			if !read || !operands[1].known {
				return unknown
			}
			wanted := operands[1].String()

			return truthValue(engine.TruthOf(slices.ContainsFunc(edges.components(), func(component []string) bool { return slices.Contains(component, wanted) })))
		},
		"supertypes": func(e evaluation, operands []value) value {
			declared, read := e.typeDeclared(operands[0])
			if !read {
				return unknown
			}
			found, complete := e.supertypes(declared)
			if !complete {
				return unknown
			}

			return known(textValues(found))
		},
		"knownSupertypes": func(e evaluation, operands []value) value {
			declared, read := e.typeDeclared(operands[0])
			if !read {
				return unknown
			}
			found, _ := e.supertypes(declared)

			return known(textValues(found))
		},
		"subtypes": func(e evaluation, operands []value) value {
			declared, read := e.typeDeclared(operands[0])
			if !read || e.codebase == nil {
				return unknown
			}
			var found []value
			for _, each := range e.subtypesBySupertype()[declared.Node().Symbol] {
				found = append(found, known(each))
			}

			return known(found)
		},
		"isSubtype": func(e evaluation, operands []value) value {
			declared, read := e.typeDeclared(operands[0])
			wanted := symbolHeld(operands[1])
			if !read || wanted == "" {
				return unknown
			}
			if declared.Node().Symbol == wanted {
				return truthValue(engine.True)
			}
			above, complete := e.supertypes(declared)
			if slices.Contains(above, wanted) {
				return truthValue(engine.True)
			}
			if !complete {
				return unknown
			}

			return truthValue(engine.False)
		},
		"symbolOf": func(_ evaluation, operands []value) value {
			return onNode(operands, func(site engine.Match) value {
				if symbol := symbolOfNode(site); symbol != "" {
					return known(symbol)
				}

				return unknown
			})
		},
		"sameSymbol": func(_ evaluation, operands []value) value {
			first, second := operands[0], operands[1]
			if left, isNode := nodeOf(first); isNode && left.Exists() {
				if right, alsoNode := nodeOf(second); alsoNode && right.Exists() {
					if bound, boundKnown := bindingOf(left); boundKnown {
						if other, otherKnown := bindingOf(right); otherKnown {
							return truthValue(engine.TruthOf(left.Source() == right.Source() && bound == other))
						}
					}
				}
			}
			a, b := symbolHeld(first), symbolHeld(second)
			if a == "" || b == "" {
				return unknown
			}

			return truthValue(engine.TruthOf(a == b))
		},
		"declarationsOf": func(e evaluation, operands []value) value {
			symbol := symbolHeld(operands[0])
			if symbol == "" || e.codebase == nil {
				return unknown
			}

			return matches(e.codebase.Declarations(symbol))
		},
		"origin": func(e evaluation, operands []value) value {
			symbol := symbolHeld(operands[0])
			if symbol == "" || e.codebase == nil {
				return unknown
			}
			declared := e.codebase.Declarations(symbol)
			switch {
			case len(declared) == 0:
				return unknown
			case declared[0].IsInDependency():
				return known("library")
			}

			return known("project")
		},
		"moduleOf": func(e evaluation, operands []value) value {
			return moduleOf(e, operands[0])
		},
		"sameModule": func(e evaluation, operands []value) value {
			return truthValue(equal(moduleOf(e, operands[0]), moduleOf(e, operands[1])))
		},
		"reusableInterface": func(e evaluation, operands []value) value {
			site, isNode := nodeOf(operands[0])
			if !operands[0].known || !isNode || !site.Exists() {
				return unknown
			}
			spelling, spelled := spellingOf(site.Source().Language())
			if !spelled {
				return unknown
			}
			layers := map[string][]string{}
			if declared, isRecord := read(e.parameters, "layers").held.(record); isRecord {
				for name, allowed := range declared {
					items, _ := allowed.held.([]value)
					for _, item := range items {
						if text, isText := item.held.(string); isText {
							layers[name] = append(layers[name], text)
						}
					}
					if _, listed := layers[name]; !listed {
						layers[name] = nil
					}
				}
			}
			public := map[string]bool{}
			if published, isList := read(e.parameters, "publicContracts").held.([]value); isList {
				for _, item := range published {
					if text, isText := item.held.(string); isText {
						public[text] = true
					}
				}
			}
			reuse := site.ReusableInterface(engine.Layers(layers, spelling.separator, spelling.caseSensitive), public)
			if !reuse.Contract.Exists() {
				return known(nil)
			}

			return known(record{"contract": known(reuse.Contract), "exact": known(reuse.Exact)})
		},
		"layerOf": func(e evaluation, operands []value) value {
			module, isText := operands[0].held.(string)
			layers, isRecord := read(e.parameters, "layers").held.(record)
			if !operands[0].known || !isText || !isRecord {
				return unknown
			}
			best := ""
			for name := range layers {
				if (name == module || withinModule(module, name)) && len(name) > len(best) {
					best = name
				}
			}
			if best == "" {
				return unknown
			}

			return known(best)
		},
		"reached": func(_ evaluation, operands []value) value {
			return onNode(operands, func(site engine.Match) value {
				var symbols []string
				for _, each := range descendantMatches(site) {
					if symbol := symbolOfNode(each); symbol != "" && !slices.Contains(symbols, symbol) && each.Node().Kind != "binding" {
						symbols = append(symbols, symbol)
					}
				}

				return known(textValues(symbols))
			})
		},
		"commentsOf": func(_ evaluation, operands []value) value {
			return onNode(operands, func(site engine.Match) value {
				var found []value
				for _, comment := range commentRecords(site) {
					found = append(found, known(comment))
				}

				return known(found)
			})
		},

		"rowsOf": func(e evaluation, operands []value) value {
			rows, read := e.matchingRows(operands)
			if !read {
				return unknown
			}

			return known(rows)
		},
		"hasRow": func(e evaluation, operands []value) value {
			rows, read := e.matchingRows(operands)
			if !read {
				return unknown
			}

			return truthValue(engine.TruthOf(len(rows) > 0))
		},
		"countOf": func(e evaluation, operands []value) value {
			rows, read := e.matchingRows(operands)
			if !read {
				return unknown
			}

			return known(float64(len(rows)))
		},
		"rowOf": func(e evaluation, operands []value) value {
			rows, read := e.matchingRows(operands)
			if !read || len(rows) != 1 {
				return unknown
			}

			return rows[0]
		},
	})
}

func listed(answer func(items []value, operands []value) value) computation {
	return func(_ evaluation, operands []value) value {
		items, isList := listOf(operands[0])
		if !isList {
			return unknown
		}

		return answer(items, operands)
	}
}

func twoLists(answer func(first, second []value) value) computation {
	return func(_ evaluation, operands []value) value {
		first, isFirst := listOf(operands[0])
		second, isSecond := listOf(operands[1])
		if !isFirst || !isSecond {
			return unknown
		}

		return answer(first, second)
	}
}

func wholeNumber(v value) (int, bool) {
	number, isNumber := v.held.(float64)
	if !v.known || !isNumber || number != math.Trunc(number) {
		return 0, false
	}

	return int(number), true
}

func clamp(at, length int) int {
	return max(0, min(at, length))
}

// bounds is a slice's start and end, a negative position counted from the end, each clamped to the length.
func bounds(from, to, length int) (int, int) {
	if from < 0 {
		from += length
	}
	if to < 0 {
		to += length
	}
	start, end := clamp(from, length), clamp(to, length)

	return start, max(start, end)
}

func distinctItems(items []value) ([]value, bool) {
	var kept []value
	for _, item := range items {
		switch heldIn(kept, item) {
		case engine.False:
			kept = append(kept, item)
		case engine.Unknown:
			return nil, false
		}
	}

	return kept, true
}

// heldIn says whether a list holds a value equal to the one given.
func heldIn(items []value, wanted value) engine.Truth {
	answer := engine.False
	for _, item := range items {
		if answer = answer.Or(equal(item, wanted)); answer == engine.True {
			break
		}
	}

	return answer
}

// keptWhere is each item of the first list whose being held by the second is the truth given.
func keptWhere(first, second []value, wanted engine.Truth) value {
	var kept []value
	for _, item := range first {
		switch held := heldIn(second, item); {
		case held == engine.Unknown:
			return unknown
		case held == wanted:
			kept = append(kept, item)
		}
	}

	return known(kept)
}

func subset(first, second []value) engine.Truth {
	answer := engine.True
	for _, item := range first {
		if answer = answer.And(heldIn(second, item)); answer == engine.False {
			break
		}
	}

	return answer
}

func sum(numbers []float64) float64 {
	total := 0.0
	for _, number := range numbers {
		total += number
	}

	return total
}

// numbersFolded is an operator over a list of numbers, or over the numbers written as its operands.
func numbersFolded(answer func([]float64) (float64, bool)) computation {
	return func(_ evaluation, operands []value) value {
		items := operands
		if len(operands) == 1 {
			listedItems, isList := listOf(operands[0])
			if !isList {
				return unknown
			}
			items = listedItems
		}
		numbers, read := numbersOf(items)
		if !read {
			return unknown
		}
		total, answered := answer(numbers)
		if !answered {
			return unknown
		}

		return known(total)
	}
}

// keyed answers the iterating operators that key each item by the body: distinctBy, groupItems, sortBy, minBy, maxBy.
func keyed(operator string, items []value, keyOf func(value) value) value {
	keys := make([]value, len(items))
	for at, item := range items {
		if keys[at] = keyOf(item); !keys[at].known {
			return unknown
		}
	}
	switch operator {
	case "distinctBy", "groupItems":
		var order []string
		groups := map[string][]value{}
		values := map[string]value{}
		for at, item := range items {
			written := keys[at].String()
			if _, seen := groups[written]; !seen {
				order = append(order, written)
				values[written] = keys[at]
			}
			groups[written] = append(groups[written], item)
		}
		kept := make([]value, len(order))
		for at, written := range order {
			if operator == "distinctBy" {
				kept[at] = groups[written][0]
			} else {
				kept[at] = known(record{"key": values[written], "items": known(groups[written])})
			}
		}

		return known(kept)
	case "sortBy":
		positions := make([]int, len(items))
		for at := range positions {
			positions[at] = at
		}
		slices.SortStableFunc(positions, func(a, b int) int { return compareValues(keys[a], keys[b]) })
		sorted := make([]value, len(items))
		for at, position := range positions {
			sorted[at] = items[position]
		}

		return known(sorted)
	}
	if len(items) == 0 {
		return known(nil)
	}
	chosen := 0
	for at := range items {
		number, isNumber := keys[at].held.(float64)
		best, _ := keys[chosen].held.(float64)
		if !isNumber {
			return unknown
		}
		if (operator == "minBy" && number < best) || (operator == "maxBy" && number > best) {
			chosen = at
		}
	}

	return items[chosen]
}

// graph is a list of edges read once: each value by its written form, and where each leads.
type graph struct {
	values map[string]value
	next   map[string][]string
	order  []string
}

func edgesOf(v value) (graph, bool) {
	items, isList := listOf(v)
	edges := graph{values: map[string]value{}, next: map[string][]string{}}
	if !isList {
		return edges, false
	}
	add := func(each value) string {
		written := each.String()
		if _, seen := edges.values[written]; !seen {
			edges.values[written] = each
			edges.order = append(edges.order, written)
		}

		return written
	}
	for _, item := range items {
		fields, isRecord := item.held.(record)
		if !item.known || !isRecord || !fields["from"].known || !fields["to"].known {
			return edges, false
		}
		from, to := add(fields["from"]), add(fields["to"])
		edges.next[from] = append(edges.next[from], to)
	}

	return edges, true
}

// reachedFrom is every value the edges lead to from one, nearest first, in at most the steps given (0 for any).
func reachedFrom(edges graph, start string, steps int) []string {
	var found []string
	seen := map[string]bool{}
	frontier := []string{start}
	for depth := 1; len(frontier) > 0 && (steps == 0 || depth <= steps); depth++ {
		var following []string
		for _, at := range frontier {
			for _, to := range edges.next[at] {
				if !seen[to] {
					seen[to] = true
					found = append(found, to)
					following = append(following, to)
				}
			}
		}
		frontier = following
	}

	return found
}

// components is each strongly connected set of values that holds a cycle, found by Tarjan's walk, in first-seen order.
func (g graph) components() [][]string {
	index, low := map[string]int{}, map[string]int{}
	onStack := map[string]bool{}
	var stack []string
	var found [][]string
	counter := 0
	var visit func(at string)
	visit = func(at string) {
		index[at], low[at] = counter, counter
		counter++
		stack = append(stack, at)
		onStack[at] = true
		for _, to := range g.next[at] {
			if _, seen := index[to]; !seen {
				visit(to)
				low[at] = min(low[at], low[to])
			} else if onStack[to] {
				low[at] = min(low[at], index[to])
			}
		}
		if low[at] != index[at] {
			return
		}
		var component []string
		for {
			top := stack[len(stack)-1]
			stack = stack[:len(stack)-1]
			onStack[top] = false
			component = append(component, top)
			if top == at {
				break
			}
		}
		if len(component) > 1 || slices.Contains(g.next[at], at) {
			slices.SortFunc(component, func(a, b string) int { return cmp.Compare(slices.Index(g.order, a), slices.Index(g.order, b)) })
			found = append(found, component)
		}
	}
	for _, at := range g.order {
		if _, seen := index[at]; !seen {
			visit(at)
		}
	}

	return found
}

// typeDeclared is the type declaration a value names: the node itself, or the declaration of its symbol or type.
func (e evaluation) typeDeclared(v value) (engine.Match, bool) {
	if site, isNode := nodeOf(v); isNode && site.Exists() && site.Node().Kind == "type" {
		return site, true
	}
	symbol := symbolHeld(v)
	if symbol == "" || e.codebase == nil {
		return engine.Match{}, false
	}
	for _, declared := range e.codebase.Declarations(symbol) {
		if declared.Node().Kind == "type" {
			return declared, true
		}
	}

	return engine.Match{}, false
}

// supertypes is the symbol of every type a declaration reaches upward, nearest first, and whether every one of them is
// declared in the codebase.
// subtypesBySupertype is every type declaration of the codebase under each type it extends, implements or mixes in, at
// any depth: read once a codebase, so asking for the subtypes of many types costs one walk of the types.
func (e evaluation) subtypesBySupertype() map[string][]engine.Match {
	return engine.Analysis(e.codebase, subtypesKey{}, func(*engine.Codebase) map[string][]engine.Match {
		below := map[string][]engine.Match{}
		for _, each := range e.codebase.WhereKindReadAsContext("type").Get() {
			above, _ := e.supertypes(each)
			for _, symbol := range above {
				below[symbol] = append(below[symbol], each)
			}
		}

		return below
	})
}

type subtypesKey struct{}

func (e evaluation) supertypes(declared engine.Match) ([]string, bool) {
	var found []string
	complete := true
	frontier := []engine.Match{declared}
	for len(frontier) > 0 {
		var following []engine.Match
		for _, at := range frontier {
			for _, role := range []string{"bases", "interfaces", "mixins"} {
				for _, written := range at.ChildrenIn(role) {
					symbol := written.Node().Refers
					if symbol == "" || slices.Contains(found, symbol) {
						complete = complete && symbol != ""
						continue
					}
					found = append(found, symbol)
					if above, read := e.typeDeclared(known(symbol)); read {
						following = append(following, above)
					} else {
						complete = false
					}
				}
			}
			for _, id := range at.Node().Linked("satisfies") {
				satisfied := at.Source().Match(id)
				symbol := satisfied.Node().Symbol
				if symbol == "" || slices.Contains(found, symbol) {
					continue
				}
				found = append(found, symbol)
				following = append(following, satisfied)
			}
		}
		frontier = following
	}

	return found, complete
}

// symbolOfNode is the symbol a node is or names.
func symbolOfNode(site engine.Match) string {
	node := site.Node()
	switch {
	case node.Symbol != "":
		return node.Symbol
	case node.Refers != "":
		return node.Refers
	case node.Target != nil:
		return node.Target.Symbol
	}
	if resolved := site.Typed(); node.Kind == "construction" && resolved != nil {
		return resolved.Name
	}

	return ""
}

// symbolHeld is a value read as a symbol: text, a type's name, or what a node names.
func symbolHeld(v value) string {
	if !v.known {
		return ""
	}
	switch held := v.held.(type) {
	case string:
		return held
	case *contract.Type:
		if held != nil {
			return held.Name
		}
	case engine.Match:
		if held.Exists() {
			return symbolOfNode(held)
		}
	}

	return ""
}

// moduleOf is the module a node lies in, or the module of the declaration a symbol names.
func moduleOf(e evaluation, v value) value {
	if site, isNode := nodeOf(v); isNode && v.known {
		if !site.Exists() {
			return known(nil)
		}

		return known(site.Source().Module)
	}
	symbol := symbolHeld(v)
	if symbol == "" || e.codebase == nil {
		return unknown
	}
	declared := e.codebase.Declarations(symbol)
	if len(declared) == 0 {
		return known(nil)
	}

	return known(declared[0].Source().Module)
}

// rowsNamed is the relation a rowsOf, hasRow, countOf or rowOf call names, checked when the rule loads.
func (e Expr) rowsNamed() (string, bool) {
	if !slices.Contains([]string{"rowsOf", "hasRow", "countOf", "rowOf"}, e.Operator) || len(e.Operands) == 0 {
		return "", false
	}
	name, isText := e.Operands[0].Value.(string)

	return name, isText && !e.Operands[0].IsReference() && !e.Operands[0].IsCall()
}

// matchingRows is the rows of a relation the find builds whose fields equal the record given, or all of them.
func (e evaluation) matchingRows(operands []value) ([]value, bool) {
	name, isText := operands[0].held.(string)
	if !operands[0].known || !isText || e.relations == nil {
		return nil, false
	}
	all := e.relations(name)
	if len(operands) < 2 {
		return all, true
	}
	wanted, isRecord := operands[1].held.(record)
	if !operands[1].known || !isRecord {
		return nil, false
	}
	var found []value
	for _, row := range all {
		answer := engine.True
		for field, asked := range wanted {
			answer = answer.And(equal(read(row, field), asked))
		}
		if answer == engine.Unknown {
			return nil, false
		}
		if answer == engine.True {
			found = append(found, row)
		}
	}

	return found, true
}

func bindingOf(site engine.Match) (int, bool) {
	if site.Node().Kind == "binding" {
		return site.Node().ID, true
	}
	if linked := site.Node().Linked("binding"); len(linked) == 1 {
		return linked[0], true
	}

	return 0, false
}
