package rule

import (
	"encoding/json"
	"maps"
	"slices"
	"strings"
)

// readsAcrossFiles are the operators whose answer for a node rests on files other than the node's own: who refers to a
// declaration, and the declaration a call resolves to.
var readsAcrossFiles = []string{"referrers", "overriders", "declarationOf", "programEnums", "programTexts", "typeDeclaration"}

// readsTheWholeTree are the operators whose answer for a node can turn on any file of the tree, not only on the files
// the node's own file reads: who refers to a declaration, and every enum the program declares.
var readsTheWholeTree = []string{"referrers", "overriders", "programEnums", "programTexts"}

// StandsOnTheTree says whether the rule's answer for a file can turn on any file of the tree, so a change anywhere asks
// it again: a pipeline whose rows group across files, or an operator that reads the whole program.
func (r Rule) StandsOnTheTree() bool {
	return r.read.Find.From != nil || r.applies(func(operator string) bool { return slices.Contains(readsTheWholeTree, operator) })
}

// ReadsAcrossFiles says whether the rule's answer for a file rests on other files too, so a change to one of them asks
// it again.
func (r Rule) ReadsAcrossFiles() bool {
	return r.read.Sin.Reporting.CrossFile || r.read.Find.From != nil || r.applies(func(operator string) bool { return slices.Contains(readsAcrossFiles, operator) })
}

// ReadsForward says whether the rule's answer for a file rests on that file and on what the others declare, never on
// how the others are written inside: true of every rule that reads only its own file.
func (r Rule) ReadsForward() bool {
	return !r.ReadsAcrossFiles()
}

// applies says whether the rule applies an operator the test picks, in its find or its definitions.
func (r Rule) applies(picked func(operator string) bool) bool {
	found := false
	visit := func(each Expr) {
		found = found || each.IsCall() && picked(each.Operator)
	}
	for _, expression := range r.read.expressions() {
		expression.Each(visit)
	}

	return found
}

// expressions is every expression a rule file writes: its definitions', its find's and its pipeline's.
func (f file) expressions() []Expr {
	var all []Expr
	for _, defined := range f.Definitions {
		all = append(all, defined.Expr)
	}
	written := []*Expr{f.Find.Where, f.Find.Having, f.Find.Emit, f.Find.Advice}
	for _, each := range f.Find.Let {
		all = append(all, each.Expr)
	}
	stages := slices.Clone(f.Find.Pipeline)
	for _, defined := range f.Find.Relations {
		stages = append(stages, defined.Pipeline...)
		all = append(all, slices.Collect(maps.Values(defined.Aggregate))...)
	}
	for _, step := range stages {
		written = append(written, step.Reject, step.Where, step.Having, step.GroupBy, step.Expand, step.Emit, step.Group)
		all = append(all, slices.Concat(step.OrderBy, step.FirstBy, slices.Collect(maps.Values(step.Project)), slices.Collect(maps.Values(step.On)))...)
	}
	for _, each := range written {
		if each != nil {
			all = append(all, *each)
		}
	}

	return all
}

// Fingerprint is what the rule's answers stand on: the effective rule with every definition it composes and the
// contract it reads (CacheKey), the values its parameters are set to, and the layers it judges against. Editing the
// rule file, an override of it or a definition it calls changes it, so no answer of the old rule is served.
func (r Rule) Fingerprint() string {
	set, _ := json.Marshal(r.set)

	return strings.Join([]string{r.CacheKey(), string(set), r.layers}, "\x00")
}

// ExemptionTags are the package exemption tags the rule honours: each tag an exemptedBy it asks names.
func (r Rule) ExemptionTags() []string {
	var tags []string
	for _, expression := range r.compiled().expressions() {
		expression.Each(func(each Expr) {
			if each.Operator != "exemptedBy" || len(each.Operands) == 0 {
				return
			}
			if tag, written := each.Operands[0].Value.(string); written && !slices.Contains(tags, tag) {
				tags = append(tags, tag)
			}
		})
	}
	slices.Sort(tags)

	return tags
}
