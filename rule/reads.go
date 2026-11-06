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
	"resolves", "resolvesLike", "declaredBy", "answeredBy", "declaredElsewhere", "docReference", "constructs",

	"type", "typeLike", "typeShape", "typeOrigin", "elementType", "nullable",

	"extendsAny", "implements", "uses", "isA", "ancestors", "overrides",

	"unused", "callers", "duplicated", "repeated", "referredFrom", "calledFrom", "sibling",

	"php", "python", "csharp", "typescript", "vue",
}

var targetsAnotherFile = []string{"declaration", "declaredBy"}

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
