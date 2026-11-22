package judge

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestATestFolderOutsideThePathsIsReadForItsFakesButNeverJudged(t *testing.T) {
	project, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for name, source := range map[string]string{
		"composer.json":                          `{"autoload": {"psr-4": {"App\\": "app/"}}, "autoload-dev": {"psr-4": {"Tests\\": "tests/"}}}`,
		".commandments/config.json":              `{"paths": ["app"]}`,
		"app/Engine/Contracts/Sleeper.php":       "<?php\n\nnamespace App\\Engine\\Contracts;\n\ninterface Sleeper\n{\n    public function sleep(int $seconds): void;\n}\n",
		"app/Engine/Contracts/SystemSleeper.php": "<?php\n\nnamespace App\\Engine\\Contracts;\n\nfinal class SystemSleeper implements Sleeper\n{\n    public function sleep(int $seconds): void\n    {\n        sleep($seconds);\n    }\n}\n",
		"app/Engine/Contracts/Clock.php":         "<?php\n\nnamespace App\\Engine\\Contracts;\n\ninterface Clock\n{\n    public function now(): int;\n}\n",
		"app/Engine/Contracts/SystemClock.php":   "<?php\n\nnamespace App\\Engine\\Contracts;\n\nfinal class SystemClock implements Clock\n{\n    public function now(): int\n    {\n        return time();\n    }\n}\n",
		"tests/Support/FakeSleeper.php":          "<?php\n\nnamespace Tests\\Support;\n\nuse App\\Engine\\Contracts\\Sleeper;\n\nfinal class FakeSleeper implements Sleeper\n{\n    public function sleep(int $seconds): void\n    {\n    }\n}\n",
		"tests/Feature/ClockTest.php":            "<?php\n\nnamespace Tests\\Feature;\n\nuse App\\Engine\\Contracts\\Clock;\n\nfinal class ClockTest\n{\n    public function test_the_clock_ticks(Clock $clock): void\n    {\n        assert($clock->now() > 0);\n    }\n}\n",
		"tests/Support/Contracts/Ticker.php":     "<?php\n\nnamespace Tests\\Support\\Contracts;\n\ninterface Ticker\n{\n    public function tick(): void;\n}\n",
		"tests/Support/Contracts/OneTicker.php":  "<?php\n\nnamespace Tests\\Support\\Contracts;\n\nfinal class OneTicker implements Ticker\n{\n    public function tick(): void\n    {\n    }\n}\n",
	} {
		path := filepath.Join(project, name)
		if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(path, []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	_, said, warned := judgedIn(t, project, "--sin=lone-implementer-interface", "--no-checklist")

	if !strings.Contains(said, "Contracts/Clock.php") {
		t.Errorf("an interface with one implementer anywhere is no longer found: %s%s", said, warned)
	}
	if strings.Contains(said, "Sleeper") {
		t.Errorf("an interface a test fake also implements is still a lone implementer: %s", said)
	}
	if strings.Contains(said, "tests/") {
		t.Errorf("a test folder outside the judged paths was judged: %s", said)
	}
}
