package php

import (
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// DocComment is the docblock php-parser's getDocComment gives the node: the last leading doc comment on it or on
// an enclosing node that starts where it does, since php-parser hands a token's comments to every node it builds
// there. An attribute group is the exception: it never carries one.
func DocComment(m engine.Match) (contract.Comment, bool) {
	var doc contract.Comment
	found := false
	if m.Kind() == "AttributeGroup" {
		return doc, false
	}
	start := m.Node().Span.Start
	for at := m; at.Exists() && at.Node().Span.Start == start; at = at.Parent() {
		for _, comment := range at.Comments() {
			if comment.Kind == "doc" && !comment.Trailing && (!found || comment.Span.Start > doc.Span.Start) {
				doc, found = comment, true
			}
		}
	}

	return doc, found
}

// docScalars are the docblock types that are no class, so a collection of them has no element class.
var docScalars = []string{
	"string", "int", "integer", "float", "double", "bool", "boolean", "array", "iterable",
	"object", "mixed", "callable", "null", "void", "never", "true", "false", "scalar", "resource",
	"key-of", "value-of", "non-empty-string", "positive-int", "negative-int", "array-key",
}

// docTag is one `@var` or `@param` line: its written type, and the variable it names, if any.
var docTag = regexp.MustCompile(`@(?:var|param)\s+([^\s]+)(?:\s+\$([A-Za-z_]\w*))?`)

// ElementNamed is the element class the first collection type a docblock's `@var`/`@param` declares names, as
// written: `list<Payment>`, `Payment[]`, `Collection<int, Payment>`. A variable narrows it to the tags naming that
// variable or none; the empty variable takes every tag.
func ElementNamed(docblock, variable string) string {
	for _, tag := range docTag.FindAllStringSubmatch(docblock, -1) {
		if variable != "" && tag[2] != "" && tag[2] != variable {
			continue
		}
		if element := ElementOf(tag[1]); element != "" {
			return element
		}
	}

	return ""
}

// ElementOf is the element class a written collection type names: the last generic argument, or what `[]` follows.
// Empty for a type that is no collection, or a collection of scalars.
func ElementOf(written string) string {
	open := strings.Index(written, "<")
	switch {
	case open >= 0 && strings.HasSuffix(written, ">"):
		arguments := strings.Split(written[open+1:len(written)-1], ",")
		written = strings.TrimSpace(arguments[len(arguments)-1])
	case strings.HasSuffix(written, "[]"):
		written = strings.TrimSuffix(written, "[]")
	default:
		return ""
	}
	written = strings.TrimLeft(strings.TrimSpace(written), `\`)
	if written == "" || slices.Contains(docScalars, strings.ToLower(written)) {
		return ""
	}

	return written
}

// Resolve is a class name as a docblock in the file writes it, fully qualified the way PHP would: through the
// file's imports, else into its namespace. A name written with a leading `\` is already qualified.
func Resolve(written string, file *engine.File) string {
	if strings.HasPrefix(written, `\`) {
		return strings.TrimLeft(written, `\`)
	}
	root, _, _ := strings.Cut(written, `\`)
	if imported, ok := Imports(file)[root]; ok {
		return imported + written[len(root):]
	}
	if namespace := Namespace(file); namespace != "" {
		return namespace + `\` + written
	}

	return written
}

// Imports is every class a file's `use` statements import, by the name the file knows it by: its alias, or the
// last part of its name. A group's items are imported whatever their kind.
func Imports(file *engine.File) map[string]string {
	imports := map[string]string{}
	for _, node := range file.Nodes() {
		if node.Kind != "Stmt_Use" || len(node.Modifiers) > 0 {
			continue
		}
		for local, target := range imported(node) {
			if _, taken := imports[local]; !taken {
				imports[local] = target
			}
		}
	}
	for _, node := range file.Nodes() {
		if node.Kind != "Stmt_GroupUse" {
			continue
		}
		prefix := child(node, "prefix").Name
		for local, target := range imported(node) {
			imports[local] = prefix + `\` + target
		}
	}

	return imports
}

// Namespace is the file's first namespace, or empty for none.
func Namespace(file *engine.File) string {
	for _, node := range file.Nodes() {
		if node.Kind == "Stmt_Namespace" {
			return child(node, "name").Name
		}
	}

	return ""
}

func imported(use *contract.Node) map[string]string {
	imports := map[string]string{}
	for _, item := range use.Children {
		if item.Field != "uses" {
			continue
		}
		name := child(item, "name").Name
		local := name[strings.LastIndex(name, `\`)+1:]
		if alias := child(item, "alias"); alias.Name != "" {
			local = alias.Name
		}
		imports[local] = name
	}

	return imports
}

// child is the node's child in a field, or an empty node when it has none there.
func child(node *contract.Node, field string) *contract.Node {
	for _, child := range node.Children {
		if child.Field == field {
			return child
		}
	}

	return &contract.Node{}
}
