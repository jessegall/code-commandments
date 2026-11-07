package sync

import (
	"encoding/json"
	"os"
	"path/filepath"
	"slices"
	"testing"
)

func manifestNamed(t *testing.T, name string) map[string]any {
	t.Helper()

	read, err := os.ReadFile(filepath.Join("..", "..", name))
	if err != nil {
		t.Fatal(err)
	}

	var held map[string]any
	if err := json.Unmarshal(read, &held); err != nil {
		t.Fatalf("%s is not readable as a manifest: %v", name, err)
	}

	return held
}

func heardBy(held map[string]any) []string {
	var names []string
	for _, name := range held["hears"].([]any) {
		names = append(names, name.(string))
	}

	return names
}

func TestTheVersionFiveManifestDropsTheNamesThatJournalDoesNotDeclare(t *testing.T) {
	five := heardBy(manifestNamed(t, "journal-plugin.5.json"))

	for _, refused := range []string{"subagent_started", "plugin.updated"} {
		if slices.Contains(five, refused) {
			t.Errorf("the version five manifest hears %s, which that journal declares no event for, so it reads as "+
				"a wrong key and the plugin looks broken to anyone reading its events", refused)
		}
	}

	if !slices.Contains(five, "file_edited") {
		t.Error("the version five manifest no longer hears file_edited, which it does declare, and an edit is the " +
			"whole of the live judging")
	}
}

func TestTheFloorManifestKeepsWhatAVersionFourJournalNeeds(t *testing.T) {
	floor := manifestNamed(t, "journal-plugin.json")

	for _, needed := range []string{"subagent_started", "plugin.updated", "file_edited"} {
		if !slices.Contains(heardBy(floor), needed) {
			t.Errorf("the floor manifest no longer hears %s, and a version four journal reads only that file", needed)
		}
	}

	if _, carried := floor["listen"]; carried {
		t.Error("the floor manifest carries listen entries, which a version four journal has no field for: they " +
			"belong in the versioned manifest where they are read")
	}
}

func TestEachManifestNamesTheJournalItNeeds(t *testing.T) {
	floor, five := manifestNamed(t, "journal-plugin.json"), manifestNamed(t, "journal-plugin.5.json")

	if floor["journal"] == five["journal"] {
		t.Errorf("both manifests need journal %v, and they are written for different ones: a journal would be "+
			"offered a manifest it cannot read", floor["journal"])
	}

	if said, _ := five["journal"].(string); said == "" || said[0] != '5' {
		t.Errorf("the version five manifest needs journal %q, which is not a version five", said)
	}
}

func TestEveryListenEntryFiresAfterTheFactSoTheJournalNeverWaits(t *testing.T) {
	five := manifestNamed(t, "journal-plugin.5.json")

	entries, carried := five["listen"].([]any)
	if !carried || len(entries) == 0 {
		t.Fatal("the version five manifest carries no listen entry, so nothing of the journal's own keys reaches us")
	}

	for _, each := range entries {
		entry := each.(map[string]any)

		if _, held := entry["listen"]; held {
			t.Errorf("%s is answered on its listen form, which HOLDS the event with no limit: the journal waits "+
				"for us and never may. Only commit fires after the fact", entry["event"])
		}

		if _, guards := entry["guard"]; guards {
			window, declared := entry["window_ms"].(float64)
			if !declared || window <= 0 {
				t.Errorf("%s guards the event without a window_ms: the journal then waits for our answer with no "+
					"limit, so a hang of ours holds the change until a person switches the plugin off",
					entry["event"])
			}

			continue
		}

		if _, after := entry["commit"]; !after {
			t.Errorf("%s names no commit script, so nothing of it reaches us at all", entry["event"])
		}
	}
}
