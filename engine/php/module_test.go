package php

import "testing"

func TestAModuleIsTheFirstTwoSegmentsOfAQualifiedName(t *testing.T) {
	cases := map[string]string{
		`\App\Billing\Invoices\Invoice`: `App\Billing`,
		`Channel\Shopify\Client`:        `Channel\Shopify`,
		`App\Invoice`:                   `App\Invoice`,
		`Invoice`:                       `Invoice`,
	}
	for name, want := range cases {
		if got := ModuleOf(name); got != want {
			t.Errorf("ModuleOf(%q) = %q, want %q", name, got, want)
		}
	}
}
