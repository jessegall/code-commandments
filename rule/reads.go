package rule

import (
	"reflect"
	"slices"
	"strings"
)

// answeredFromTheFile are the checks a rule can answer from the FILE it reports in: the node itself, its place in
// its own tree, its text, its comments, what its own declaration writes, and the holders that step onto another
// node of the same file. Every other check is read as reaching beyond that file.
//
// The default is deliberately the other way round from a shipped rule's. A shipped rule's reach is MEASURED, by
// asking it the same question of one file and of the whole fixture (fixture/reads.go); a project's own rule has no
// fixture, so its reach is read from the checks its file writes — and a check this list forgets then costs a mark
// the per-file check would have shown, never an answer worked out from a file that could not hold it. That is the
// cheaper mistake: a rule asked about a program while it is shown one file does not fail, it ANSWERS, and the
// wrong answer reaches the agent as a sin to fix. Issue 824 was one: a rule asking `extendsAny` about a class
// whose base sits two files away was shown the edited file alone, found no Migration above it, and reported a
// migration as a stranger in the migrations folder.
var answeredFromTheFile = []string{
	// What it is and what it is called, which the node carries itself.
	"is", "kind", "name", "nameIn", "nameLike", "nameMatches", "nameMood", "nameCase",
	"hasModifier", "hasFlag", "operator", "operatorIn",

	// What it says: its own text and the comments written on it.
	"text", "textLike", "textMatches", "commentIs", "commentLike", "commentMatches", "docTag", "documented",

	// Where it sits, which is its own path and its own tree. `sameAs` asks about another node of the same file
	// and says so.
	"file", "fileMatches", "namespaceLike", "layer", "topLevel", "withinLoop", "position", "field", "role",
	"sameAs",

	// The readings of a condition, a branch and a loop, each worked out from the node's own shape.
	"unnamedCondition", "bindsItsOwnTest", "redundantElse", "soleLoopBodyGuard", "ladderRungs",
	"passesItsElementOn",

	// Whether it is ours to judge, which its bridge marks on the node.
	"testCode", "implicit",

	// How big it is, counted over what it holds itself. `members` counts a type's own members, and reaches a
	// parent's only where the step asks it to, which is read from the step.
	"parameters", "arguments", "lines", "complexity", "members",

	// What its own declaration writes: the kind it declares, its direct parent, its annotations, the types it
	// writes as text, and a literal's value.
	"typeKind", "extends", "hasAnnotation", "hasAttribute", "returnType", "parameterType", "value",

	// The holders, whose reach is the reach of the steps nested in them, and `of`, whose reach is the target's.
	"descendant", "child", "body", "inside", "next", "previous", "nestedAtLeast", "calls", "argument", "count",
	"all", "any", "not", "rule", "of",
}

// readAcrossFiles are the checks a rule cannot answer from the file it reports in, each because it follows
// something out of that file: a name resolved to its declaration, a chain of parents, a type a checker read
// through an import, the call graph, or a recurrence over the codebase. The list is here to be read beside the
// one above, and is held to it by a test: between them they name every check a rule file may write, so a check
// added to the vocabulary is classified rather than defaulted by accident.
var readAcrossFiles = []string{
	// What it refers to, resolved: the declaration is wherever it was written.
	"resolves", "resolvesLike", "declaredBy", "answeredBy", "declaredElsewhere", "docReference", "constructs",

	// The type a checker gave it, which it read through the imports of the whole project.
	"type", "typeLike", "typeShape", "typeOrigin", "elementType", "nullable",

	// The chain of parents and the contracts along it, followed through the scan.
	"extendsAny", "implements", "uses", "isA", "ancestors", "overrides",

	// What the rest of the codebase does with it, or does like it.
	"unused", "callers", "duplicated", "repeated", "referredFrom", "calledFrom", "sibling",

	// A language's own checks, a few of which read a base type (C#'s `inherited`), and the key alone does not
	// say which: a rule reaching for one is read as reaching as far as the furthest of them.
	"php", "python", "csharp", "typescript", "vue",
}

// targetsAnotherFile are the nodes "of" can name that are declared wherever their owner was written, rather than
// beside the node the step came from.
var targetsAnotherFile = []string{"declaration", "declaredBy"}

// ReadsAcrossFiles says whether the rule reads more than the file it reports in, which is what a scoped run and a
// per-file check ask before they show it one file alone. A project's own rule answers it from the checks its file
// writes; a shipped rule carries the marker its measurement earned (catalog.IsCrossFile asks both).
func (r Rule) ReadsAcrossFiles() bool {
	if r.find.inner != nil && r.find.inner.ReadsAcrossFiles() {
		return true
	}

	return slices.ContainsFunc(r.find.Where, Step.readsAcrossFiles) ||
		slices.ContainsFunc(r.find.Reject, Step.readsAcrossFiles)
}

// readsAcrossFiles says whether the step, a step nested in it, or the rule it names reads beyond the file.
func (s Step) readsAcrossFiles() bool {
	for _, key := range s.written() {
		if slices.Contains(readAcrossFiles, key) {
			return true
		}
	}

	if slices.Contains(targetsAnotherFile, s.Of) {
		return true
	}

	// A tally asks a type's parents for their members only where the step says so, and the parents are
	// declared wherever they were written.
	if s.Members != nil && s.Members.Inherited {
		return true
	}

	if s.named != nil && s.named.ReadsAcrossFiles() {
		return true
	}

	return slices.ContainsFunc(s.nested(), func(nested *Step) bool { return nested.readsAcrossFiles() })
}

// written are the keys the step writes, read from the step itself, so a check added to it is a key this answers
// for without being written down twice.
func (s Step) written() []string {
	var keys []string

	value := reflect.ValueOf(s)
	for i := range value.NumField() {
		key, _, _ := strings.Cut(value.Type().Field(i).Tag.Get("json"), ",")
		if key == "" || key == "-" || value.Field(i).IsZero() {
			continue
		}

		keys = append(keys, key)
	}

	return keys
}
