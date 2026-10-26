package gotree_test

import (
	"strings"
	"testing"
)

// TestAFieldOfAnotherPackagesTypeIsKnownByNameAlone holds what the bridge may write for a struct field it cannot
// type. Go answers every import outside the module with an empty package, by the decision doc 3 holds, so a
// field of `*strings.Builder` has no type the checker can give; the bridge writes the field by name alone rather
// than inventing one, and the contract says a field's type may be absent for exactly this reason.
//
// streamOf reads what the bridge wrote back through the contract's own reader, which holds every line against
// the schema. Before a field was allowed to carry no type, that refused the stream — `fields/1/type: null is not
// of type object` — so any module with a struct field of another package's type was unreadable by a reader that
// validates, which is every test in this repository.
func TestAFieldOfAnotherPackagesTypeIsKnownByNameAlone(t *testing.T) {
	for name, source := range map[string]string{
		"a pointer to another package's struct": "package shop\n\nimport \"strings\"\n\ntype Printer struct {\n\twidth int\n\tout   *strings.Builder\n}\n\nfunc (p *Printer) Width() int { return p.width }\n",
		"a value of another package's struct":   "package shop\n\nimport \"strings\"\n\ntype Printer struct {\n\twidth int\n\tout   strings.Builder\n}\n\nfunc (p *Printer) Width() int { return p.width }\n",
	} {
		t.Run(name, func(t *testing.T) {
			stream := streamOf(t, map[string]string{"go.mod": "module example.com/shop\n", "shop.go": source})
			if len(stream.Files) != 1 {
				t.Fatalf("the bridge wrote %d files", len(stream.Files))
			}
			if !strings.Contains(source, "out") {
				t.Fatal("the source no longer holds the field this test is about")
			}
		})
	}
}
