package golang_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func TestInterfaceReuseRespectsGoPackageVisibility(t *testing.T) {
	codebase := codebaseOf(t, map[string]string{
		"go.mod": "module example.com/office\n",
		"billing/contracts.go": `package billing
type InvoiceReader interface { Total(int) int; Exists(int) bool }
type hidden interface { Phase(int) string; Updated(int) bool }
`,
		"billing/internal/rates/contracts.go": `package rates
type RateLookup interface { Multiplier(string) int; Supported(string) bool }
`,
		"billing/refunds/refunds.go": `package refunds
type RefundReader interface { Exists(int) bool; Total(int) int }
type RefundPrivate interface { Updated(int) bool; Phase(int) string }
`,
		"collections/requests.go": `package collections
type CollectionRate interface { Supported(string) bool; Multiplier(string) int }
`,
	})
	layers := engine.Layers(map[string][]string{
		"example.com/office/billing":                nil,
		"example.com/office/billing/refunds":        {"example.com/office/billing"},
		"example.com/office/billing/internal/rates": nil,
		"example.com/office/collections":            {"example.com/office/billing/internal/rates"},
	}, "/", true)
	for _, declared := range codebase.WhereTypeDeclaration().Where(engine.Match.IsInterfaceType).Get() {
		if reuse := declared.ReusableInterface(layers, nil); declared.Name() == "RefundReader" {
			if !reuse.Exact || reuse.Contract.Name() != "InvoiceReader" {
				t.Fatalf("expected the visible upstream contract, got %q", reuse.Contract.Identity())
			}
		} else if reuse.Contract.Exists() {
			t.Fatalf("%s was told to reuse %s", declared.Identity(), reuse.Contract.Identity())
		}
	}
}
