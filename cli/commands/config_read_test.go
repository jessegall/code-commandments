package commands

import (
	"encoding/json"
	"os"
	"slices"
	"testing"
)

func TestConfigReadLeavesFrameworkWiringAlone(t *testing.T) {
	t.Chdir(t.TempDir())
	t.Setenv("JOURNAL_REPORT", "report.json")
	if err := os.MkdirAll("vendor/composer", 0o755); err != nil {
		t.Fatal(err)
	}
	for file, source := range map[string]string{
		"composer.json":                  `{"require":{"laravel/framework":"^12.0","spatie/laravel-data":"^4.0"}}`,
		"vendor/composer/installed.json": `{"packages":[{"name":"laravel/framework","version":"v12.0.0","replace":{"illuminate/support":"self.version"}},{"name":"spatie/laravel-data","version":"4.11.1"}]}`,
		"composer.lock":                  `{"packages":[{"name":"laravel/framework","version":"v12.0.0"},{"name":"spatie/laravel-data","version":"4.11.1"}]}`,
		"Wiring.php": `<?php
namespace Illuminate\Database\Eloquent\Factories {
    abstract class Factory { abstract public function definition(): array; }
}
namespace Spatie\LaravelData\Attributes {
    interface InjectsPropertyValue { public function resolve(): mixed; }
}
namespace Shop {
    use Illuminate\Database\Eloquent\Factories\Factory;
    use Spatie\LaravelData\Attributes\InjectsPropertyValue;

    final class ItemFactory extends Factory {
        public function definition(): array { return ['id' => config('item.id')]; }
        public function limit(): int { return config('item.limit'); }
    }
    final class FromConfig implements InjectsPropertyValue {
        public function resolve(): mixed { return config('item.id'); }
    }
    final class ItemReader {
        public function resolve(): mixed { return config('item.id'); }
    }
}
`,
	} {
		if err := os.WriteFile(file, []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	code, out, warned := ran("judge", ".", "--sin=config-read", "--no-checklist")
	if code != 1 {
		t.Fatalf("judge exited %d: %s %s", code, out, warned)
	}
	raw, err := os.ReadFile("report.json")
	if err != nil {
		t.Fatal(err)
	}
	var report struct {
		Findings []struct{ Where string }
	}
	if err := json.Unmarshal(raw, &report); err != nil {
		t.Fatal(err)
	}
	var scopes []string
	for _, finding := range report.Findings {
		scopes = append(scopes, finding.Where)
	}
	slices.Sort(scopes)
	if !slices.Equal(scopes, []string{`Shop\ItemFactory::limit`, `Shop\ItemReader::resolve`}) {
		t.Fatalf("findings = %v", scopes)
	}
}
