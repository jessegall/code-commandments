package csharp

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/prose"
)

var constructs = map[string]prose.Spelled{
	"ForEachStatement":                 {Construct: prose.Loop, Keywords: []string{"foreach"}},
	"ForStatement":                     {Construct: prose.Loop, Keywords: []string{"for"}},
	"WhileStatement":                   {Construct: prose.ConditionalLoop, Keywords: []string{"while"}},
	"DoStatement":                      {Construct: prose.ConditionalLoop, Keywords: []string{"do"}},
	"IfStatement":                      {Construct: prose.Condition, Keywords: []string{"if"}},
	"ElseClause":                       {Construct: prose.Otherwise, Keywords: []string{"else"}},
	"ConditionalExpression":            {Construct: prose.Condition},
	"ReturnStatement":                  {Construct: prose.Return, Keywords: []string{"return"}},
	"YieldReturnStatement":             {Construct: prose.Return, Keywords: []string{"yield", "return"}},
	"BreakStatement":                   {Construct: prose.Break, Keywords: []string{"break"}},
	"ContinueStatement":                {Construct: prose.Continue, Keywords: []string{"continue"}},
	"SwitchStatement":                  {Construct: prose.Branch, Keywords: []string{"switch"}},
	"SwitchExpression":                 {Construct: prose.Branch, Keywords: []string{"switch"}},
	"TryStatement":                     {Construct: prose.Attempt, Keywords: []string{"try"}},
	"CatchClause":                      {Construct: prose.Recovery, Keywords: []string{"catch"}},
	"ThrowStatement":                   {Construct: prose.Failure, Keywords: []string{"throw"}},
	"ThrowExpression":                  {Construct: prose.Failure, Keywords: []string{"throw"}},
	"ObjectCreationExpression":         {Construct: prose.Creation, Keywords: []string{"new"}},
	"ImplicitObjectCreationExpression": {Construct: prose.Creation, Keywords: []string{"new"}},
	"SimpleAssignmentExpression":       {Construct: prose.Assignment},
	"LocalDeclarationStatement":        {Construct: prose.Assignment},
	"AddAssignmentExpression":          {Construct: prose.Accumulation},
	"PostIncrementExpression":          {Construct: prose.Accumulation, Keywords: []string{"increment"}},
	"PreIncrementExpression":           {Construct: prose.Accumulation, Keywords: []string{"increment"}},
	"UsingStatement":                   {Construct: prose.Scope, Keywords: []string{"using"}},
}

func (n Node) CodeWords() []string {
	var words []string
	for _, word := range prose.Words(strings.Join(n.spelled(), " ")) {
		if !slices.Contains(words, word) {
			words = append(words, word)
		}
	}

	return words
}

func (n Node) spelled() []string {
	var spelled []string
	if construct, ok := constructs[n.Kind()]; ok {
		spelled = construct.Words()
	}
	for _, text := range []string{n.Name(), n.Text()} {
		if text != "" {
			spelled = append(spelled, text)
		}
	}
	for _, child := range n.All() {
		if role := child.hierarchyRole(); role != "statement" && role != "member" {
			spelled = append(spelled, child.spelled()...)
		}
	}

	return spelled
}

func (n Node) hierarchyRole() string {
	switch {
	case n.Is("NamespaceDeclaration", "FileScopedNamespaceDeclaration"):
		return "member"
	case n.Is("LocalFunctionStatement"):
		return "statement"
	case strings.HasSuffix(n.Kind(), "AccessorDeclaration") || n.Node().Role == "pattern":
		return "other"
	}

	return n.Node().Role
}
