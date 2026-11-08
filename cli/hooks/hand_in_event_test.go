package hooks

import (
	"encoding/json"
	"os"
	"strings"
	"testing"
)

func TestAHandInArrivesUnderTheKeyTheManifestListensFor(t *testing.T) {
	raw, err := os.ReadFile("../../journal-plugin.5.json")
	if err != nil {
		t.Fatal(err)
	}

	var manifest struct {
		Listen []struct {
			Event string `json:"event"`
		} `json:"listen"`
	}
	if err := json.Unmarshal(raw, &manifest); err != nil {
		t.Fatal(err)
	}

	for _, listening := range manifest.Listen {
		if !strings.HasSuffix(listening.Event, deliveringEvent) {
			continue
		}

		if !strings.HasPrefix(listening.Event, "suites.coding.workers.") {
			t.Errorf("the manifest listens for %s, which is not a key the journal has", listening.Event)
		}

		if namedAsTheKey(listening.Event) != deliveringEvent {
			t.Errorf("%s reaches the service as %s, so no hand-in is judged", listening.Event, namedAsTheKey(listening.Event))
		}

		return
	}

	t.Error("the manifest does not listen for a hand-in at all")
}
