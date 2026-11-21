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
	if read := m.grammar().CodeWords; read != nil && m.node != nil {
		return read(m)
	}

	return prose.Words(strings.Join(m.spelledWords(), " "))
}

func (m Match) spelledWords() []string {
	words := m.grammar().Keywords[m.Kind()].Words()
	words = append(words, m.Name())
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

func (m Match) Value() *contract.Value {
	if m.node == nil {
		return nil
	}

	return m.node.Value
}
