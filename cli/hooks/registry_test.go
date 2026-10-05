package hooks

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

// withoutCodex turns an agent off, which leaves every hook wired.
const withoutCodex = `<?php
return function ($config): void {
    $config->disable(\JesseGall\CodeCommandments\Agents\CodexAgent::class);
};
`

// TestHooksAreWiredAsThePHPToolWiresThem holds the wiring to what the PHP tool wrote for each case, recorded under
// testdata/wired/<case>: whether it wired anything, and the settings file it left.
func TestHooksAreWiredThroughTheLauncher(t *testing.T) {
	for name, settings := range map[string]*string{
		"no-settings":   nil,
		"own-hooks":     ptr(`{"model": "opus", "hooks": {"Stop": [{"hooks": [{"type": "command", "command": "./mine.sh"}, {"type": "command", "command": "php vendor/bin/commandments judge-reminder"}]}], "PreToolUse": [{"matcher": "Bash", "hooks": [{"type": "command", "command": "x # @code-commandments-managed"}]}]}}`),
		"already-wired": nil,
	} {
		t.Run(name, func(t *testing.T) {
			root := t.TempDir()
			must(t, os.MkdirAll(filepath.Join(root, ".commandments"), 0o755))
			must(t, os.WriteFile(filepath.Join(root, ".commandments", "config.php"), []byte(withoutCodex), 0o644))
			must(t, os.WriteFile(filepath.Join(root, "composer.json"), []byte("{}"), 0o644))

			if settings != nil {
				must(t, os.MkdirAll(filepath.Join(root, ".claude"), 0o755))
				must(t, os.WriteFile(filepath.Join(root, ".claude", "settings.json"), []byte(*settings), 0o644))
			}

			times := 1
			if name == "already-wired" {
				times = 2
			}

			var wired bool
			for range times {
				var err error
				wired, err = Wire(root)
				must(t, err)
			}

			recorded := filepath.Join("testdata", "wired", name)
			want, _ := os.ReadFile(filepath.Join(recorded, "answer"))
			if (string(want) == "wired") != wired {
				t.Errorf("wired %v, the PHP tool %s", wired, want)
			}

			phpFile, _ := os.ReadFile(filepath.Join(recorded, "settings.json"))
			goFile, _ := os.ReadFile(filepath.Join(root, ".claude", "settings.json"))
			if string(goFile) != string(phpFile) {
				t.Errorf("wrote\n%s\nthe PHP tool wrote\n%s", goFile, phpFile)
			}
		})
	}
}

func TestAProjectWithNoPHPRunsItsHooksThroughTheBinary(t *testing.T) {
	root := t.TempDir()

	if wired, err := Wire(root); !wired || err != nil {
		t.Fatalf("%v %v", wired, err)
	}

	settings, _ := os.ReadFile(filepath.Join(root, ".claude", "settings.json"))
	if !strings.Contains(string(settings), `"command": "commandments hooks # @code-commandments-managed"`) || strings.Contains(string(settings), "php") {
		t.Errorf("wired\n%s", settings)
	}
}

func ptr(text string) *string {
	return &text
}

func must(t *testing.T, err error) {
	t.Helper()

	if err != nil {
		t.Fatal(err)
	}
}
