package php

import (
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

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

var docScalars = []string{
	"string", "int", "integer", "float", "double", "bool", "boolean", "array", "iterable",
	"object", "mixed", "callable", "null", "void", "never", "true", "false", "scalar", "resource",
	"key-of", "value-of", "non-empty-string", "positive-int", "negative-int", "array-key",
}

var docTag = regexp.MustCompile(`@(?:var|param)\s+([^\s]+)(?:\s+\$([A-Za-z_]\w*))?`)

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

func child(node *contract.Node, field string) *contract.Node {
	for _, child := range node.Children {
		if child.Field == field {
			return child
		}
	}

	return contract.NewNode(contract.Node{})
}
