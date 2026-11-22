package rule

import (
	"iter"
	"maps"
	"math"
	"slices"
	"strconv"
	"strings"
	"sync"
	"unicode"
	"unicode/utf8"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/prose"
)

// Judged is what a rule made of the nodes it selected: the findings it proved, and the nodes whose verdict an unknown
// decided, which a run reports as coverage it could not give.
type Judged struct {
	Found     []engine.Match
	Undecided []engine.Match
}

// bindings are the names an expression may read, each to its value: a name a loop or a let binds points at the names
// it extends and is read nearest first, so binding one more name copies nothing; names handed in together sit at the root.
type bindings struct {
	names  map[string]value
	name   string
	bound  value
	parent *bindings
}

func (b bindings) with(name string, bound value) bindings {
	return bindings{name: name, bound: bound, parent: &b}
}

func (b *bindings) link(name string, bound value) bindings {
	return bindings{name: name, bound: bound, parent: b}
}

func (b bindings) nearestFirst() iter.Seq[*bindings] {
	return func(yield func(*bindings) bool) {
		at := &b
		for at != nil && yield(at) {
			at = at.parent
		}
	}
}

func (b *bindings) own() map[string]value {
	if b.parent == nil {
		return b.names
	}

	return map[string]value{b.name: b.bound}
}

func (b bindings) record() record {
	fields := record{}
	for at := range b.nearestFirst() {
		for name, held := range at.own() {
			if _, nearer := fields[name]; !nearer {
				fields[name] = held
			}
		}
	}

	return fields
}

func (b bindings) lookup(name string) (value, bool) {
	for at := range b.nearestFirst() {
		if at.parent == nil {
			held, isBound := at.names[name]

			return held, isBound
		}
		if at.name == name {
			return at.bound, true
		}
	}

	return unknown, false
}

// evaluation is one rule asked of one selected node.
type evaluation struct {
	definitions map[string]definition
	site        engine.Match
	parameters  value
	codebase    *engine.Codebase
	// relations is the rows of a relation the find builds, by its name, for an expression that reads them.
	relations func(name string) []value
	graphs    *sync.Map
	kept      *keptAnswers
}

// Judge asks the rule of every node it selects: selection first, then where, then the let bindings in order, then
// having, then emit. A node is a finding only when where and having are proven true.
func (r Rule) Judge(codebase *engine.Codebase) Judged {
	asked := r.compiled()
	written := asked.Find
	if written.From != nil {
		return judgeRows(asked, codebase, r.parameters())
	}
	run := rows{evaluation{definitions: asked.Definitions, parameters: r.parameters(), codebase: codebase}, codebase, written.Relations, map[string][]value{}}
	parameters, relations, graphs, kept := r.parameters(), run.relationRows, &sync.Map{}, keepingAnswers(asked.Definitions)
	asking := func(site engine.Match) evaluation {
		return evaluation{definitions: asked.Definitions, site: site, parameters: parameters, codebase: codebase, relations: relations, graphs: graphs, kept: kept}
	}
	var judged Judged
	note := func(site, emitted engine.Match, verdict engine.Truth) {
		switch verdict {
		case engine.True:
			judged.Found = append(judged.Found, emitted)
		case engine.Unknown:
			judged.Undecided = append(judged.Undecided, site)
		}
	}
	kinds, _ := selected(written.Select)
	if slices.Contains(kinds, commentSource) {
		for _, comment := range commentsOf(codebase) {
			site, _ := nodeOf(comment["location"])
			emitted, verdict := asking(site).judgeBound(written, known(comment), site)
			note(site, emitted, verdict)
		}
		kinds = slices.DeleteFunc(slices.Clone(kinds), func(kind string) bool { return kind == commentSource })
		if len(kinds) == 0 {
			return judged
		}
	}
	for _, site := range codebase.WhereKind(kinds...).Get() {
		emitted, verdict := asking(site).judge(written, site)
		note(site, emitted, verdict)
	}

	return judged
}

// AdviceFor is the line the rule's advice expression writes for a finding, read with the finding bound as the
// selected node; empty when the rule writes none or it says nothing.
func (r Rule) AdviceFor(finding engine.Match) string {
	asked := r.compiled()
	if asked.Find.Advice == nil || !finding.Exists() {
		return ""
	}
	advice := evaluation{definitions: asked.Definitions, site: finding, parameters: r.parameters(), codebase: finding.Codebase()}
	advice.relations = rows{advice, finding.Codebase(), asked.Find.Relations, map[string][]value{}}.relationRows

	return stringOf(advice.evaluate(*asked.Find.Advice, bindings{}.with(asked.Find.As, known(finding))))
}

func (r Rule) evaluate(codebase *engine.Codebase) []engine.Match {
	return r.Judge(codebase).Found
}

func (e evaluation) judge(written find, site engine.Match) (engine.Match, engine.Truth) {
	return e.judgeBound(written, known(site), site)
}

func (e evaluation) judgeBound(written find, selected value, site engine.Match) (engine.Match, engine.Truth) {
	bound := bindings{}.with(written.As, selected)
	if written.Where != nil {
		if verdict := truthOf(e.evaluate(*written.Where, bound)); verdict != engine.True {
			return engine.Match{}, verdict
		}
	}
	for _, each := range written.Let {
		bound = bound.with(each.Name, e.evaluate(each.Expr, bound))
	}
	if written.Having != nil {
		if verdict := truthOf(e.evaluate(*written.Having, bound)); verdict != engine.True {
			return engine.Match{}, verdict
		}
	}
	if written.Emit == nil {
		return site, engine.True
	}
	emitted, isNode := nodeOf(e.evaluate(*written.Emit, bound))
	if !isNode || !emitted.Exists() {
		return engine.Match{}, engine.Unknown
	}

	return emitted, engine.True
}

func (e evaluation) evaluate(expression Expr, bound bindings) value {
	switch {
	case expression.IsReference():
		reached, isBound := bound.lookup(expression.Name())
		if !isBound && expression.Name() == parameterName {
			reached, isBound = e.parameters, true
		}
		if !isBound {
			return unknown
		}
		for _, segment := range expression.Reference[1:] {
			reached = read(reached, segment)
		}

		return reached
	case expression.IsCall():
		if defined, isDefinition := e.definitions[expression.Operator]; isDefinition {
			own := bindings{}
			arguments := make([]value, len(defined.Args))
			for at, name := range defined.Args {
				arguments[at] = e.evaluate(expression.Operands[at], bound)
				own = own.with(name, arguments[at])
			}

			return e.kept.answer(expression.Operator, e.site, arguments, func() value { return e.evaluate(defined.Expr, own) })
		}

		return e.apply(expression, bound)
	case expression.IsArms():
		return unknown
	}

	return known(expression.Value)
}

// apply evaluates one operator. An operator that decides early leaves the rest of its operands unasked: a false
// conjunct, a true disjunct, a case's arms other than the one selected.
func (e evaluation) apply(expression Expr, bound bindings) value {
	operands := expression.Operands
	operand := func(at int) value { return e.evaluate(operands[at], bound) }
	if compute, isComputed := computed[expression.Operator]; isComputed {
		held := make([]value, len(operands))
		for at := range operands {
			held[at] = operand(at)
		}

		return compute(e, held)
	}
	switch expression.Operator {
	case "and":
		result := engine.True
		for at := range operands {
			if result = result.And(truthOf(operand(at))); result == engine.False {
				break
			}
		}

		return truthValue(result)
	case "or":
		result := engine.False
		for at := range operands {
			if result = result.Or(truthOf(operand(at))); result == engine.True {
				break
			}
		}

		return truthValue(result)
	case "not":
		return truthValue(truthOf(operand(0)).Not())
	case "=":
		return truthValue(equal(operand(0), operand(1)))
	case "!=":
		return truthValue(equal(operand(0), operand(1)).Not())
	case "<", "<=", ">", ">=":
		return truthValue(compare(expression.Operator, operand(0), operand(1)))
	case "count":
		items, isList := listOf(operand(0))
		if !isList {
			return unknown
		}

		return known(float64(len(items)))
	case "filter", "map", "some", "every", "none", "distinctBy", "groupItems", "sortBy", "minBy", "maxBy":
		return e.iterate(expression, bound)
	case "case":
		return e.choose(expression, bound)
	case "exists":
		return truthValue(holdsSomething(operand(0), false))
	case "nonempty":
		return truthValue(holdsSomething(operand(0), true))
	case "literalIs":
		return truthValue(literalIs(operand(0), operand(1)))
	case "literalEquals":
		return truthValue(literalEquals(operand(0), operand(1)))
	case "assignable":
		return truthValue(e.assignable(operand(0), operand(1)))
	case "reportable":
		site, isNode := nodeOf(operand(0))
		if !isNode {
			return unknown
		}

		return truthValue(engine.TruthOf(site.Exists() && !site.IsInDependency()))
	case "hasEffect":
		site, isNode := nodeOf(operand(0))
		effect, isText := operand(1).held.(string)
		if !isNode || !isText || !site.Exists() {
			return unknown
		}

		return truthValue(site.HasEffect(effect))
	case "proven":
		return truthValue(engine.TruthOf(truthOf(operand(0)) == engine.True))
	case "exemptedBy":
		tag, isText := operand(0).held.(string)
		site, isNode := nodeOf(operand(1))
		if !isText || !isNode || !site.Exists() {
			return unknown
		}

		return truthValue(site.ExemptedBy(tag))
	case "identities":
		named, isText := operand(0).held.(string)
		if !isText {
			return unknown
		}

		return known(texts(engine.PackageIdentitiesNamed(named)))
	case "identity":
		named, isText := operand(0).held.(string)
		if !isText {
			return unknown
		}
		if identity, found := engine.PackageIdentityNamed(named); found {
			return known(identity)
		}

		return unknown
	case "overriders":
		site, isNode := nodeOf(operand(0))
		if !isNode || !site.Exists() {
			return unknown
		}
		var found []value
		for _, each := range site.Overriders() {
			found = append(found, known(each))
		}

		return known(found)
	case "referrers":
		site, isNode := nodeOf(operand(0))
		if !isNode || !site.Exists() {
			return unknown
		}
		var found []value
		for _, each := range site.Referrers() {
			found = append(found, known(each))
		}

		return known(found)
	case "declarationOf":
		site, isNode := nodeOf(operand(0))
		if !isNode || !site.Exists() {
			return unknown
		}
		if member, _ := site.Single("member"); site.Kind() == "access" && member.Exists() {
			site = member
		}
		symbol := site.TargetSymbol()
		if symbol == "" {
			symbol = site.Node().Refers
		}
		if bound := site.Node().Linked("binding"); symbol == "" && len(bound) == 1 {
			return known(site.Source().Match(bound[0]))
		}
		if symbol == "" {
			return unknown
		}
		declared := site.Codebase().Declarations(symbol)
		if len(declared) == 0 {
			return known(nil)
		}

		return known(declared[0])
	case "declarationBySymbol":
		symbol, isSymbol := operand(0).held.(string)
		if !operand(0).known {
			return unknown
		}
		if !isSymbol || symbol == "" {
			return known(nil)
		}
		if declared := e.codebase.Declarations(symbol); len(declared) > 0 {
			return known(declared[0])
		}

		return known(nil)
	case "docTags":
		site, isNode := nodeOf(operand(0))
		if !isNode {
			return unknown
		}
		tags := []value{}
		if site.Exists() {
			for _, comment := range commentRecords(site) {
				documented, _ := nodeOf(comment["documents"])
				words, held := comment[derivedAt].held.(commentWords)
				if held && documented.Exists() && documented.Node() == site.Node() && words.comment.Form == "doc" {
					tags = append(tags, tagRecords(words.comment.Comment.Tags)...)
				}
			}
		}

		return known(tags)
	case "typeDeclaration":
		symbol, isSymbol := operand(0).held.(string)
		if !operand(0).known {
			return unknown
		}
		if !isSymbol || symbol == "" {
			return known(nil)
		}
		for _, declared := range e.codebase.Declarations(symbol) {
			if kind := declared.Node().Kind; kind == "type" || kind == "alias" {
				return known(declared)
			}
		}

		return known(nil)
	case "position":
		site, isNode := nodeOf(operand(0))
		if !isNode {
			return unknown
		}
		if !site.Exists() {
			return known(nil)
		}
		if site.Parent().Exists() && site.Parent().Node().Incomplete {
			return unknown
		}
		for at, each := range siblings(site, false) {
			if held, _ := nodeOf(each); held.Node() == site.Node() {
				return known(float64(at))
			}
		}

		return unknown
	case "programEnums":
		return known(programEnums(e.codebase))
	case "programTexts":
		return known(programTexts(e.codebase))
	case "reverse":
		items, isList := listOf(operand(0))
		if !isList {
			return unknown
		}
		turned := slices.Clone(items)
		slices.Reverse(turned)

		return known(turned)
	case "snakeCase":
		name, isText := operand(0).held.(string)
		if !operand(0).known || !isText {
			return unknown
		}
		var snake strings.Builder
		for at, letter := range name {
			if at > 0 && letter >= 'A' && letter <= 'Z' {
				snake.WriteByte('_')
			}
			snake.WriteRune(letter)
		}

		return known(strings.ToLower(snake.String()))
	case "baseName":
		path, isText := operand(0).held.(string)
		if !operand(0).known || !isText {
			return unknown
		}
		base := path[strings.LastIndexAny(path, `/\`)+1:]
		if dot := strings.LastIndex(base, "."); dot > 0 {
			base = base[:dot]
		}

		return known(base)
	case "siblings", "before":
		site, isNode := nodeOf(operand(0))
		if !isNode {
			return unknown
		}
		if !site.Exists() {
			return known([]value{})
		}
		if site.Parent().Exists() && site.Parent().Node().Incomplete {
			return unknown
		}

		return known(siblings(site, expression.Operator == "before"))
	case "ownAncestors":
		site, isNode := nodeOf(operand(0))
		if !isNode {
			return unknown
		}
		var above []value
		for at := site.Parent(); at.Exists() && !at.Node().Is("function-like"); at = at.Parent() {
			above = append(above, known(at))
		}

		return known(above)
	case "trailingCount":
		return e.trailing(expression, bound)
	case "flatten":
		lists, isList := listOf(operand(0))
		if !isList {
			return unknown
		}
		var flat []value
		for _, each := range lists {
			inner, isInner := listOf(each)
			if !isInner {
				return unknown
			}
			flat = append(flat, inner...)
		}

		return known(flat)
	case "reads":
		text, isText := operand(0).held.(string)
		judged, isJudgement := proseJudgements[stringOf(operand(1))]
		if !operand(0).known || !isText || !isJudgement {
			return unknown
		}

		return truthValue(engine.TruthOf(judged(text)))
	case "hasStartingWith":
		items, isList := listOf(operand(0))
		part, isPart := operand(1).held.(string)
		if !isList || !operand(1).known || !isPart {
			return unknown
		}
		if index := e.textIndexOf(items); index != nil {
			return truthValue(engine.TruthOf(index.holdsStartingWith(part)))
		}
		result := engine.False
		for _, item := range items {
			written, isText := item.held.(string)
			if !item.known || !isText {
				result = result.Or(engine.Unknown)

				continue
			}
			if strings.HasPrefix(written, part) {
				return truthValue(engine.True)
			}
		}

		return truthValue(result)
	case "prefixes":
		text, isText := operand(0).held.(string)
		separator, isSeparator := operand(1).held.(string)
		if !operand(0).known || !operand(1).known || !isText || !isSeparator || separator == "" {
			return unknown
		}

		return known(prefixesOf(text, separator))
	case "startsWith":
		text, isText := operand(0).held.(string)
		part, isPart := operand(1).held.(string)
		if !operand(0).known || !operand(1).known || !isText || !isPart {
			return unknown
		}

		return truthValue(engine.TruthOf(strings.HasPrefix(text, part)))
	case "keys":
		held, isRecord := operand(0).held.(record)
		if !operand(0).known || !isRecord {
			return unknown
		}
		names := slices.Sorted(maps.Keys(held.whole()))
		keys := make([]value, len(names))
		for at, name := range names {
			keys[at] = known(name)
		}

		return known(keys)
	case "get":
		held, isRecord := operand(0).held.(record)
		name, isName := operand(1).held.(string)
		if !operand(0).known || !isRecord || !isName {
			return unknown
		}
		if field, found := held.field(name); found {
			return field
		}

		return known(nil)
	case "withinModule":
		name, isName := operand(0).held.(string)
		prefix, isPrefix := operand(1).held.(string)
		if !operand(0).known || !operand(1).known || !isName || !isPrefix {
			return unknown
		}

		return truthValue(engine.TruthOf(withinModule(name, prefix)))
	case "contains":
		text, isText := operand(0).held.(string)
		part, isPart := operand(1).held.(string)
		if !operand(0).known || !operand(1).known || !isText || !isPart {
			return unknown
		}

		return truthValue(engine.TruthOf(strings.Contains(text, part)))
	case "isOdd":
		number, isNumber := operand(0).held.(float64)
		if !operand(0).known || !isNumber || number != math.Trunc(number) {
			return unknown
		}

		return truthValue(engine.TruthOf(math.Mod(math.Abs(number), 2) == 1))
	case "list":
		items := make([]value, len(operands))
		for at := range operands {
			items[at] = operand(at)
		}

		return known(items)
	case "last":
		items, isList := listOf(operand(0))
		if !isList {
			return unknown
		}
		if len(items) == 0 {
			return known(nil)
		}

		return items[len(items)-1]
	case "first":
		items, isList := listOf(operand(0))
		if !isList {
			return unknown
		}
		if len(items) == 0 {
			return known(nil)
		}

		return items[0]
	case "text":
		written, isText := key(operand(0))
		if !operand(0).known || !isText {
			return unknown
		}

		return known(written)
	case "toNumber":
		written, isText := operand(0).held.(string)
		if !operand(0).known || !isText {
			return unknown
		}

		return numberOf(written)
	case "length":
		written, isText := operand(0).held.(string)
		if !operand(0).known || !isText {
			return unknown
		}

		return known(float64(utf8.RuneCountInString(written)))
	case "has":
		items, isList := listOf(operand(0))
		if !isList {
			return unknown
		}
		if written, isText := operand(1).held.(string); isText && operand(1).known {
			if index := e.textIndexOf(items); index != nil {
				return truthValue(engine.TruthOf(index.holds(written)))
			}
		}
		result := engine.False
		for _, item := range items {
			if result = result.Or(equal(item, operand(1))); result == engine.True {
				break
			}
		}

		return truthValue(result)
	case "concat":
		var joined strings.Builder
		for at := range operands {
			part := operand(at)
			written, isText := part.held.(string)
			if !part.known || !isText {
				return unknown
			}
			joined.WriteString(written)
		}

		return known(joined.String())
	case "join":
		items, isList := listOf(operand(0))
		separator, isText := operand(1).held.(string)
		if !isList || !operand(1).known || !isText {
			return unknown
		}
		parts := make([]string, 0, len(items))
		for _, item := range items {
			written, isWritten := item.held.(string)
			if !item.known || !isWritten {
				return unknown
			}
			parts = append(parts, written)
		}

		return known(strings.Join(parts, separator))
	case "at":
		items, isList := listOf(operand(0))
		position, isNumber := operand(1).held.(float64)
		if !isList || !operand(1).known || !isNumber || position != math.Trunc(position) {
			return unknown
		}
		if position < 0 || int(position) >= len(items) {
			return known(nil)
		}

		return items[int(position)]
	case "in":
		wanted := operand(0)
		result := engine.False
		for at := range operands[1:] {
			result = result.Or(equal(wanted, operand(at+1)))
		}

		return truthValue(result)
	case "matchesShape":
		site, isNode := nodeOf(operand(0))
		if !isNode || !operands[1].IsArms() {
			return unknown
		}
		if !site.Exists() {
			return known(nil)
		}

		return e.matchesShape(site, operands[1].Arms, bound)
	case "is":
		site, isNode := nodeOf(operand(0))
		named, isText := operand(1).held.(string)
		if !isNode || !isText {
			return unknown
		}

		return truthValue(engine.TruthOf(site.Exists() && site.Node().Is(named)))
	case "sort", "distinctCount", "sum":
		return gathered(expression.Operator, operand(0))
	case "pair":
		return known([]value{operand(0), operand(1)})
	case "encloses":
		return truthValue(encloses(operand(0), operand(1)))
	case "lexLess":
		first, isText := operand(0).held.(string)
		second, alsoText := operand(1).held.(string)
		if !operand(0).known || !operand(1).known || !isText || !alsoText {
			return unknown
		}

		return truthValue(engine.TruthOf(first < second))
	case "syntaxWeight":
		site, isNode := nodeOf(operand(0))
		if !isNode {
			return unknown
		}

		return known(float64(site.SyntaxWeight()))
	}

	return unknown
}

func compare(operator string, a, b value) engine.Truth {
	first, isNumber := a.held.(float64)
	second, alsoNumber := b.held.(float64)
	if !a.known || !b.known || !isNumber || !alsoNumber {
		return engine.Unknown
	}
	switch operator {
	case "<":
		return engine.TruthOf(first < second)
	case "<=":
		return engine.TruthOf(first <= second)
	case ">":
		return engine.TruthOf(first > second)
	}

	return engine.TruthOf(first >= second)
}

// iterate evaluates an operator that binds a name to each item of a list in turn, for its last operand alone. An item
// whose answer is unknown makes a filter's list unknown, and a quantifier unknown unless another item decides it.
func (e evaluation) iterate(expression Expr, bound bindings) value {
	items, isList := listOf(e.evaluate(expression.Operands[0], bound))
	if !isList {
		return unknown
	}
	name, _ := expression.Operands[1].Value.(string)
	body := expression.Operands[2]
	outer := &bound
	answer := func(item value) value { return e.evaluate(body, outer.link(name, item)) }

	switch expression.Operator {
	case "distinctBy", "groupItems", "sortBy", "minBy", "maxBy":
		return keyed(expression.Operator, items, answer)
	case "map":
		mapped := make([]value, len(items))
		for at, item := range items {
			mapped[at] = answer(item)
		}

		return known(mapped)
	case "filter":
		var kept []value
		for _, item := range items {
			switch truthOf(answer(item)) {
			case engine.True:
				kept = append(kept, item)
			case engine.Unknown:
				return unknown
			}
		}

		return known(kept)
	}

	if expression.Operator == "every" {
		every := engine.True
		for _, item := range items {
			if every = every.And(truthOf(answer(item))); every == engine.False {
				break
			}
		}

		return truthValue(every)
	}

	some := engine.False
	for _, item := range items {
		if some = some.Or(truthOf(answer(item))); some == engine.True {
			break
		}
	}
	if expression.Operator == "some" {
		return truthValue(some)
	}

	return truthValue(some.Not())
}

// choose evaluates the one arm a case's value names, or its last operand when no arm names it; nothing else.
func (e evaluation) choose(expression Expr, bound bindings) value {
	chosen, isKnown := key(e.evaluate(expression.Operands[0], bound))
	if !isKnown {
		return unknown
	}
	if arm, named := expression.Operands[1].Arms[chosen]; named {
		return e.evaluate(arm, bound)
	}
	if len(expression.Operands) == 3 {
		return e.evaluate(expression.Operands[2], bound)
	}

	return unknown
}

// holdsSomething says whether a value holds anything: a node that is there, a list with items; with empty counting
// too, a text that is not empty.
func holdsSomething(v value, empty bool) engine.Truth {
	if !v.known {
		return engine.Unknown
	}
	switch held := v.held.(type) {
	case nil:
		return engine.False
	case engine.Match:
		return engine.TruthOf(held.Exists())
	case []value:
		return engine.TruthOf(len(held) > 0)
	case string:
		return engine.TruthOf(!empty || held != "")
	}

	return engine.True
}

func literalIs(v, named value) engine.Truth {
	site, isNode := nodeOf(v)
	if !v.known {
		return engine.Unknown
	}
	if !isNode || !site.Exists() || site.Node().Kind != "literal" {
		return engine.False
	}

	return equal(fact(site, "literalKind"), named)
}

func literalEquals(v, wanted value) engine.Truth {
	site, isNode := nodeOf(v)
	if !v.known {
		return engine.Unknown
	}
	if !isNode || !site.Exists() || site.Node().Kind != "literal" {
		return engine.False
	}

	return equal(literalValue(site), wanted)
}

// literalValue is a literal's value as a rule compares it: the decimal text of a number literal read as a number.
func literalValue(site engine.Match) value {
	held := fact(site, "value")
	written, isText := held.held.(string)
	kind, _ := fact(site, "literalKind").held.(string)
	if !held.known || !isText || (kind != "integer" && kind != "float") {
		return held
	}

	return numberOf(written)
}

// numberOf is a number written as text: decimal, with digit separators, or an integer in hexadecimal, octal or binary.
func numberOf(written string) value {
	if number, err := strconv.ParseFloat(strings.ReplaceAll(written, "_", ""), 64); err == nil {
		return known(number)
	}
	if number, err := strconv.ParseInt(written, 0, 64); err == nil {
		return known(float64(number))
	}

	return unknown
}

// assignable says whether a type is the type named or one of its supertypes. A type the bridge did not resolve, or one
// whose ancestors lie in code the codebase does not hold, is unknown.
func (e evaluation) assignable(v, named value) engine.Truth {
	resolved, isType := v.held.(*contract.Type)
	if symbol, isSymbol := v.held.(string); isSymbol && symbol != "" {
		resolved, isType = &contract.Type{Kind: "named", Name: symbol}, true
	}
	if site, isNode := nodeOf(v); isNode {
		resolved, isType = site.Typed(), site.Typed() != nil
		if node := site.Node(); !isType && node != nil && node.Kind == "type" && node.Symbol != "" {
			resolved, isType = &contract.Type{Kind: "named", Name: node.Symbol}, true
		}
	}
	base, isText := named.held.(string)
	if identities := engine.PackageIdentitiesNamed(base); len(identities) > 1 {
		answer := engine.False
		for _, identity := range identities {
			if answer = answer.Or(e.assignable(v, known(identity))); answer == engine.True {
				break
			}
		}

		return answer
	} else if len(identities) == 1 {
		base = identities[0]
	}
	if !v.known || !isType || !isText || resolved == nil || resolved.Name == "" {
		return engine.Unknown
	}
	if resolved.Name == base {
		return engine.True
	}
	asked := e.site
	if site, isNode := nodeOf(v); isNode {
		asked = site
	}
	if !asked.Exists() && e.codebase != nil {
		if declared := e.codebase.Declarations(resolved.Name); len(declared) > 0 {
			asked = declared[0]
		}
	}
	if !asked.Exists() {
		return engine.Unknown
	}
	types, typed := asked.Types()
	if !typed {
		return engine.Unknown
	}
	if engine.IsA(types, resolved.Name, base) {
		return engine.True
	}
	if declaration, declared := types.Declaration(resolved.Name); declared && !declaration.IsInDependency() {
		return engine.False
	}

	return engine.Unknown
}

// ownDescendants is every node under a node, in source order, leaving out what a function written inside it holds.
func ownDescendants(site engine.Match) []value {
	var found []value
	var walk func(node *contract.Node)
	walk = func(node *contract.Node) {
		for _, child := range node.Children() {
			found = append(found, known(site.Source().Match(child.ID)))
			if !child.Is("function-like") && child.Kind != "type" && child.Kind != "comprehension" {
				walk(child)
			}
		}
	}
	walk(site.Node())

	return found
}

// read is one step of a reference: a node's property, role, link or fact, a file's, span's or type's field, or the
// same step taken on every item of a list.
func read(from value, segment string) value {
	if !from.known {
		return unknown
	}
	switch held := from.held.(type) {
	case nil:
		return known(nil)
	case engine.Match:
		return readNode(held, segment)
	case []value:
		items := make([]value, len(held))
		for at, item := range held {
			items[at] = read(item, segment)
		}

		return known(items)
	case record:
		field, found := held.field(segment)
		if !found {
			return unknown
		}

		return field
	case *engine.File:
		return fileField(held, segment)
	case *contract.Type:
		return typeField(held, segment)
	}

	return unknown
}

func readNode(site engine.Match, segment string) value {
	if !site.Exists() {
		return known(nil)
	}
	node := site.Node()
	switch segment {
	case "kind":
		return known(node.Kind)
	case "role":
		return known(node.InRole())
	case "file":
		return known(site.Source())
	case "span":
		return known(spanOf(site))
	case "line":
		return known(float64(node.Span.Line))
	case "parent":
		return known(site.Parent())
	case "resolved":
		if node.Resolved == nil && node.Kind == "function" && site.Child("returnType").Typed() != nil {
			return known(&contract.Type{Text: "function", Kind: "function", Returns: site.Child("returnType").Typed(), Origin: "written"})
		}
		if node.Resolved == nil && node.Kind == "type-expression" {
			if written := site.Typed(); written != nil {
				return known(written)
			}
		}
		if node.Resolved == nil {
			return unknown
		}

		return known(node.Resolved)
	case "target":
		if node.Target == nil {
			if site.Source().Knows("target") {
				return known(nil)
			}

			return unknown
		}

		return known(record{"symbol": known(node.Target.Symbol), "type": known(node.Target.Type), "name": known(node.Target.Name)})
	}
	if arity, declared := contract.DeclaredRole(node.Kind, segment); declared && arity != contract.Many {
		first, answer := site.Single(segment)
		if answer == contract.Unknown {
			return unknown
		}

		return known(first)
	}
	if arity, declared := contract.DeclaredRole(node.Kind, segment); declared {
		held, _ := site.In(segment)
		if held.Answer == contract.Unknown {
			return unknown
		}

		return nodes(held.Nodes, arity)
	}
	if arity, declared := contract.DeclaredLink(node.Kind, segment); declared {
		ids := node.Linked(segment)
		if len(ids) == 0 && (node.Incomplete || (segment == "binding" && !site.Source().Knows("refers"))) {
			return unknown
		}
		linked := make([]engine.Match, len(ids))
		for at, id := range ids {
			linked[at] = site.Source().Match(id)
		}

		return nodes(linked, arity)
	}
	if segment == "value" && node.Kind == "literal" {
		return literalValue(site)
	}
	if contract.FactApplies(node.Kind, segment) {
		return fact(site, segment)
	}

	return known(nil)
}

// nodes is what a role or link holds: a list for one that holds many, else its one node, absent when it holds none.
func nodes(held []engine.Match, arity contract.Arity) value {
	if arity == contract.Many {
		items := make([]value, len(held))
		for at, each := range held {
			items[at] = known(each)
		}

		return known(items)
	}
	if len(held) == 0 {
		return known(engine.Match{})
	}

	return known(held[0])
}

// gathered is one answer over a whole list: its items sorted, how many distinct values it holds, or the sum of its
// numbers. An unknown list, or an unknown item, makes it unknown.
func gathered(operator string, v value) value {
	items, isList := listOf(v)
	if !isList || slices.ContainsFunc(items, func(item value) bool { return !item.known }) {
		return unknown
	}
	switch operator {
	case "sort":
		sorted := slices.Clone(items)
		slices.SortStableFunc(sorted, compareValues)

		return known(sorted)
	case "distinctCount":
		distinct := map[string]bool{}
		for _, item := range items {
			distinct[item.String()] = true
		}

		return known(float64(len(distinct)))
	}
	total := 0.0
	for _, item := range items {
		number, isNumber := item.held.(float64)
		if !isNumber {
			return unknown
		}
		total += number
	}

	return known(total)
}

// encloses says whether one module holds the other: the other's name starts with it and goes on past a separator, as
// App\Ui holds App\Ui\Shared, shop.ui holds shop.ui.cart and a/b holds a/b/c, whatever the language spells it with.
func encloses(outer, inner value) engine.Truth {
	first, isText := outer.held.(string)
	second, alsoText := inner.held.(string)
	if !outer.known || !inner.known || !isText || !alsoText {
		return engine.Unknown
	}
	if len(second) <= len(first) || !strings.HasPrefix(second, first) {
		return engine.False
	}
	next, _ := utf8.DecodeRuneInString(second[len(first):])

	return engine.TruthOf(!unicode.IsLetter(next) && !unicode.IsDigit(next) && next != '_')
}

// proseJudgements are what the reads operator may ask of a name or a text, each the prose package's one judgement.
var proseJudgements = map[string]func(string) bool{
	"third-person": prose.IsThirdPerson, "question": prose.ReadsAsQuestion, "bare-state": prose.IsBareStateName,
	"bare-verb": prose.IsBareVerb, "relational-compound": prose.IsRelationalCompound, "history": prose.NarratesHistory,
	"strawman": prose.DefendsAgainstStrawman, "fixture-marker": prose.IsFixtureMarker, "non-entity": prose.IsNonEntity,
}

func stringOf(v value) string {
	text, _ := v.held.(string)

	return text
}

// followed is a node and each node its role holds in turn while that node is of the kind: an if and its else-ifs.
func followed(site engine.Match, role, kind string) []value {
	var chain []value
	for at := site; at.Exists() && (kind == "" || at.Node().Is(kind)); {
		chain = append(chain, known(at))
		next := linkedMatches(at, role)
		if held, err := at.In(role); err == nil {
			next = held.Nodes
		}
		if len(next) != 1 {
			break
		}
		at = next[0]
	}

	return chain
}

// siblings is every node in the same role of the node's parent, in order; with before, only those ahead of it.
func siblings(site engine.Match, before bool) []value {
	held, err := site.Parent().In(site.Node().InRole())
	if err != nil {
		return nil
	}
	var found []value
	for _, each := range held.Nodes {
		if before && each.Node() == site.Node() {
			break
		}
		found = append(found, known(each))
	}

	return found
}

// trailing counts the items at the end of a list for which the body holds, back to the first for which it does not;
// unknown when one of those it reads is unknown.
func (e evaluation) trailing(expression Expr, bound bindings) value {
	items, isList := listOf(e.evaluate(expression.Operands[0], bound))
	if !isList {
		return unknown
	}
	name, _ := expression.Operands[1].Value.(string)
	count := 0
	outer := &bound
	for at := len(items) - 1; at >= 0; at-- {
		switch truthOf(e.evaluate(expression.Operands[2], outer.link(name, items[at]))) {
		case engine.True:
			count++
		case engine.Unknown:
			return unknown
		default:
			return known(float64(count))
		}
	}

	return known(float64(count))
}

// leaves is what a run of nested nodes of one kind holds that is not of that kind, in source order: the parts of
// a && b && c, however the parser nested them.
func leaves(site engine.Match, kind, role string) []value {
	var found []value
	for _, child := range site.Children() {
		if child.Node().Kind == kind && (role == "" || child.Node().InRole() == role) {
			found = append(found, leaves(child, kind, role)...)

			continue
		}
		found = append(found, known(child))
	}

	return found
}

// programEnums is the case values of every enum the program declares, one list per enum: a case's literal value, or
// its name where it has none. An enum of fewer than two cases says nothing about a vocabulary and is left out.
// programTexts is the value of every string literal the program writes, once each: what a key a file declares may be
// read as anywhere.
func programTexts(codebase *engine.Codebase) []value {
	return engine.Analysis(codebase, "rule-program-texts", func(codebase *engine.Codebase) []value {
		seen := map[string]bool{}
		var texts []value
		for _, literal := range codebase.WhereKind("literal").Get() {
			if kind, _ := readNode(literal, "literalKind").held.(string); kind != "string" {
				continue
			}
			if written, isText := readNode(literal, "value").held.(string); isText && !seen[written] {
				seen[written] = true
				texts = append(texts, known(written))
			}
		}

		return texts
	})
}

func programEnums(codebase *engine.Codebase) []value {
	return engine.Analysis(codebase, "rule-program-enums", func(codebase *engine.Codebase) []value {
		var enums []value
		for _, declared := range codebase.WhereKind("type").Get() {
			if form, _ := readNode(declared, "form").held.(string); form != "enum" {
				continue
			}
			members, _ := listOf(readNode(declared, "writtenMembers"))
			var cases []value
			for _, member := range members {
				held, _ := nodeOf(member)
				if held.Node().Kind != "enum-case" {
					continue
				}
				word := readNode(held, "name")
				if written, isNode := nodeOf(readNode(held, "value")); isNode && written.Exists() {
					word = readNode(written, "value")
				}
				cases = append(cases, word)
			}
			if len(cases) >= 2 {
				enums = append(enums, known(cases))
			}
		}

		return enums
	})
}

// withinModule says whether a module is the prefix module or one inside it, whatever separator the language spells
// its modules with: a PHP namespace, a Python or C# dotted name and a path read alike.
func withinModule(name, prefix string) bool {
	spelled := strings.NewReplacer(`\`, "/", ".", "/", "::", "/")
	name, prefix = strings.Trim(spelled.Replace(name), "/"), strings.Trim(spelled.Replace(prefix), "/")

	return prefix == "" || name == prefix || strings.HasPrefix(name, prefix+"/")
}
