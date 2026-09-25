package php

import (
	"strings"
)

// IsClassReference says whether the node names a class: a name that is no namespace's own, no function's and no
// constant's, and not self, parent or static.
func (n Node) IsClassReference() bool {
	if !isName(n.Match) || slicesContainsFold(specialClassNames, n.Name()) {
		return false
	}
	switch n.Parent().Kind() {
	case "Stmt_Namespace", "Expr_FuncCall", "Expr_ConstFetch":
		return false
	}

	return true
}

func slicesContainsFold(values []string, value string) bool {
	for _, each := range values {
		if strings.EqualFold(each, value) {
			return true
		}
	}

	return false
}

// NamespaceName is the namespace the node is written in; empty outside any.
func (n Node) NamespaceName() string {
	for at := n; at.Exists(); at = at.Up() {
		if at.Kind() == "Stmt_Namespace" {
			return at.Child("name").Name()
		}
	}

	return ""
}

// ArgumentOfCall is the name of the call the node is an argument of, reaching up through the argument's own
// expression; empty when a call closer to it intervenes, or it is no argument.
func (n Node) ArgumentOfCall() string {
	for at := n; at.Exists(); at = at.Up() {
		if at.Kind() == "Arg" {
			return at.Up().CallName()
		}
		if at.Node() != n.Node() && at.CallName() != "" {
			return ""
		}
	}

	return ""
}

// EnclosingAttributeName is the attribute the node is written in; empty outside any.
func (n Node) EnclosingAttributeName() string {
	for at := n; at.Exists(); at = at.Up() {
		if at.Kind() == "Attribute" {
			return at.Child("name").Name()
		}
	}

	return ""
}

// NamespaceOf is the namespace a class name sits in; empty for a class at the global level.
func NamespaceOf(class string) string {
	at := strings.LastIndex(strings.TrimLeft(class, `\`), `\`)
	if at < 0 {
		return ""
	}

	return strings.TrimLeft(class, `\`)[:at]
}

// Within says whether a name is the namespace or sits under it, in any case; everything is within the global one.
func Within(name, namespace string) bool {
	lowered, within := strings.ToLower(strings.TrimLeft(name, `\`)), strings.ToLower(strings.Trim(namespace, `\`))

	return within == "" || lowered == within || strings.HasPrefix(lowered, within+`\`)
}
