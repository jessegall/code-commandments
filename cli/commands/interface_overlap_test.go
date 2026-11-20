package commands

import (
	"os"
	"strings"
	"testing"
)

func TestJudgeSuggestsAnExactReuseOrExtensionOnlyAcrossAllowedBoundaries(t *testing.T) {
	t.Chdir(t.TempDir())
	if err := os.Mkdir(".commandments", 0o755); err != nil {
		t.Fatal(err)
	}
	for file, content := range map[string]string{
		".commandments/config.json": `{"paths":["."],"configure":{"php/NamespaceDependencyDetector":[
			{"layer":["Shop\\Billing"]}, {"layer":["Shop\\Billing\\Refunds",["Shop\\Billing"]]},
			{"layer":["Shop\\Billing\\Private"]}, {"layer":["Shop\\Collections"]}
		]}}`,
		"Invoice.php": `<?php
namespace Shop\Billing\Contracts {
    final class InvoiceId {}
    interface InvoiceReader {
        public function total(InvoiceId $id): int;
        public function exists(InvoiceId $id): bool;
    }
}
namespace Shop\Billing\Refunds {
    use Shop\Billing\Contracts\InvoiceId as Key;
    interface RefundReader {
        public function exists(Key $id): bool;
        public function total(Key $id): int;
    }
    interface RefundAudit {
        public function audit(string $token): bool;
        public function exists(Key $id): bool;
        public function total(Key $id): int;
    }
    interface RefundSearch {
        public function category(string $name): string;
        public function atOffset(int $index): int;
    }
}
namespace Shop\Billing\Private {
    interface HiddenQuery {
        public function atOffset(int $index): int;
        public function category(string $name): string;
    }
}
namespace Shop\Collections {
    use Shop\Billing\Contracts\InvoiceId;
    interface Collector {
        public function exists(InvoiceId $id): bool;
        public function total(InvoiceId $id): int;
    }
}
namespace Shop\Shipping {
    interface Driver { public function seat(int $seat): int; public function shift(): string; }
}
namespace Shop\Shipping\Routes {
    interface RouteDriver { public function shift(): string; public function seat(int $seat): int; }
}
namespace Shop\Access {
    interface DataReader { public function find(int $id): string; public function visible(int $id): bool; }
    interface TenantReader { public function find(int $id): string; public function visible(int $id): bool; }
    final class LocalData implements DataReader {
        public function find(int $id): string { return (string) $id; }
        public function visible(int $id): bool { return $id > 0; }
    }
    final class SelectsTenant implements TenantReader {
        public function __construct(private DataReader $selected) {}
        public function find(int $id): string { return $this->selected->find($id); }
        public function visible(int $id): bool { return $this->selected->visible($id); }
    }
}
`,
	} {
		if err := os.WriteFile(file, []byte(content), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	code, out, stderr := ran("judge", ".", "--sin=interface-overlap", "--no-checklist", "--parallel=2")
	report := out + stderr
	for _, want := range []string{
		`Reuse Shop\Billing\Contracts\InvoiceReader`,
		`Extend Shop\Billing\Contracts\InvoiceReader`,
		`Reuse Shop\Shipping\Driver`,
		"3 sins",
	} {
		if code != 1 || !strings.Contains(report, want) {
			t.Fatalf("missing %q in judge output %d:\n%s", want, code, report)
		}
	}
	for _, unwanted := range []string{"HiddenQuery", "RefundSearch", "Collector", "TenantReader"} {
		if strings.Contains(report, unwanted) {
			t.Fatalf("the judge crosses an undeclared boundary for %s:\n%s", unwanted, report)
		}
	}
}
