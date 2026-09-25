package hooks

import (
	"os"
	"os/exec"
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

func TestHooksAreWiredAsThePHPToolWiresThem(t *testing.T) {
	if _, err := exec.LookPath("php"); err != nil {
		t.Skip("no php to wire with the PHP tool")
	}

	for name, settings := range map[string]*string{
		"no settings file": nil,
		"the user's own hooks beside an old one of ours": ptr(`{"model": "opus", "hooks": {"Stop": [{"hooks": [{"type": "command", "command": "./mine.sh"}, {"type": "command", "command": "php vendor/bin/commandments judge-reminder"}]}], "PreToolUse": [{"matcher": "Bash", "hooks": [{"type": "command", "command": "x # @code-commandments-managed"}]}]}}`),
		"already wired": nil,
	} {
		t.Run(name, func(t *testing.T) {
			php, golang := t.TempDir(), t.TempDir()

			for _, root := range []string{php, golang} {
				must(t, os.MkdirAll(filepath.Join(root, ".commandments"), 0o755))
				must(t, os.WriteFile(filepath.Join(root, ".commandments", "config.php"), []byte(withoutCodex), 0o644))
				must(t, os.WriteFile(filepath.Join(root, "composer.json"), []byte("{}"), 0o644))

				if settings != nil {
					must(t, os.MkdirAll(filepath.Join(root, ".claude"), 0o755))
					must(t, os.WriteFile(filepath.Join(root, ".claude", "settings.json"), []byte(*settings), 0o644))
				}
			}

			repo, _ := filepath.Abs("../..")
			script := `require '` + repo + `/vendor/autoload.php';
echo \JesseGall\CodeCommandments\Hooks\HookRegistry::wire($argv[1]) ? 'wired' : 'unchanged';`

			times := 1
			if name == "already wired" {
				times = 2
			}

			var want string
			var wired bool

			for range times {
				out, err := exec.Command("php", "-r", script, "--", php).CombinedOutput()
				if err != nil {
					t.Fatalf("php: %v\n%s", err, out)
				}

				want = string(out)

				wired, err = Wire(golang)
				must(t, err)
			}

			if (want == "wired") != wired {
				t.Errorf("wired %v, the PHP tool %s", wired, want)
			}

			phpFile, _ := os.ReadFile(filepath.Join(php, ".claude", "settings.json"))
			goFile, _ := os.ReadFile(filepath.Join(golang, ".claude", "settings.json"))

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
