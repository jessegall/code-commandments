package python_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

func TestATypeTestOutsideAFunctionHeadsNoSwitch(t *testing.T) {
	codebase := python.In(pythontest.FromSource(t, map[string]string{"weights.py": `
import sys

value = sys.argv[1]
if isinstance(value, int):
    print("int")
elif isinstance(value, str):
    print("str")
else:
    print("other")
`}))
	for _, match := range codebase.WhereCall().Get() {
		if call := (python.Node{Match: match}); call.IsTypeSwitchHead() {
			t.Errorf("%s heads a type switch, though no function holds its arms", call.Location())
		}
	}
}
