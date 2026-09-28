package php

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func init() {
	engine.Freezes(contract.PHP, declaresFrozen)
}

// declaresFrozen says whether an attribute group of the file opens with #[Frozen], whatever the spacing inside it.
func declaresFrozen(file *engine.File) bool {
	source, err := file.Source()
	if err != nil {
		return false
	}
	for _, group := range file.File.Nodes() {
		if group.Kind != "AttributeGroup" || len(group.Children) == 0 {
			continue
		}
		name := firstChildIn(group.Children[0], "name")
		if name == nil {
			continue
		}
		opened := engine.Source(source).SkipWhitespace(group.Span.Start+len("#["), name.Span.Start)
		if opened == name.Span.Start && string(source[name.Span.Start:name.Span.End]) == "Frozen" {
			return true
		}
	}

	return false
}

func firstChildIn(node *contract.Node, field string) *contract.Node {
	for _, child := range node.Children {
		if child.Field == field {
			return child
		}
	}

	return nil
}
