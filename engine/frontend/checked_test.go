package frontend_test

import (
	"strconv"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine/frontend/frontendtest"
)

// TestAFileTooLargeToCheckIsReadByItsSyntaxAlone holds the bridge to the limit set for a generated file the checker
// cannot type within the capped container: a file past a mebibyte of source is read whole, its resolver says it did
// not run, and no expression in it carries a type, while a file beside it is typed as ever.
func TestAFileTooLargeToCheckIsReadByItsSyntaxAlone(t *testing.T) {
	var generated strings.Builder
	for line := 0; generated.Len() <= 1<<20; line++ {
		generated.WriteString("export const value" + strconv.Itoa(line) + " = " + strconv.Itoa(line) + "\n")
	}
	codebase := frontendtest.FromSource(t, map[string]string{"src/generated.ts": generated.String(), "src/cart.ts": "export const total = 1 + 2\n"})
	for _, file := range codebase.Files() {
		large := strings.HasSuffix(file.Path, "generated.ts")
		if file.Resolver == nil || file.Resolver.Ran == large {
			t.Errorf("%s: the resolver says ran %v", file.Path, file.Resolver)
		}
		typed := 0
		for _, node := range file.Match(0).Descendants() {
			if node.Node().Resolved != nil {
				typed++
			}
		}
		if (typed > 0) == large {
			t.Errorf("%s: %d expressions are typed", file.Path, typed)
		}
	}
}
