package engine

import "github.com/jessegall/code-commandments/contract"

func (m Match) LiteralKind() string {
	if m.node == nil {
		return ""
	}

	return m.node.Literal
}

func (m Match) CodeWords() []string {
	if read := m.grammar().CodeWords; read != nil && m.node != nil {
		return read(m)
	}

	return nil
}

func (m Match) Value() *contract.Value {
	if m.node == nil {
		return nil
	}

	return m.node.Value
}
