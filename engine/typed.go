package engine

import "github.com/jessegall/code-commandments/contract"

// Typed is the type OBJECT a node has, which is where every structural question about a type is answered: whether
// it admits absence, what shape it has, what it holds and where it came from. It is one reader, so a rule asking
// any of those asks the same question of the same node:
//
//   - an expression: the type it resolves to, as its language's checker or the engine's own resolution read it;
//   - a declaration: the type it writes — a parameter's, a property's, a function's return;
//   - a written type NODE, which a rule reaches with `of: returnType` or `of: parameterType`: the type of the
//     declaration it is written on, since the `?` of `?Money` and the `| None` of `str | None` belong to the
//     declaration's type and not to the name underneath.
//
// No type where the node has none: a value nothing says anything about, a function with no return written. A rule
// then keeps the node for no type question, since absence of a type is no answer about a type.
func (m Match) Typed() *contract.Type {
	if m.node == nil {
		return nil
	}

	switch {
	case m.node.Resolved != nil:
		return m.node.Resolved
	case m.node.Declared != nil:
		return m.node.Declared
	case m.node.Returns != nil:
		return m.node.Returns
	}

	return m.writtenFor()
}

// writtenFor is the type of the declaration the node is the written type of, found through the declaration's own
// grammar rather than by guessing which field of the tree a language keeps a type in.
func (m Match) writtenFor() *contract.Type {
	declaration := m.Parent()
	if returned := declaration.ReturnType(); returned.node == m.node {
		return declaration.node.Returns
	}

	if written := declaration.ParameterType(); written.node == m.node {
		return declaration.node.Declared
	}

	return nil
}

// ElementType is the type a collection holds: the element its bridge read, from a generic argument, a docblock
// `list<X>` or an attribute such as `#[DataCollectionOf(X::class)]`; else the argument of an `array`, which the
// contract says IS its element; else the value of a `map`, which the contract keeps second after its key. No type
// for anything else, a collection whose element nothing says among them, since a bag of unknowns is no bag of a
// type.
func (m Match) ElementType() *contract.Type {
	typed := m.Typed()

	switch {
	case typed == nil:
		return nil
	case typed.Element != nil:
		return typed.Element
	case typed.Kind == "array" && len(typed.Args) == 1:
		return typed.Args[0]
	case typed.Kind == "map" && len(typed.Args) == 2:
		return typed.Args[1]
	}

	return nil
}

// AdmitsAbsence says whether the node's type admits absence, however its language spells it: `?Money`,
// `Money|null`, `str | None`, `string?`, `number | undefined`. It is false for a node with no type at all, since
// nothing known about a type says nothing about absence.
func (m Match) AdmitsAbsence() bool {
	typed := m.Typed()

	return typed != nil && typed.Nullable
}
