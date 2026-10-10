package laravel_test

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/engine/php"
	_ "github.com/jessegall/code-commandments/engine/php/laravel"
)

// TestALaravelMigrationIsFrozenWithoutAMark holds a migration to what it is: run once and kept as it ran, so it is read
// but never a target, whether Laravel wrote it as an anonymous class or a named one; a class that only shares the name
// Migration is no migration.
func TestALaravelMigrationIsFrozenWithoutAMark(t *testing.T) {
	cases := map[string]bool{
		"<?php\nuse Illuminate\\Database\\Migrations\\Migration;\n\nreturn new class extends Migration {\n    public function up(): void {}\n};\n":        true,
		"<?php\nuse Illuminate\\Database\\Migrations\\Migration;\n\nfinal class CreateOrders extends Migration {\n    public function up(): void {}\n}\n": true,
		"<?php\nnamespace Shop;\n\nclass Migration {}\n\nfinal class Draft extends Migration {}\n":                                                        false,
		"<?php\nfinal class Order {}\n": false,
	}
	for source, frozen := range cases {
		path := filepath.Join(t.TempDir(), "2026_10_10_000000_create_orders.php")
		if err := os.WriteFile(path, []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
		codebase, err := php.Here().Scan(path)
		if err != nil {
			t.Fatal(err)
		}
		if got := codebase.Files()[0].IsFrozen(); got != frozen {
			t.Errorf("%q: frozen %v, want %v", source, got, frozen)
		}
	}
}
