package dashboard

import (
	"encoding/json"
	"os"
	"testing"

	_ "github.com/jessegall/code-commandments/registry"
)

func TestTheDashboardAndStoreAreWrittenAsPhpWritesThem(t *testing.T) {
	raw, _ := os.ReadFile("testdata/stored.json")

	var stored []Stored
	if err := json.Unmarshal(raw, &stored); err != nil {
		t.Fatal(err)
	}

	for golden, render := range map[string]func() (string, error){
		"testdata/stored.json": func() (string, error) { return Pretty(stored, false) },
		"testdata/sins.json":   func() (string, error) { return Pretty(Render(stored), true) },
		"testdata/empty.json":  func() (string, error) { return Pretty(Render(nil), true) },
	} {
		got, err := render()
		want, _ := os.ReadFile(golden)

		if err != nil || got != string(want) {
			t.Errorf("%s differs (%v)\n--- want\n%s\n--- got\n%s", golden, err, want, got)
		}
	}
}
