package sync_test

import (
	"encoding/json"
	"os"
	"path/filepath"
	"strings"
	"testing"
)

type declared struct {
	Key     string `json:"key"`
	Title   string `json:"title"`
	Type    string `json:"type"`
	Help    string `json:"help"`
	Default any    `json:"default"`
}

func settingsFile(t *testing.T) []declared {
	t.Helper()

	text, err := os.ReadFile(filepath.Join("..", "..", ".journal-plugin", "settings.json"))
	if err != nil {
		t.Fatal(err)
	}

	var settings []declared
	if err := json.Unmarshal(text, &settings); err != nil {
		t.Fatal(err)
	}

	return settings
}

func TestEverySettingThisPlanAddsSaysWhatItIsAndWhatItDefaultsTo(t *testing.T) {
	want := map[string]any{
		"judge_workers":       false,
		"judge_merges":        true,
		"judge_nodes_held":    float64(0),
		"judge_files_at_once": float64(0),
	}

	found := map[string]declared{}
	for _, setting := range settingsFile(t) {
		found[setting.Key] = setting
	}

	for key, fallback := range want {
		setting, declared := found[key]
		if !declared {
			t.Errorf("the settings file declares no %s, so nobody can switch it", key)

			continue
		}

		if setting.Default != fallback {
			t.Errorf("%s defaults to %v, and it is %v", key, setting.Default, fallback)
		}

		if setting.Title == "" || strings.HasSuffix(setting.Title, ".") {
			t.Errorf("%s has the title %q, and a title is a short name rather than a sentence", key, setting.Title)
		}

		if !strings.HasSuffix(strings.TrimSpace(setting.Help), ".") || len(setting.Help) < 40 {
			t.Errorf("%s's help does not read as a sentence: %q", key, setting.Help)
		}
	}
}

func TestNoSettingPromisesAGateThatCannotExist(t *testing.T) {
	for _, setting := range settingsFile(t) {
		if setting.Key == "hold_merges" {
			t.Error("the settings file offers to hold a merge, and a merge cannot be held: the key fires after the merge has committed and a refusal from that form is a fault")
		}
	}
}
