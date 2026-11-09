package cli

import (
	"bytes"
	"reflect"
	"testing"
)

func TestAProgressRelaySendsTheLinesAJudgingMakesAndKeepsWhatItSays(t *testing.T) {
	var kept bytes.Buffer
	var sent []string

	relay := ProgressRelay{Writer: &kept, Send: func(line string) { sent = append(sent, line) }}
	progress := NewProgress(relay)
	progress.Plan(2)
	progress.Advance("first")
	progress.Advance("second")

	if want := []string{"detector 1/2", "detector 2/2"}; !reflect.DeepEqual(sent, want) {
		t.Fatalf("sent %v, want %v", sent, want)
	}

	relay.Write([]byte("said\n"))

	if kept.String() != "said\n" {
		t.Fatalf("kept %q", kept.String())
	}
}
