package rule

import (
	"maps"
	"math"
	"path"
	"reflect"
	"regexp"
	"slices"
	"strings"
	"sync"
	"unicode"
	"unicode/utf8"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/prose"
)

// The operators over values, numbers, text, names and paths: each answers from the values it is given alone.
func init() {
	define(map[string]operator{
		"versionAtLeast": {operands: []valueType{text, text}, gives: truth, says: "whether a version is the one given or later: 10.2.1 is at least 10.2"},
		"constant":       {operands: []valueType{node}, gives: anyValue, says: "the value a literal writes or the compiler folded an expression to; unknown where neither says"},
		"isConstant":     {operands: []valueType{node}, gives: truth, says: "whether a value is fixed before the program runs: a literal, a folded expression, or a reference to an enum case or a constant; false only where the compiler ran and folded nothing"},
		"valueKind":      {operands: []valueType{anyValue}, gives: text, says: "what a known value is: text, number, truth, list, node, record, type or null"},
		"coalesce":       {operands: []valueType{anyValue, anyValue}, repeating: true, gives: anyValue, says: "the first value that is known and not absent; unknown when none is"},
		"possible":       {operands: []valueType{truth}, gives: truth, says: "true unless the truth is proven false: unknown reads as true, the opposite of proven"},
		"isUnknown":      {operands: []valueType{anyValue}, gives: truth, says: "whether the value is one the tree cannot state; always known"},
		"record":         {operands: []valueType{anyValue, anyValue}, repeating: true, gives: anyValue, says: "a record of the fields named and their values, written name, value, name, value"},
		"emptyValue":     {operands: []valueType{node, text}, gives: truth, says: "whether a literal is empty in the sense named: nothing (null or undefined), blank-string, zero, false, empty-collection, or empty-scalar (any of the blank string, zero and false)"},
		"defaultOf":      {operands: []valueType{node}, gives: node, says: "the value an expression falls back to when its subject is absent: the right side of ?? or ?:, the default a library's lookup is given; no node when it has none"},
		"nullForgiving":  {operands: []valueType{node}, gives: truth, says: "whether an expression only tells the checker a value is there: x! or a TypeScript assertion cast"},
		"keyedStoreRead": {operands: []valueType{node}, gives: anyValue, says: "the store and key an index or a library's keyed read reads, as a record of store and key; no value for another expression"},
		"literalValue":   {operands: []valueType{node}, gives: anyValue, says: "the value a wholly literal expression writes: a literal's value, a list for a list literal, a record for a keyed one; unknown where any part is computed"},
		"paths":          {operands: []valueType{anyValue, text}, gives: list, says: "the path of every value a record holds nested, each key joined by the separator: app.name for {app: {name: …}}"},
		"add":            {operands: []valueType{number, number}, repeating: true, gives: number, says: "the sum of the numbers"},
		"subtract":       {operands: []valueType{number, number}, gives: number, says: "the first number less the second"},
		"multiply":       {operands: []valueType{number, number}, repeating: true, gives: number, says: "the product of the numbers"},
		"divide":         {operands: []valueType{number, number}, gives: number, says: "the first number divided by the second; unknown for a zero divisor"},
		"remainder":      {operands: []valueType{number, number}, gives: number, says: "what is left of the first number after dividing it by the second; unknown for a zero divisor"},
		"abs":            {operands: []valueType{number}, gives: number, says: "the number without its sign"},
		"floor":          {operands: []valueType{number}, gives: number, says: "the largest whole number not above it"},
		"ceil":           {operands: []valueType{number}, gives: number, says: "the smallest whole number not below it"},
		"round":          {operands: []valueType{number}, gives: number, says: "the nearest whole number, a half rounded away from zero"},
		"between":        {operands: []valueType{number, number, number}, gives: truth, says: "whether a number lies from the low to the high one, both included"},
		"range":          {operands: []valueType{number, number}, gives: list, says: "the whole numbers from the first up to the second, both included"},
		"lower":          {operands: []valueType{text}, gives: text, says: "the text in lower case"},
		"upper":          {operands: []valueType{text}, gives: text, says: "the text in upper case"},
		"trim":           {operands: []valueType{text}, gives: text, says: "the text without the white space around it"},
		"endsWith":       {operands: []valueType{text, text}, gives: truth, says: "whether the text ends with the part"},
		"matchesText":    {operands: []valueType{text, text}, gives: truth, says: "whether the text matches a pattern, written in Go's regular expressions and checked when the rule loads"},
		"replace":        {operands: []valueType{text, text, text}, gives: text, says: "the text with every occurrence of the second replaced by the third"},
		"stripPrefix":    {operands: []valueType{text, text}, gives: text, says: "the text without the part at its start; unchanged when it does not start with it"},
		"stripSuffix":    {operands: []valueType{text, text}, gives: text, says: "the text without the part at its end; unchanged when it does not end with it"},
		"split":          {operands: []valueType{text, text}, gives: list, says: "the pieces of the text between each separator"},
		"lines":          {operands: []valueType{text}, gives: list, says: "each line of the text"},
		"words":          {operands: []valueType{text}, gives: list, says: "the words of a name in lower case, split at camel case, snake case, kebab case and acronyms: HTTPServerError reads http, server, error"},
		"stem":           {operands: []valueType{text}, gives: text, says: "the stem the prose package compares a word by"},
		"contentWords":   {operands: []valueType{text}, gives: list, says: "the stems of a text's content words, the small words left out"},
		"caseStyle":      {operands: []valueType{text}, gives: text, says: "how a name is written: camel, pascal, snake, screaming-snake, kebab, lower, upper or mixed; a one-word lower-case name is lower"},
		"isCase":         {operands: []valueType{text, text}, gives: truth, says: "whether a name is written in the style named, a one-word name counting where the style allows it"},
		"toCase":         {operands: []valueType{text, text}, gives: text, says: "a name written again in the style named: camel, pascal, snake, screaming-snake or kebab"},
		"mood":           {operands: []valueType{text}, gives: text, says: "what the prose package reads a name's leading word as: imperative, third-person, question, state or non-entity; unknown for a word it does not know"},
		"commonPrefix":   {operands: []valueType{list}, gives: text, says: "the words every name of a list starts with, joined in lower case by spaces; empty when they share none"},
		"distance":       {operands: []valueType{text, text}, gives: number, says: "how many characters must be added, removed or changed to turn one text into the other"},
		"dirName":        {operands: []valueType{text}, gives: text, says: "a path without its last segment"},
		"extension":      {operands: []valueType{text}, gives: text, says: "the part of a path's last segment after its last dot, without the dot; empty when there is none"},
		"pathSegments":   {operands: []valueType{text}, gives: list, says: "the segments of a path, . left out and .. taking the segment before it away"},
		"cleanPath":      {operands: []valueType{list}, gives: text, says: "the segments joined into one path by slashes"},
		"writtenPath":    {operands: []valueType{node}, gives: anyValue, says: "the path a text literal, or a joining of text literals, writes, as a record of root (a slash or nothing) and segments; unknown where a part is computed"},
		"pathFits":       {operands: []valueType{text, text}, gives: truth, says: "whether a path fits a route template, a {name} segment standing for any one segment"},
	}, map[string]computation{
		"versionAtLeast": func(_ evaluation, operands []value) value {
			version, wanted, read := texts2(operands)
			if !read {
				return unknown
			}

			return truthValue(engine.TruthOf(engine.InVersionRange(strings.TrimPrefix(version, "v"), ">="+strings.TrimPrefix(wanted, "v"))))
		},
		"constant": func(_ evaluation, operands []value) value {
			site, isNode := nodeOf(operands[0])
			if !isNode || !site.Exists() {
				return unknown
			}
			if site.Node().Kind == "literal" {
				return literalValue(site)
			}

			return constantOf(site)
		},
		"isConstant": func(e evaluation, operands []value) value {
			site, isNode := nodeOf(operands[0])
			if !isNode || !site.Exists() {
				return unknown
			}

			return truthValue(isConstant(e, site))
		},
		"valueKind": func(_ evaluation, operands []value) value {
			if !operands[0].known {
				return unknown
			}

			return known(kindOfValue(operands[0]))
		},
		"coalesce": func(_ evaluation, operands []value) value {
			for _, each := range operands {
				if !each.known {
					return unknown
				}
				if site, isNode := each.held.(engine.Match); each.held == nil || isNode && !site.Exists() {
					continue
				}

				return each
			}

			return unknown
		},
		"possible": func(_ evaluation, operands []value) value {
			return truthValue(engine.TruthOf(truthOf(operands[0]) != engine.False))
		},
		"isUnknown": func(_ evaluation, operands []value) value {
			return truthValue(engine.TruthOf(!operands[0].known))
		},
		"record": func(_ evaluation, operands []value) value {
			if len(operands)%2 != 0 {
				return unknown
			}
			fields := record{}
			for at := 0; at < len(operands); at += 2 {
				name, isText := operands[at].held.(string)
				if !operands[at].known || !isText {
					return unknown
				}
				fields[name] = operands[at+1]
			}

			return known(fields)
		},
		"emptyValue": func(_ evaluation, operands []value) value {
			site, isNode := nodeOf(operands[0])
			class, isText := operands[1].held.(string)
			if !operands[0].known || !isNode || !isText {
				return unknown
			}

			return truthValue(emptyValue(site, class))
		},
		"defaultOf": func(_ evaluation, operands []value) value {
			site, isNode := nodeOf(operands[0])
			if !isNode {
				return unknown
			}

			return defaultOf(site)
		},
		"nullForgiving": func(_ evaluation, operands []value) value {
			site, isNode := nodeOf(operands[0])
			if !isNode {
				return unknown
			}
			node := site.Node()

			return truthValue(engine.TruthOf(site.Exists() && ((node.Kind == "unary" && node.Operator == "assert-present") || (node.Kind == "cast" && node.Form == "assertion"))))
		},
		"keyedStoreRead": func(_ evaluation, operands []value) value {
			site, isNode := nodeOf(operands[0])
			if !isNode || !site.Exists() {
				return unknown
			}

			return keyedStoreRead(site)
		},
		"literalValue": func(_ evaluation, operands []value) value {
			site, isNode := nodeOf(operands[0])
			if !isNode || !site.Exists() {
				return unknown
			}

			return literalOf(site)
		},
		"paths": func(_ evaluation, operands []value) value {
			separator, isText := operands[1].held.(string)
			if !operands[0].known || !operands[1].known || !isText {
				return unknown
			}
			found, complete := nestedPaths(operands[0], "", separator)
			if !complete {
				return unknown
			}

			return known(found)
		},
		"add":       folded(func(a, b float64) (float64, bool) { return a + b, true }),
		"subtract":  folded(func(a, b float64) (float64, bool) { return a - b, true }),
		"multiply":  folded(func(a, b float64) (float64, bool) { return a * b, true }),
		"divide":    folded(func(a, b float64) (float64, bool) { return a / b, b != 0 }),
		"remainder": folded(func(a, b float64) (float64, bool) { return math.Mod(a, b), b != 0 }),
		"abs":       rounded(math.Abs),
		"floor":     rounded(math.Floor),
		"ceil":      rounded(math.Ceil),
		"round":     rounded(math.Round),
		"between": func(_ evaluation, operands []value) value {
			numbers, read := numbersOf(operands)
			if !read {
				return unknown
			}

			return truthValue(engine.TruthOf(numbers[0] >= numbers[1] && numbers[0] <= numbers[2]))
		},
		"range": func(_ evaluation, operands []value) value {
			numbers, read := numbersOf(operands)
			if !read || numbers[0] != math.Trunc(numbers[0]) || numbers[1] != math.Trunc(numbers[1]) {
				return unknown
			}
			var counted []value
			for at := numbers[0]; at <= numbers[1]; at++ {
				counted = append(counted, known(at))
			}

			return known(counted)
		},
		"lower":        textual(strings.ToLower),
		"upper":        textual(strings.ToUpper),
		"trim":         textual(strings.TrimSpace),
		"stem":         textual(prose.Stem),
		"endsWith":     textTest(strings.HasSuffix),
		"matchesText":  textTest(func(text, pattern string) bool { held, err := compiledPattern(pattern); return err == nil && held.MatchString(text) }),
		"stripPrefix":  textPair(strings.TrimPrefix),
		"stripSuffix":  textPair(strings.TrimSuffix),
		"split":        textList(func(text string, operands []string) []string { return strings.Split(text, operands[0]) }, 1),
		"lines":        textList(func(text string, _ []string) []string { return strings.Split(strings.ReplaceAll(text, "\r\n", "\n"), "\n") }, 0),
		"words":        textList(func(text string, _ []string) []string { return nameWords(text) }, 0),
		"contentWords": textList(func(text string, _ []string) []string { return prose.Words(text) }, 0),
		"pathSegments": textList(func(text string, _ []string) []string { return pathSegments(text) }, 0),
		"dirName":      textual(func(text string) string { return path.Dir(strings.ReplaceAll(text, `\`, "/")) }),
		"extension":    textual(func(text string) string { return strings.TrimPrefix(path.Ext(strings.ReplaceAll(text, `\`, "/")), ".") }),
		"caseStyle":    textual(caseStyle),
		"replace": func(_ evaluation, operands []value) value {
			written, read := textsOf(operands)
			if !read {
				return unknown
			}

			return known(strings.ReplaceAll(written[0], written[1], written[2]))
		},
		"isCase": func(_ evaluation, operands []value) value {
			name, style, read := texts2(operands)
			if !read {
				return unknown
			}
			written := caseStyle(name)

			return truthValue(engine.TruthOf(written == style || (written == "lower" && (style == "camel" || style == "snake" || style == "kebab")) ||
				(written == "upper" && style == "screaming-snake") || (written == "pascal" && style == "pascal")))
		},
		"toCase": func(_ evaluation, operands []value) value {
			name, style, read := texts2(operands)
			if !read {
				return unknown
			}
			written, styled := toCase(nameWords(name), style)
			if !styled {
				return unknown
			}

			return known(written)
		},
		"mood": func(_ evaluation, operands []value) value {
			name, isText := operands[0].held.(string)
			if !operands[0].known || !isText {
				return unknown
			}
			for _, each := range []struct {
				mood  string
				reads func(string) bool
			}{{"imperative", prose.IsBareVerb}, {"third-person", prose.IsThirdPerson}, {"question", prose.ReadsAsQuestion},
				{"state", prose.IsBareStateName}, {"non-entity", func(name string) bool { return prose.IsNonEntity(prose.LeadingToken(name)) }}} {
				if each.reads(name) {
					return known(each.mood)
				}
			}

			return unknown
		},
		"commonPrefix": func(_ evaluation, operands []value) value {
			names, read := textItems(operands[0])
			if !read {
				return unknown
			}

			return known(commonPrefix(names))
		},
		"distance": func(_ evaluation, operands []value) value {
			first, second, read := texts2(operands)
			if !read {
				return unknown
			}

			return known(float64(editDistance(first, second)))
		},
		"cleanPath": func(_ evaluation, operands []value) value {
			segments, read := textItems(operands[0])
			if !read {
				return unknown
			}

			return known(strings.Join(segments, "/"))
		},
		"writtenPath": func(_ evaluation, operands []value) value {
			site, isNode := nodeOf(operands[0])
			if !isNode || !site.Exists() {
				return unknown
			}
			written, read := writtenText(site)
			if !read {
				return unknown
			}
			root := ""
			if strings.HasPrefix(written, "/") {
				root = "/"
			}

			return known(record{"root": known(root), "segments": known(textValues(pathSegments(written)))})
		},
		"pathFits": func(_ evaluation, operands []value) value {
			written, template, read := texts2(operands)
			if !read {
				return unknown
			}

			return truthValue(engine.TruthOf(pathFits(written, template)))
		},
	})
}

func texts2(operands []value) (string, string, bool) {
	written, read := textsOf(operands)
	if !read || len(written) < 2 {
		return "", "", false
	}

	return written[0], written[1], true
}

func textsOf(operands []value) ([]string, bool) {
	written := make([]string, len(operands))
	for at, each := range operands {
		text, isText := each.held.(string)
		if !each.known || !isText {
			return nil, false
		}
		written[at] = text
	}

	return written, true
}

func numbersOf(operands []value) ([]float64, bool) {
	numbers := make([]float64, len(operands))
	for at, each := range operands {
		number, isNumber := each.held.(float64)
		if !each.known || !isNumber {
			return nil, false
		}
		numbers[at] = number
	}

	return numbers, true
}

// textItems is a list of texts; not read when the list or any item is unknown or not a text.
func textItems(v value) ([]string, bool) {
	items, isList := listOf(v)
	if !isList {
		return nil, false
	}

	return textsOf(items)
}

func textValues(texts []string) []value {
	values := make([]value, len(texts))
	for at, each := range texts {
		values[at] = known(each)
	}

	return values
}

// folded is an operator over numbers that combines them left to right; unknown where an operand is, or where a step
// says it has no answer.
func folded(step func(a, b float64) (float64, bool)) computation {
	return func(_ evaluation, operands []value) value {
		numbers, read := numbersOf(operands)
		if !read {
			return unknown
		}
		total := numbers[0]
		for _, next := range numbers[1:] {
			answer, answered := step(total, next)
			if !answered {
				return unknown
			}
			total = answer
		}

		return known(total)
	}
}

func rounded(change func(float64) float64) computation {
	return func(_ evaluation, operands []value) value {
		numbers, read := numbersOf(operands)
		if !read {
			return unknown
		}

		return known(change(numbers[0]))
	}
}

func textual(change func(string) string) computation {
	return func(_ evaluation, operands []value) value {
		written, read := textsOf(operands[:1])
		if !read {
			return unknown
		}

		return known(change(written[0]))
	}
}

func textTest(test func(text, part string) bool) computation {
	return func(_ evaluation, operands []value) value {
		text, part, read := texts2(operands)
		if !read {
			return unknown
		}

		return truthValue(engine.TruthOf(test(text, part)))
	}
}

func textPair(change func(text, part string) string) computation {
	return func(_ evaluation, operands []value) value {
		text, part, read := texts2(operands)
		if !read {
			return unknown
		}

		return known(change(text, part))
	}
}

func textList(split func(text string, rest []string) []string, rest int) computation {
	return func(_ evaluation, operands []value) value {
		written, read := textsOf(operands[:1+rest])
		if !read {
			return unknown
		}

		return known(textValues(split(written[0], written[1:])))
	}
}

// patternWritten is the pattern a matchesText call writes as text, checked when the rule loads.
func (e Expr) patternWritten() (string, bool) {
	if e.Operator != "matchesText" || len(e.Operands) != 2 {
		return "", false
	}
	pattern, isText := e.Operands[1].Value.(string)

	return pattern, isText && !e.Operands[1].IsReference() && !e.Operands[1].IsCall()
}

var compiledPatterns sync.Map

// compiledPattern is a pattern compiled once, for the rule that writes it.
func compiledPattern(pattern string) (*regexp.Regexp, error) {
	if held, found := compiledPatterns.Load(pattern); found {
		return held.(*regexp.Regexp), nil
	}
	compiled, err := regexp.Compile(pattern)
	if err == nil {
		compiledPatterns.Store(pattern, compiled)
	}

	return compiled, err
}

func kindOfValue(v value) string {
	switch held := v.held.(type) {
	case nil:
		return "null"
	case string:
		return "text"
	case float64:
		return "number"
	case bool, engine.Truth:
		return "truth"
	case []value:
		return "list"
	case engine.Match:
		if !held.Exists() {
			return "null"
		}

		return "node"
	case record:
		return "record"
	case *contract.Type:
		return "type"
	}

	return "value"
}

// constantOf is the value the compiler folded an expression to, a number as a number; unknown where it folded none.
func constantOf(site engine.Match) value {
	folded := site.Node().ConstantValue
	if folded == nil {
		return unknown
	}
	if folded.Value == nil {
		return known(nil)
	}
	held := plainOf(reflect.ValueOf(folded.Value))
	if written, isText := held.held.(string); isText && folded.Type != "string" {
		if number := numberOf(written); number.known {
			return number
		}
	}

	return held
}

// isConstant says whether a value is fixed before the program runs.
func isConstant(e evaluation, site engine.Match) engine.Truth {
	node := site.Node()
	if node.Kind == "literal" || node.ConstantValue != nil {
		return engine.True
	}
	if node.Kind == "access" {
		site = site.Child("member")
		node = site.Node()
	}
	if node != nil && node.Kind == "reference" && node.Refers != "" {
		for _, declared := range e.codebase.Declarations(node.Refers) {
			for at := declared; at.Exists(); at = at.Parent() {
				if kind := at.Node().Kind; kind == "enum-case" || kind == "constant" {
					return engine.True
				} else if kind != "binding" && kind != "declarator" {
					break
				}
			}
		}
	}
	if site.Source().Knows("constantValue") {
		return engine.False
	}

	return engine.Unknown
}

// emptyValue says whether a literal is empty in the sense named; unknown for an expression the bridge did not fold.
func emptyValue(site engine.Match, class string) engine.Truth {
	if !site.Exists() {
		return engine.False
	}
	node := site.Node()
	if node.Kind == "collection" {
		return engine.TruthOf(class == "empty-collection" && len(site.ChildrenIn("entries")) == 0)
	}
	held := literalOf(site)
	if !held.known {
		return engine.Unknown
	}
	written, isText := held.held.(string)
	number, isNumber := held.held.(float64)
	truthful, isTruth := held.held.(bool)
	nothing := held.held == nil
	switch class {
	case "nothing":
		return engine.TruthOf(nothing)
	case "blank-string":
		return engine.TruthOf(isText && strings.TrimSpace(written) == "")
	case "zero":
		return engine.TruthOf(isNumber && number == 0)
	case "false":
		return engine.TruthOf(isTruth && !truthful)
	case "empty-collection":
		return engine.False
	case "empty-scalar":
		return engine.TruthOf((isText && written == "") || (isNumber && number == 0) || (isTruth && !truthful))
	}

	return engine.Unknown
}

// defaultOf is what an expression falls back to: the replacement of ?? or ?:, or the last argument of a library call
// the package facts say provides a default.
func defaultOf(site engine.Match) value {
	if !site.Exists() {
		return known(nil)
	}
	switch node := site.Node(); {
	case node.Kind == "fallback":
		return known(site.Child("replacement"))
	case node.Kind == "call":
		provides := site.HasEffect("provides-default")
		if provides == engine.Unknown {
			return unknown
		}
		arguments := site.ChildrenIn("arguments")
		if provides == engine.True && len(arguments) > 1 {
			return known(arguments[len(arguments)-1].Child("value"))
		}
	}

	return known(nil)
}

// keyedStoreRead is the store and key an index reads, or a library call the package facts say reads a key.
func keyedStoreRead(site engine.Match) value {
	switch site.Node().Kind {
	case "index":
		return known(record{"store": known(site.Child("subject")), "key": known(site.Child("key"))})
	case "call":
		reads := site.HasEffect("reads-key")
		arguments := site.ChildrenIn("arguments")
		switch {
		case reads == engine.Unknown:
			return unknown
		case reads == engine.True && len(arguments) > 0 && site.Child("callee").Node().Kind == "access":
			return known(record{"store": known(site.Child("callee").Child("subject")), "key": known(arguments[0].Child("value"))})
		}
	}

	return known(nil)
}

// literalOf is the value a wholly literal expression writes: unknown where any part is computed.
func literalOf(site engine.Match) value {
	node := site.Node()
	switch node.Kind {
	case "literal":
		return literalValue(site)
	case "unary":
		inner := literalOf(site.Child("operand"))
		number, isNumber := inner.held.(float64)
		if node.Operator == "negate" && inner.known && isNumber {
			return known(-number)
		}
	case "collection":
		entries := site.ChildrenIn("entries")
		keyed := len(entries) > 0 && !slices.ContainsFunc(entries, func(entry engine.Match) bool { return !entry.Child("key").Exists() })
		items := make([]value, 0, len(entries))
		fields := record{}
		for _, entry := range entries {
			held := literalOf(entry.Child("value"))
			if !held.known {
				return unknown
			}
			if !keyed {
				items = append(items, held)

				continue
			}
			name := literalOf(entry.Child("key"))
			written, isText := key(name)
			if !name.known || !isText {
				return unknown
			}
			fields[written] = held
		}
		if keyed {
			return known(fields)
		}

		return known(items)
	}
	if node.ConstantValue != nil {
		return constantOf(site)
	}

	return unknown
}

// nestedPaths is the path of every value a record holds, nested records walked into.
func nestedPaths(v value, prefix, separator string) ([]value, bool) {
	fields, isRecord := v.held.(record)
	if !v.known || !isRecord {
		return nil, v.known
	}
	var found []value
	for _, name := range slices.Sorted(maps.Keys(fields)) {
		at := name
		if prefix != "" {
			at = prefix + separator + name
		}
		found = append(found, known(at))
		inner, complete := nestedPaths(fields[name], at, separator)
		if !complete {
			return nil, false
		}
		found = append(found, inner...)
	}

	return found, true
}

// nameWords is the words of a name in lower case, split at case changes, digits, underscores, dashes, dots and spaces.
func nameWords(name string) []string {
	var words []string
	var word []rune
	runes := []rune(name)
	flush := func() {
		if len(word) > 0 {
			words = append(words, strings.ToLower(string(word)))
			word = nil
		}
	}
	for at, letter := range runes {
		switch {
		case !unicode.IsLetter(letter) && !unicode.IsDigit(letter):
			flush()

			continue
		case unicode.IsUpper(letter) && len(word) > 0:
			previous := word[len(word)-1]
			nextIsLower := at+1 < len(runes) && unicode.IsLower(runes[at+1])
			if unicode.IsLower(previous) || unicode.IsDigit(previous) || (unicode.IsUpper(previous) && nextIsLower) {
				flush()
			}
		}
		word = append(word, letter)
	}
	flush()

	return words
}

// caseStyle is how a name is written.
func caseStyle(name string) string {
	hasLower := strings.IndexFunc(name, unicode.IsLower) >= 0
	hasUpper := strings.IndexFunc(name, unicode.IsUpper) >= 0
	underscore, dash := strings.Contains(name, "_"), strings.Contains(name, "-")
	first, _ := utf8.DecodeRuneInString(strings.TrimLeft(name, "_"))
	switch {
	case underscore && dash:
		return "mixed"
	case underscore && !hasLower:
		return "screaming-snake"
	case underscore && !hasUpper:
		return "snake"
	case dash && !hasUpper:
		return "kebab"
	case underscore || dash:
		return "mixed"
	case !hasUpper:
		return "lower"
	case !hasLower:
		return "upper"
	case unicode.IsUpper(first):
		return "pascal"
	}

	return "camel"
}

func toCase(words []string, style string) (string, bool) {
	capitalized := func(word string) string {
		first, size := utf8.DecodeRuneInString(word)

		return string(unicode.ToUpper(first)) + word[size:]
	}
	switch style {
	case "snake":
		return strings.Join(words, "_"), true
	case "screaming-snake":
		return strings.ToUpper(strings.Join(words, "_")), true
	case "kebab":
		return strings.Join(words, "-"), true
	case "camel", "pascal":
		var written strings.Builder
		for at, word := range words {
			if at == 0 && style == "camel" {
				written.WriteString(word)
			} else {
				written.WriteString(capitalized(word))
			}
		}

		return written.String(), true
	}

	return "", false
}

func commonPrefix(names []string) string {
	if len(names) == 0 {
		return ""
	}
	shared := nameWords(names[0])
	for _, name := range names[1:] {
		words := nameWords(name)
		length := 0
		for length < len(shared) && length < len(words) && shared[length] == words[length] {
			length++
		}
		shared = shared[:length]
	}

	return strings.Join(shared, " ")
}

// pathSegments is a path's segments, . left out and .. taking the one before it away.
func pathSegments(written string) []string {
	var segments []string
	for _, segment := range strings.FieldsFunc(written, func(letter rune) bool { return letter == '/' || letter == '\\' }) {
		switch segment {
		case ".":
		case "..":
			if len(segments) > 0 {
				segments = segments[:len(segments)-1]
			}
		default:
			segments = append(segments, segment)
		}
	}

	return segments
}

// writtenText is the text a string literal, a concatenation of them or an interpolation of literal parts writes.
func writtenText(site engine.Match) (string, bool) {
	node := site.Node()
	switch {
	case node.Kind == "literal":
		written, isText := literalValue(site).held.(string)

		return written, isText
	case node.Kind == "text":
		written, isText := fact(site, "value").held.(string)

		return written, isText
	case node.Kind == "binary" && (node.Operator == "concatenate" || node.Operator == "add"):
		left, readLeft := writtenText(site.Child("left"))
		right, readRight := writtenText(site.Child("right"))

		return left + right, readLeft && readRight
	case node.Kind == "interpolation":
		var joined strings.Builder
		for _, part := range site.ChildrenIn("parts") {
			written, read := writtenText(part)
			if !read {
				return "", false
			}
			joined.WriteString(written)
		}

		return joined.String(), true
	}

	return "", false
}

func pathFits(written, template string) bool {
	segments, wanted := pathSegments(written), pathSegments(template)
	if len(segments) != len(wanted) {
		return false
	}
	for at, segment := range wanted {
		if strings.HasPrefix(segment, "{") && strings.HasSuffix(segment, "}") {
			continue
		}
		if segment != segments[at] {
			return false
		}
	}

	return true
}
