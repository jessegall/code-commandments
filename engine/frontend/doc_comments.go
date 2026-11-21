package frontend

import (
	"regexp"

	"github.com/jessegall/code-commandments/prose"
)

var docLink = regexp.MustCompile(`(?:\{@link(?:code|plain)?\s+|^@see\s+)([A-Za-z_$][\w$]*(?:[.#][A-Za-z_$][\w$]*)*)(?:[\s|}]|$)`)

var keywords = map[string]prose.Spelled{
	"IfStatement":         {Construct: prose.Condition},
	"ReturnStatement":     {Construct: prose.Return},
	"ForOfStatement":      {Construct: prose.Loop, Keywords: []string{"for", "over"}},
	"ForStatement":        {Construct: prose.Loop, Keywords: []string{"for"}},
	"WhileStatement":      {Construct: prose.ConditionalLoop, Keywords: []string{"while"}},
	"VariableStatement":   {Construct: prose.Assignment},
	"ThrowStatement":      {Construct: prose.Failure},
	"NewExpression":       {Construct: prose.Creation},
	"FunctionDeclaration": {Construct: prose.Method},
}
