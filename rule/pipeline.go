package rule

import (
	"bytes"
	"cmp"
	"encoding/json"
	"fmt"
	"slices"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// importsSource is the relation of every project import that reaches another project file: one record per import,
// with the module it is written in (from), the module it reaches (to), the import itself (anchor), its file, the
// file's place among the files (fileOrder), its place in the file (sourceOrder), whether the file is a test (test) and
// whether the run reports the file (held).
const importsSource = "imports"

// source is where a pipeline's rows come from: one relation, a kind or a category, or ["unionAll", …] of relations.
type source []string

func (s *source) UnmarshalJSON(text []byte) error {
	var one string
	if json.Unmarshal(text, &one) == nil {
		*s = source{one}

		return nil
	}
	var union []string
	if err := json.Unmarshal(text, &union); err != nil || len(union) < 3 || union[0] != "unionAll" {
		return fmt.Errorf(`from is a relation, kind or category, or ["unionAll", "a", "b", …]`)
	}
	*s = union[1:]

	return nil
}

// relation is a named set of rows a find builds from others: rows from its source, through its pipeline, then, with
// groupBy, one record per distinct value of the fields named, holding those fields and each aggregate.
type relation struct {
	From      source          `json:"from"`
	As        string          `json:"as,omitempty"`
	Pipeline  []stage         `json:"pipeline,omitempty"`
	GroupBy   []string        `json:"groupBy,omitempty"`
	Aggregate map[string]Expr `json:"aggregate,omitempty"`

	// definitions are those a shared relation's file declares, under the names it is brought in with.
	definitions map[string]definition
	// languages are those a shared relation's file judges: its rows are read from them, whatever the rule reading
	// them judges.
	languages []contract.Language
}

// mapped is the relation with every expression it writes passed through change.
func (r relation) mapped(change func(Expr) Expr) relation {
	changed := r
	changed.Pipeline = make([]stage, len(r.Pipeline))
	for at, step := range r.Pipeline {
		changed.Pipeline[at] = step.mapped(change)
	}
	changed.Aggregate = map[string]Expr{}
	for name, expression := range r.Aggregate {
		changed.Aggregate[name] = change(expression)
	}

	return changed
}

func (s stage) mapped(change func(Expr) Expr) stage {
	changed := s
	for _, written := range []**Expr{&changed.Reject, &changed.Where, &changed.Having, &changed.GroupBy, &changed.Expand, &changed.Emit, &changed.Group, &changed.Distinct} {
		if *written != nil {
			made := change(**written)
			*written = &made
		}
	}
	for _, named := range []*map[string]Expr{&changed.Project, &changed.On} {
		if *named != nil {
			copied := map[string]Expr{}
			for name, expression := range *named {
				copied[name] = change(expression)
			}
			*named = copied
		}
	}
	for _, listed := range []*[]Expr{&changed.OrderBy, &changed.FirstBy} {
		if *listed != nil {
			copied := make([]Expr, len(*listed))
			for at, expression := range *listed {
				copied[at] = change(expression)
			}
			*listed = copied
		}
	}

	return changed
}

// stage is one step of a pipeline: it names exactly one of its operations.
type stage struct {
	Reject  *Expr           `json:"reject,omitempty"`
	Where   *Expr           `json:"where,omitempty"`
	Having  *Expr           `json:"having,omitempty"`
	Project map[string]Expr `json:"project,omitempty"`
	GroupBy *Expr           `json:"groupBy,omitempty"`
	Expand  *Expr           `json:"expand,omitempty"`
	Emit    *Expr           `json:"emit,omitempty"`
	Group   *Expr           `json:"group,omitempty"`
	Join    string          `json:"join,omitempty"`
	On      map[string]Expr `json:"on,omitempty"`
	As      string          `json:"as,omitempty"`
	OrderBy []Expr          `json:"orderBy,omitempty"`
	FirstBy []Expr          `json:"firstBy,omitempty"`
	// Mode is how a join keeps its rows: first (the first matching record, unknown when none), inner (rows with a
	// match), semi (rows with a match, binding nothing), anti (rows with none) or all (one row per match).
	Mode string `json:"mode,omitempty"`
	// Descending orders an orderBy from the largest key down.
	Descending bool  `json:"descending,omitempty"`
	Distinct   *Expr `json:"distinct,omitempty"`
	Limit      *int  `json:"limit,omitempty"`
}

func (s *stage) UnmarshalJSON(text []byte) error {
	type plain stage
	decoder := json.NewDecoder(bytes.NewReader(text))
	decoder.DisallowUnknownFields()
	if err := decoder.Decode((*plain)(s)); err != nil {
		return fmt.Errorf("a pipeline stage: %w", err)
	}
	if named := s.operations(); len(named) != 1 {
		return fmt.Errorf("a pipeline stage names one operation of reject, where, having, project, groupBy, expand, emit, join, orderBy, firstBy, distinct and limit, and this one names %v", named)
	}

	return nil
}

func (s stage) operations() []string {
	var named []string
	for name, written := range map[string]bool{
		"reject": s.Reject != nil, "where": s.Where != nil, "having": s.Having != nil, "project": s.Project != nil,
		"groupBy": s.GroupBy != nil, "expand": s.Expand != nil, "emit": s.Emit != nil, "join": s.Join != "",
		"orderBy": s.OrderBy != nil, "firstBy": s.FirstBy != nil, "distinct": s.Distinct != nil, "limit": s.Limit != nil,
	} {
		if written {
			named = append(named, name)
		}
	}
	slices.Sort(named)

	return named
}

// row is one row of a pipeline: the names bound for it, each name a link that points at the names bound before it, so
// a stage that binds a name copies nothing.
type row struct{ names bindings }

func (r row) with(name string, bound value) row {
	return row{r.names.with(name, bound)}
}

func (r row) scope() bindings {
	return r.names
}

func (r row) named(name string) (value, bool) {
	return r.names.lookup(name)
}

// record is a row, or a relation's record, read as one value: each field by name.
type record map[string]value

func asRecord(each row) value {
	return known(each.names.record())
}

// rows is a pipeline run over one codebase: relations built once each.
type rows struct {
	evaluation
	codebase  *engine.Codebase
	relations map[string]relation
	built     map[string][]value
}

// judgeRows runs a find that reads rows: its source, then each stage in order. What an emit stage names is a finding,
// each anchor once.
func judgeRows(asked file, codebase *engine.Codebase, parameters value) Judged {
	walks := &fileWalks{}
	defer walks.count()
	run := rows{evaluation{definitions: asked.Definitions, parameters: parameters, codebase: codebase, kept: keepingAnswers(asked.Definitions), walks: walks}, codebase, asked.Find.Relations, map[string][]value{}}
	run.evaluation.relations = run.relationRows
	written := asked.Find
	started := run.from(written.From, written.As)
	var judged Judged
	seen := map[*contract.Node]bool{}
	for _, emitted := range run.through(started, written.Pipeline) {
		if site, isNode := nodeOf(emitted); isNode && site.Exists() && !seen[site.Node()] {
			seen[site.Node()] = true
			judged.Found = append(judged.Found, site)
		}
	}

	return judged
}

// from is the rows of a source, each bound to the name given.
func (r rows) from(names source, as string) []row {
	var started []row
	for _, name := range names {
		for _, item := range r.relationRows(name) {
			started = append(started, row{}.with(as, item))
		}
	}

	return started
}

func (r rows) relationFrom(names source, as string) []row {
	var started []row
	for _, name := range names {
		_, isRelation := r.relations[name]
		_, isProgram := programRelations[name]
		items := r.relationRows(name)
		if !isRelation && !isProgram {
			items = r.contextRows(name)
		}
		for _, item := range items {
			started = append(started, row{}.with(as, item))
		}
	}

	return started
}

func (r rows) contextRows(name string) []value {
	key := "read as context: " + name
	if built, done := r.built[key]; done {
		return built
	}
	kinds, _ := selected([]string{name})
	var items []value
	for _, site := range r.codebase.WhereKindReadAsContext(kinds...).Get() {
		items = append(items, known(site))
	}
	r.built[key] = items

	return items
}

func (r rows) relationRows(name string) []value {
	if built, done := r.built[name]; done {
		return built
	}
	var items []value
	program, isProgram := programRelations[name]
	switch defined, isRelation := r.relations[name]; {
	case isRelation && len(defined.languages) > 0:
		items = r.over(r.codebase.Whole().Of(defined.languages...)).build(defined)
	case isRelation:
		items = r.build(defined)
	case isProgram:
		items = programRows(r.codebase, name, program)
	default:
		kinds, _ := selected([]string{name})
		for _, site := range r.codebase.WhereKind(kinds...).Get() {
			items = append(items, known(site))
		}
	}
	r.built[name] = items

	return items
}

// over is the same run over another part of the codebase, its kinds read afresh there.
func (r rows) over(codebase *engine.Codebase) rows {
	other := rows{r.evaluation, codebase, r.relations, map[string][]value{}}
	other.evaluation.codebase = codebase
	other.evaluation.relations = other.relationRows

	return other
}

func (r rows) build(defined relation) []value {
	as := cmp.Or(defined.As, "item")
	projects := slices.ContainsFunc(defined.Pipeline, func(step stage) bool { return step.Project != nil })
	var items []value
	for _, each := range r.stages(r.relationFrom(defined.From, as), defined.Pipeline) {
		if projects {
			items = append(items, asRecord(each))
		} else {
			held, _ := each.named(as)
			items = append(items, held)
		}
	}
	if defined.GroupBy == nil {
		return items
	}

	return r.aggregated(items, defined)
}

// aggregated is one record per distinct value of the fields a relation groups by, with each aggregate over its group.
func (r rows) aggregated(items []value, defined relation) []value {
	var keys []string
	groups := map[string][]value{}
	for _, item := range items {
		var fields []string
		for _, field := range defined.GroupBy {
			fields = append(fields, read(item, field).String())
		}
		groupKey := fmt.Sprint(fields)
		if groups[groupKey] == nil {
			keys = append(keys, groupKey)
		}
		groups[groupKey] = append(groups[groupKey], item)
	}
	var records []value
	for _, groupKey := range keys {
		members := groups[groupKey]
		grouped := record{}
		for _, field := range defined.GroupBy {
			grouped[field] = read(members[0], field)
		}
		for name, expression := range defined.Aggregate {
			grouped[name] = r.evaluate(expression, bindings{}.with("rows", known(members)))
		}
		records = append(records, known(grouped))
	}

	return records
}

// through runs the stages and returns what the emit stages named.
func (r rows) through(started []row, stages []stage) []value {
	var emitted []value
	for _, each := range r.stages(started, stages) {
		if anchor, named := each.named("\x00emit"); named {
			emitted = append(emitted, anchor)
		}
	}

	return emitted
}

func (r rows) stages(current []row, stages []stage) []row {
	for _, each := range stages {
		current = r.stage(current, each)
	}

	return current
}

func (r rows) stage(current []row, step stage) []row {
	switch {
	case step.Reject != nil:
		return slices.DeleteFunc(current, func(each row) bool { return truthOf(r.evaluate(*step.Reject, each.scope())) != engine.False })
	case step.Where != nil:
		return slices.DeleteFunc(current, func(each row) bool { return truthOf(r.evaluate(*step.Where, each.scope())) != engine.True })
	case step.Having != nil:
		return slices.DeleteFunc(current, func(each row) bool { return truthOf(r.evaluate(*step.Having, each.scope())) != engine.True })
	case step.Project != nil:
		for at, each := range current {
			projected := each
			for name, expression := range step.Project {
				projected = projected.with(name, r.evaluate(expression, each.scope()))
			}
			current[at] = projected
		}

		return current
	case step.GroupBy != nil:
		return r.grouped(current, *step.GroupBy)
	case step.Expand != nil:
		return r.expanded(current, *step.Expand)
	case step.Emit != nil:
		for at, each := range current {
			current[at] = each.with("\x00emit", r.evaluate(*step.Emit, each.scope()))
		}

		return current
	case step.Join != "":
		return r.joined(current, step)
	case step.OrderBy != nil:
		slices.SortStableFunc(current, func(a, b row) int {
			if step.Descending {
				return r.order(step.OrderBy, b, a)
			}

			return r.order(step.OrderBy, a, b)
		})

		return current
	case step.Limit != nil:
		return current[:clamp(*step.Limit, len(current))]
	case step.Distinct != nil:
		seen := map[string]bool{}

		return slices.DeleteFunc(current, func(each row) bool {
			distinctKey := r.evaluate(*step.Distinct, each.scope())
			if !distinctKey.known {
				return true
			}
			taken := seen[distinctKey.String()]
			seen[distinctKey.String()] = true

			return taken
		})
	}

	seen := map[string]bool{}

	return slices.DeleteFunc(current, func(each row) bool {
		firstKey := r.keyOf(step.FirstBy, each)
		taken := seen[firstKey]
		seen[firstKey] = true

		return taken
	})
}

// grouped is one row per distinct group key: the key under the name the key's reference starts at (key when it is no
// reference), and the rows it groups under rows. A row whose key is unknown joins no group.
func (r rows) grouped(current []row, by Expr) []row {
	name := "key"
	if by.IsReference() && len(by.Reference) == 1 {
		name = by.Name()
	}
	var keys []string
	members := map[string][]value{}
	values := map[string]value{}
	for _, each := range current {
		groupKey := r.evaluate(by, each.scope())
		if !groupKey.known {
			continue
		}
		written := groupKey.String()
		if members[written] == nil {
			keys = append(keys, written)
			values[written] = groupKey
		}
		members[written] = append(members[written], asRecord(each))
	}
	grouped := make([]row, 0, len(keys))
	for _, written := range keys {
		grouped = append(grouped, row{}.with(name, values[written]).with("rows", known(members[written])))
	}

	return grouped
}

// expanded is one row per record a group holds: the record's own names restored, the group's kept, and the record
// itself as row.
func (r rows) expanded(current []row, list Expr) []row {
	var expanded []row
	for _, group := range current {
		items, _ := listOf(r.evaluate(list, group.scope()))
		for _, item := range items {
			expanded = append(expanded, group.restoring(item))
		}
	}

	return expanded
}

func (r row) restoring(item value) row {
	restored := r.with("row", item)
	fields, isRecord := item.held.(record)
	if !isRecord {
		return restored
	}
	for name, field := range fields.whole() {
		if name != "rows" {
			restored = restored.with(name, field)
		}
	}

	return restored
}

// joined binds to each row the record of the relation whose fields equal the values given; a row with no such record
// binds unknown, which no comparison proves.
func (r rows) joined(current []row, step stage) []row {
	records := r.relationRows(step.Join)
	index := indexJoin(records, step.On)
	var joined []row
	for _, each := range current {
		matched := index.matching(r, each, step.Mode == "all")
		switch step.Mode {
		case "", "first":
			bound := unknown
			if len(matched) > 0 {
				bound = matched[0]
			}
			joined = append(joined, each.with(step.As, bound))
		case "inner":
			if len(matched) > 0 {
				joined = append(joined, each.with(step.As, matched[0]))
			}
		case "semi":
			if len(matched) > 0 {
				joined = append(joined, each)
			}
		case "anti":
			if len(matched) == 0 {
				joined = append(joined, each)
			}
		case "all":
			for _, candidate := range matched {
				joined = append(joined, each.with(step.As, candidate))
			}
		}
	}

	return joined
}

func (r rows) order(by []Expr, a, b row) int {
	for _, expression := range by {
		if compared := compareValues(r.evaluate(expression, a.scope()), r.evaluate(expression, b.scope())); compared != 0 {
			return compared
		}
	}

	return 0
}

func (r rows) keyOf(by []Expr, each row) string {
	var parts []string
	for _, expression := range by {
		parts = append(parts, r.evaluate(expression, each.scope()).String())
	}

	return fmt.Sprint(parts)
}

// compareValues orders two values: numbers by size, anything else by its written form.
func compareValues(a, b value) int {
	first, isNumber := a.held.(float64)
	second, alsoNumber := b.held.(float64)
	if isNumber && alsoNumber {
		return cmp.Compare(first, second)
	}

	return cmp.Compare(a.String(), b.String())
}

func importRecords(codebase *engine.Codebase) []value {
	order := map[*engine.File]int{}
	for at, file := range codebase.Files() {
		order[file] = at
	}
	var records []value
	for _, each := range engine.ResolvedImports(codebase) {
		file := each.At.Source()
		records = append(records, known(record{
			"anchor":      known(each.At),
			"from":        known(each.From),
			"to":          known(each.To),
			"file":        known(file.Path),
			"fileOrder":   known(float64(order[file])),
			"sourceOrder": known(float64(each.At.Node().Span.Start)),
			"test":        known(file.Purpose() != engine.Ordinary),
			"held":        known(codebase.Reports(file)),
			"associative": known(each.Associative),
		}))
	}

	return records
}
