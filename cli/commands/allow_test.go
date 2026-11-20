package commands

import (
	"os"
	"strings"
	"testing"
)

func TestAllowPublishesOnlyTheNamedInterface(t *testing.T) {
	t.Chdir(t.TempDir())
	if err := os.WriteFile("Readers.php", []byte(`<?php
namespace Shop\Billing;
interface InvoiceReader { public function find(int $id): string; }
interface ReceiptReader { public function find(int $id): string; }
final class Invoices implements InvoiceReader {
    public function find(int $id): string { return (string) $id; }
}
final class Receipts implements ReceiptReader {
    public function find(int $id): string { return (string) $id; }
}
`), 0o644); err != nil {
		t.Fatal(err)
	}
	args := []string{"allow", "lone-implementer-interface"}
	if code, _, err := ran(append(args, "Readers.php")...); code == 0 || !strings.Contains(err, "several interface declarations") {
		t.Fatalf("ambiguous file returned %d, %s", code, err)
	}
	if _, err := os.Stat(".commandments/config.json"); !os.IsNotExist(err) {
		t.Fatal("ambiguous file changed project config")
	}
	for _, want := range []string{"Published", "already"} {
		if code, out, err := ran(append(args, `Shop\Billing\InvoiceReader`)...); code != 0 || !strings.Contains(out, want) {
			t.Fatalf("allow returned %d, %s %s", code, out, err)
		}
	}
	code, out, err := ran("judge", ".", "--sin=lone-implementer-interface", "--no-checklist", "--parallel=2")
	if code != 1 || !strings.Contains(out+err, "Readers.php:4") || !strings.Contains(out+err, "1 sin") {
		t.Fatalf("judge returned %d, %s %s", code, out, err)
	}
}

func TestAllowResolvesAnInterfaceFromAGoFile(t *testing.T) {
	t.Chdir(t.TempDir())
	for path, text := range map[string]string{
		"go.mod":    "module example.com/billing\n\ngo 1.24\n",
		"reader.go": "package billing\n\ntype InvoiceReader interface { Find(id int) string }\n",
	} {
		if err := os.WriteFile(path, []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	if code, out, err := ran("allow", "lone-implementer-interface", "reader.go"); code != 0 || !strings.Contains(out, "InvoiceReader") {
		t.Fatalf("allow returned %d, %s %s", code, out, err)
	}
	raw, err := os.ReadFile(".commandments/config.json")
	if err != nil || !strings.Contains(string(raw), `"go"`) {
		t.Fatalf("expected a Go contract declaration, read %s (%v)", raw, err)
	}
}
