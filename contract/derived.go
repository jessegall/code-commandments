package contract

import "slices"

func derive(file *File) {
	for _, node := range file.nodes {
		if declares(node) {
			written, made := node.Facts.Effective, effective(node, file.Language)
			if written != nil && written.Visibility != "" {
				made.Visibility = written.Visibility
			}
			node.Own().Effective = made
		}
		switch node.Kind {
		case "break", "continue":
			if node.Linked("destination") == nil {
				linkTo(node, "destination", left(node))
			}
		case "goto":
			if node.Linked("label") == nil {
				linkTo(node, "label", labelled(node))
			}
		}
	}
}

func linkTo(node *Node, name string, target *Node) {
	if target != nil {
		own := node.Own()
		own.Links = append(slices.Clip(own.Links), Link{Name: name, IDs: []int{target.ID}})
	}
}

func left(jump *Node) *Node {
	if label, written := jump.Child("label"); written {
		for at, held := jump.Parent(); held && !at.Is("function-like"); at, held = at.Parent() {
			if binding, bound := at.Child("binding"); at.Kind == "label" && bound && binding.Facts.Name == label.Facts.Name {
				if statement, wraps := at.Child("statement"); wraps {
					return statement
				}

				return at
			}
		}

		return nil
	}
	depth := max(jump.Facts.Depth, 1)
	for at, held := jump.Parent(); held && !at.Is("function-like"); at, held = at.Parent() {
		switch {
		case at.Kind == "loop", jump.Facts.Depth > 0 && leftSwitch(at):
			depth--
		case jump.Kind == "break" && (leftSwitch(at) || at.Kind == "select"):
			depth--
		default:
			continue
		}
		if depth == 0 {
			return at
		}
	}

	return nil
}

func leftSwitch(node *Node) bool {
	return node.Kind == "dispatch" && (node.Facts.Form == "switch" || node.Facts.Form == "type-switch")
}

func labelled(jump *Node) *Node {
	destination, written := jump.Child("destination")
	if !written {
		return nil
	}
	scope := jump
	for at, held := jump.Parent(); held; at, held = at.Parent() {
		scope = at
		if at.Is("function-like") {
			break
		}
	}
	var found *Node
	var walk func(node *Node)
	walk = func(node *Node) {
		for _, child := range node.Children() {
			if found != nil || child.Is("function-like") {
				continue
			}
			if binding, bound := child.Child("binding"); child.Kind == "label" && bound && binding.Facts.Name == destination.Facts.Name {
				found = child

				return
			}
			walk(child)
		}
	}
	walk(scope)

	return found
}

func declares(node *Node) bool {
	switch node.Kind {
	case "type", "field", "constant", "enum-case", "alias":
		return true
	case "parameter":
		function, held := node.Parent()

		return held && function.Facts.Form == "constructor" && (written(node.Facts.Modifiers) || slices.Contains(node.Facts.Modifiers, "readonly"))
	case "function":
		return node.Facts.Form != "closure" && node.Facts.Form != "arrow" && node.Facts.Form != "lambda" && node.Facts.Form != "local"
	}

	return false
}

func effective(node *Node, language Language) *Effective {
	owner, member := node.Parent()
	if node.Kind == "parameter" {
		owner, member = owner.Parent()
	}
	for member && owner.Kind != "type" && !owner.Is("function-like") && owner.Kind != "module" && owner.Kind != "file" {
		owner, member = owner.Parent()
	}
	member = member && owner.Kind == "type"
	made := &Effective{}
	for _, word := range node.Facts.Modifiers {
		switch word {
		case "public", "protected", "private", "internal":
			if made.Visibility == "" || word == "protected" {
				made.Visibility = word
			}
		case "static":
			made.Static = true
		case "abstract":
			made.Abstract = true
		case "final", "sealed":
			made.Final = true
		case "readonly":
			made.Readonly = true
		case "const":
			made.Static, made.Readonly = true, true
		}
	}
	name := node.Facts.Name
	if binding, bound := node.Child("binding"); name == "" && bound {
		name = binding.Facts.Name
	}
	if declarators, _ := node.Role("declarators"); name == "" && len(declarators) > 0 {
		if binding, bound := declarators[0].Child("binding"); bound {
			name = binding.Facts.Name
		}
	}
	if parent, held := node.Parent(); made.Visibility == "" && (node.Facts.Form == "get" || node.Facts.Form == "set") && held && parent.Facts.Effective != nil {
		made.Visibility = parent.Facts.Effective.Visibility
	}
	if made.Visibility == "" {
		made.Visibility = DefaultVisibility(language, name, member, node.Facts.Modifiers)
	}
	if node.Kind == "constant" {
		made.Static, made.Readonly = true, true
	}
	if member && (owner.Facts.Form == "interface" || owner.Facts.Form == "enum") && made.Visibility != "public" && !written(node.Facts.Modifiers) {
		made.Visibility = "public"
	}
	if _, bodied := node.Child("body"); member && owner.Facts.Form == "interface" && node.Kind == "function" && !bodied {
		made.Abstract = true
	}
	if node.Kind == "type" && node.Facts.Form == "enum" {
		made.Final = true
	}

	return made
}

func written(modifiers []string) bool {
	for _, word := range modifiers {
		switch word {
		case "public", "protected", "private", "internal":
			return true
		}
	}

	return false
}
