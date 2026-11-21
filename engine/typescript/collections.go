package typescript

import (

)

func (n Node) IsModuleLevelDeclaration() bool {
	return n.Kind() == "VariableDeclaration" && !n.EnclosingFunction().Exists()
}

func (n Node) declared() Node {
	for _, child := range n.Children() {
		if child.Kind() == "ArrayLiteralExpression" {
			return Node{Match: child}
		}

		if child.Kind() == "NewExpression" {
			for _, held := range child.Children() {
				if held.Kind() == "ArrayLiteralExpression" {
					return Node{Match: held}
				}
			}
		}
	}

	return Node{}
}

func (n Node) StringMembers() []string {
	var names []string

	for _, member := range n.declared().Children() {
		text, literal := member.Text()
		if member.Kind() != "StringLiteral" || !literal || text == "" {
			return nil
		}
		names = append(names, text)
	}

	return names
}

func (n Node) KeyedInstanceNames() map[string][]string {
	objects := n.declared().Children()
	if len(objects) < 2 {
		return nil
	}

	keyed := map[string][]string{}
	for _, object := range objects {
		if object.Kind() != "ObjectLiteralExpression" {
			return nil
		}

		for _, property := range object.Children() {
			value := property.Child("initializer")
			text, literal := value.Text()

			if property.Kind() != "PropertyAssignment" || !literal || text == "" {
				continue
			}
			keyed[property.Name()] = append(keyed[property.Name()], text)
		}
	}

	for key, values := range keyed {
		if len(values) != len(objects) {
			delete(keyed, key)
		}
	}

	return keyed
}

var membershipQuestions = []string{"has", "includes", "contains"}

func (n Node) AsksAboutOne(held string) bool {
	if n.Kind() != "CallExpression" {
		return false
	}

	for _, child := range n.Children() {
		if child.Kind() != "PropertyAccessExpression" {
			continue
		}

		asked := false
		for _, question := range membershipQuestions {
			asked = asked || child.Name() == question
		}

		for _, named := range child.Children() {
			if asked && named.Kind() == "Identifier" && named.Name() == held {
				return true
			}
		}
	}

	return false
}

var walkMutators = []string{"pop", "shift", "unshift", "splice", "sort", "reverse", "fill", "copyWithin"}

