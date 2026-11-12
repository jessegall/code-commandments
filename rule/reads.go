package rule

import (
	"reflect"
	"slices"
	"strings"
)

var answeredFromTheFile = []string{
	"is", "kind", "name", "nameIn", "nameLike", "nameMatches", "nameMood", "nameCase",
	"hasModifier", "hasFlag", "operator", "operatorIn",

	"text", "textLike", "textMatches", "commentIs", "commentLike", "commentMatches", "docTag", "documented",
	"documents",

	"file", "fileMatches", "namespaceLike", "layer", "topLevel", "withinLoop", "position", "field", "role",
	"sameAs",

	"unnamedCondition", "bindsItsOwnTest", "redundantElse", "soleLoopBodyGuard", "ladderRungs",
	"passesItsElementOn",

	"testCode", "implicit",

	"parameters", "arguments", "lines", "complexity", "members",

	"typeKind", "extends", "hasAnnotation", "hasAttribute", "returnType", "parameterType", "value",

	"descendant", "child", "body", "inside", "next", "previous", "nestedAtLeast", "calls", "argument", "count",
	"all", "any", "not", "rule", "of",
}

var readAcrossFiles = []string{
	"resolves", "resolvesLike", "targets", "declaredBy", "answeredBy", "declaredElsewhere", "docReference", "constructs",

	"type", "typeLike", "typeShape", "typeOrigin", "elementType", "nullable",

	"extendsAny", "implements", "uses", "isA", "ancestors", "overrides",

	"unused", "callers", "duplicated", "repeated", "referredFrom", "calledFrom", "sibling",

	"php", "python", "csharp", "typescript", "vue",
}

var targetsAnotherFile = []string{"declaration", "declaredBy"}

var readsOnlyDeclarations = []string{
	"resolves", "resolvesLike", "docReference", "constructs", "extendsAny", "implements", "uses", "isA", "ancestors",
	"overrides",
}

func (r Rule) ReadsForward() bool {
	if r.find.inner != nil && !r.find.inner.ReadsForward() {
		return false
	}

	return !slices.ContainsFunc(r.find.Where, Step.readsBackward) && !slices.ContainsFunc(r.find.Reject, Step.readsBackward)
}

func (s Step) readsBackward() bool {
	if slices.ContainsFunc(s.written(), func(key string) bool {
		return slices.Contains(readAcrossFiles, key) && !slices.Contains(readsOnlyDeclarations, key)
	}) {
		return true
	}

	if slices.Contains(targetsAnotherFile, s.Of) || s.Members != nil && s.Members.Inherited {
		return true
	}

	if s.named != nil && !s.named.ReadsForward() {
		return true
	}

	return slices.ContainsFunc(s.nested(), func(nested *Step) bool { return nested.readsBackward() })
}

func (r Rule) ReadsAcrossFiles() bool {
	if r.find.inner != nil && r.find.inner.ReadsAcrossFiles() {
		return true
	}

	return slices.ContainsFunc(r.find.Where, Step.readsAcrossFiles) ||
		slices.ContainsFunc(r.find.Reject, Step.readsAcrossFiles)
}

func (s Step) readsAcrossFiles() bool {
	for _, key := range s.written() {
		if slices.Contains(readAcrossFiles, key) {
			return true
		}
	}

	if slices.Contains(targetsAnotherFile, s.Of) {
		return true
	}

	if s.Members != nil && s.Members.Inherited {
		return true
	}

	if s.named != nil && s.named.ReadsAcrossFiles() {
		return true
	}

	return slices.ContainsFunc(s.nested(), func(nested *Step) bool { return nested.readsAcrossFiles() })
}

func (r Rule) Fingerprint() string {
	parts := []string{string(r.written), r.layers}

	if r.find.inner != nil {
		parts = append(parts, r.find.inner.Fingerprint())
	}

	for _, step := range slices.Concat(r.find.Where, r.find.Reject) {
		parts = append(parts, step.composedOf()...)
	}

	return strings.Join(parts, "\x00")
}

func (s Step) composedOf() []string {
	var parts []string

	if s.named != nil {
		parts = append(parts, s.named.Fingerprint())
	}

	for _, nested := range s.nested() {
		parts = append(parts, nested.composedOf()...)
	}

	return parts
}

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
