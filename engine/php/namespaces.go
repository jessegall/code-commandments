package php

import (
	"strings"
)

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

func (n Node) NamespaceName() string {
	for at := n; at.Exists(); at = at.Up() {
		if at.Kind() == "Stmt_Namespace" {
			return at.Child("name").Name()
		}
	}

	return ""
}

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

func (n Node) EnclosingAttributeName() string {
	for at := n; at.Exists(); at = at.Up() {
		if at.Kind() == "Attribute" {
			return at.Child("name").Name()
		}
	}

	return ""
}

func NamespaceOf(class string) string {
	at := strings.LastIndex(strings.TrimLeft(class, `\`), `\`)
	if at < 0 {
		return ""
	}

	return strings.TrimLeft(class, `\`)[:at]
}

func Within(name, namespace string) bool {
	lowered, within := strings.ToLower(strings.TrimLeft(name, `\`)), strings.ToLower(strings.Trim(namespace, `\`))

	return within == "" || lowered == within || strings.HasPrefix(lowered, within+`\`)
}
