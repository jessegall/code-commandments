package report

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

// TestAReportPublishesCodeOnlyWhenItIsShared holds a public issue to the reporter's consent: it names the file by
// its own name and lines, shows no code unless shared, and when shared shows the referenced lines alone.
func TestAReportPublishesCodeOnlyWhenItIsShared(t *testing.T) {
	file := filepath.Join(t.TempDir(), "app", "Billing", "Invoice.php")
	if err := os.MkdirAll(filepath.Dir(file), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(file, []byte("<?php\nclass Invoice\n{\n    private string $customer = 'Acme';\n    public function total(): int { return 1; }\n}\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	ref := Reference{Path: file, Start: 5}

	private := render([]Reference{ref}, false)
	if !strings.Contains(private, "`Invoice.php:5`") || strings.Contains(private, "Billing") || strings.Contains(private, "function") {
		t.Errorf("an unshared report reads %q", private)
	}

	shared := render([]Reference{ref}, true)
	if !strings.Contains(shared, "public function total()") || strings.Contains(shared, "Acme") || strings.Contains(shared, "class Invoice") {
		t.Errorf("a shared report reads %q", shared)
	}
}
