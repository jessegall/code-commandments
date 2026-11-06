package gotree_test

import (
	"strings"
	"testing"
)

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
