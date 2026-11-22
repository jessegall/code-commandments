package engine

import (
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/prose"
)

func (m Match) LiteralKind() string {
	if m.node == nil {
		return ""
	}

	return m.node.LiteralKind
}

func (m Match) CodeWords() []string {
	return prose.Words(strings.Join(m.spelledWords(), " "))
}

func (m Match) spelledWords() []string {
	words := append(m.constructWords(), m.Name())
	if text, ok := m.Text(); ok {
		words = append(words, text)
	}
	for _, child := range m.Children() {
		if child.Role() != "statement" && child.Role() != "member" {
			words = append(words, child.spelledWords()...)
		}
	}

	return words
}

func (m Match) constructWords() []string {
	if m.node == nil {
		return nil
	}
	switch m.node.Kind {
	case "loop":
		if m.node.Form == "while" || m.node.Form == "do-while" {
			return prose.ConditionalLoop.Words()
		}

		return prose.Loop.Words()
	case "assignment":
		if m.node.Mode == "compound" || m.node.Mode == "increment" {
			return prose.Accumulation.Words()
		}

		return prose.Assignment.Words()
	case "call":
		if callee := m.Child("callee"); callee.Kind() == "reference" && callee.Refers() == "" && m.file != nil && contract.RemovesWith(m.file.Language(), callee.Name()) {
			return prose.Removal.Words()
		}
	case "unary":
		if m.node.Operator == "delete" {
			return prose.Removal.Words()
		}
	}

	return constructOfKind[m.node.Kind].Words()
}

var constructOfKind = map[string]prose.Construct{
	"if":             prose.Condition,
	"conditional":    prose.Condition,
	"dispatch":       prose.Branch,
	"arm":            prose.Branch,
	"return":         prose.Return,
	"break":          prose.Break,
	"continue":       prose.Continue,
	"throw":          prose.Failure,
	"try":            prose.Attempt,
	"handler":        prose.Recovery,
	"variable":       prose.Assignment,
	"construction":   prose.Creation,
	"import":         prose.Import,
	"resource-scope": prose.Scope,
}

func (m Match) Value() *contract.Value {
	if m.node == nil {
		return nil
	}

	return m.node.Value
}
