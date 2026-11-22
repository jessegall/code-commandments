package rule

import (
	"encoding/json"
	"fmt"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// Step is one part of a find asked of one selected node: the part as written, and what it answered.
type Step struct {
	Part     string
	Written  string
	Answered string
}

// Traced is every part of a find asked of one node the rule selects: the node, each step in the order the judge asks
// it, stopping where the judge stops, and whether its where held.
type Traced struct {
	Node      engine.Match
	Steps     []Step
	WhereHeld bool
}

func (r Rule) Trace(codebase *engine.Codebase, file string, first, last int) ([]Traced, error) {
	asked := r.compiled()
	written := asked.Find
	if written.From != nil {
		return nil, fmt.Errorf("%s reads rows through a pipeline; a trace follows a find that selects nodes", r.Name())
	}
	kinds, _ := selected(written.Select)
	var traced []Traced
	ask := func(site engine.Match, selectedValue value) {
		if site.Line() < first || site.Line() > last || !strings.HasSuffix(site.File(), file) {
			return
		}
		judging := evaluation{definitions: asked.Definitions, site: site, parameters: r.parameters(), codebase: codebase}
		steps, whereHeld := judging.steps(written, selectedValue)
		traced = append(traced, Traced{site, steps, whereHeld})
	}
	if slices.Contains(kinds, commentSource) {
		for _, comment := range commentsOf(codebase) {
			site, _ := nodeOf(comment["location"])
			ask(site, known(comment))
		}
	}
	for _, site := range codebase.WhereKind(kinds...).Get() {
		ask(site, known(site))
	}

	return traced, nil
}

func (e evaluation) steps(written find, selectedValue value) ([]Step, bool) {
	bound := bindings{}.with(written.As, selectedValue)
	var steps []Step
	gate := func(part string, whole *Expr) bool {
		if whole == nil {
			return true
		}
		decided := true
		for _, each := range conjuncts(*whole) {
			answer := truthOf(e.evaluate(each, bound))
			steps = append(steps, Step{part, writtenWhole(each), truthWords[answer]})
			decided = decided && answer == engine.True
		}

		return decided
	}
	if !gate("where", written.Where) {
		return steps, false
	}
	for _, each := range written.Let {
		held := e.evaluate(each.Expr, bound)
		bound = bound.with(each.Name, held)
		steps = append(steps, Step{"let " + each.Name, writtenWhole(each.Expr), held.String()})
	}
	gate("having", written.Having)

	return steps, true
}

func conjuncts(whole Expr) []Expr {
	if whole.IsCall() && whole.Operator == "and" {
		return whole.Operands
	}

	return []Expr{whole}
}

var truthWords = map[engine.Truth]string{engine.True: "true", engine.False: "false", engine.Unknown: "unknown"}

func writtenWhole(part Expr) string {
	written, err := json.Marshal(part)
	if err != nil {
		return part.Written()
	}

	return string(written)
}
