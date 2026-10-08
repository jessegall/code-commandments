package hooks

import (
	"os"
	"path/filepath"
	"testing"
)

// TestAPackageHookStepsAsideWhereThePluginRuns holds a default hook a user kept beside the journal plugin to
// silence: in a project the plugin is installed in, the plugin answers every moment, so the package's hook does not.
func TestAPackageHookStepsAsideWhereThePluginRuns(t *testing.T) {
	plain, journaled := t.TempDir(), t.TempDir()
	manifest := filepath.Join(journaled, ".journal", "plugins", "code-commandments", ".journal-plugin", "plugin.json")
	if err := os.MkdirAll(filepath.Dir(manifest), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(manifest, []byte("{}"), 0o644); err != nil {
		t.Fatal(err)
	}

	payload := map[string]any{"hook_event_name": "PostToolUse"}
	if stepsAside(NewEvent(payload, plain)) {
		t.Error("a project without the plugin had its hook step aside")
	}
	if !stepsAside(NewEvent(payload, journaled)) {
		t.Error("a project the plugin runs in still answered with the package's hook")
	}
}
