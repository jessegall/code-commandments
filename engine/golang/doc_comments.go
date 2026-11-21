package golang

import (
	"github.com/jessegall/code-commandments/prose"
)

var keywords = map[string]prose.Spelled{
	"IfStmt":     {Construct: prose.Condition},
	"ReturnStmt": {Construct: prose.Return},
	"RangeStmt":  {Construct: prose.Loop, Keywords: []string{"range", "over"}},
	"ForStmt":    {Construct: prose.ConditionalLoop, Keywords: []string{"for"}},
	"AssignStmt": {Construct: prose.Assignment},
	"FuncDecl":   {Construct: prose.Method},
	"TypeSpec":   {Construct: prose.Type},
}
