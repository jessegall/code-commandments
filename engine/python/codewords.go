package python

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/prose"
)

// constructs is the construct each Python statement is, and the keyword Python spells it with.
var constructs = map[string]prose.Spelled{
	"For":              {Construct: prose.Loop, Keywords: []string{"for"}},
	"AsyncFor":         {Construct: prose.Loop, Keywords: []string{"for"}},
	"While":            {Construct: prose.ConditionalLoop, Keywords: []string{"while"}},
	"If":               {Construct: prose.Condition, Keywords: []string{"if"}},
	"Return":           {Construct: prose.Return, Keywords: []string{"return"}},
	"Raise":            {Construct: prose.Failure, Keywords: []string{"raise"}},
	"Try":              {Construct: prose.Attempt, Keywords: []string{"try"}},
	"TryStar":          {Construct: prose.Attempt, Keywords: []string{"try"}},
	"Match":            {Construct: prose.Branch, Keywords: []string{"match"}},
	"With":             {Construct: prose.Scope, Keywords: []string{"with"}},
	"AsyncWith":        {Construct: prose.Scope, Keywords: []string{"with"}},
	"Import":           {Construct: prose.Import, Keywords: []string{"import"}},
	"ImportFrom":       {Construct: prose.Import, Keywords: []string{"import"}},
	"ClassDef":         {Construct: prose.Type, Keywords: []string{"class"}},
	"FunctionDef":      {Construct: prose.Method, Keywords: []string{"def"}},
	"AsyncFunctionDef": {Construct: prose.Method, Keywords: []string{"def"}},
	"Assign":           {Construct: prose.Assignment},
	"AnnAssign":        {Construct: prose.Assignment},
	"AugAssign":        {Construct: prose.Accumulation},
}

// CodeWords is the words a statement's own head spells, as prose content words: the construct it is and its
// keyword, then its names, attributes, keyword arguments and string literals, the statements it holds aside. A
// comment measured against them shows whether it says anything the code does not.
func (n Node) CodeWords() []string {
	var spelled []string
	if construct, ok := constructs[n.Kind()]; ok {
		spelled = construct.Words()
	}
	for _, expression := range n.ownExpressions() {
		for _, part := range append([]Node{expression}, expression.Descendants()...) {
			spelled = append(spelled, part.spells()...)
		}
	}
	var words []string
	for _, word := range prose.Words(strings.Join(spelled, " ")) {
		if !slices.Contains(words, word) {
			words = append(words, word)
		}
	}

	return words
}

// ownExpressions is the expressions a statement's head holds, the statements in its body aside: a def's
// decorators, never its parameters; a with's contexts and targets.
func (n Node) ownExpressions() []Node {
	var own []Node
	for _, child := range n.Children() {
		switch {
		case child.Node().Role == "expression":
			own = append(own, child)
		case child.Kind() == "withitem":
			own = append(own, child.ownExpressions()...)
		}
	}

	return own
}

// spells is the text the expression itself spells: a name, an attribute, a keyword argument, a string.
func (n Node) spells() []string {
	switch n.Kind() {
	case "Name", "Attribute", "keyword":
		return []string{n.Name()}
	}
	if text, ok := n.Text(); ok {
		return []string{text}
	}

	return nil
}
