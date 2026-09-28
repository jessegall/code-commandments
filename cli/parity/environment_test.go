package parity

import (
	"os"
	"path/filepath"
	"testing"
	"time"
)

func TestACaseRunsInTheZoneItsGoldenWasRecordedIn(t *testing.T) {
	scratch := t.TempDir()
	c := Case{Name: "zone", Setup: []string{"touch -t 202601011200 stamped"}}

	if _, err := Run(c, t.TempDir(), scratch, "true"); err != nil {
		t.Fatal(err)
	}

	info, err := os.Stat(filepath.Join(scratch, "project", "stamped"))
	if err != nil {
		t.Fatal(err)
	}

	if got, want := info.ModTime().UTC(), time.Date(2026, 1, 1, 11, 0, 0, 0, time.UTC); !got.Equal(want) {
		t.Errorf("12:00 on the recording machine is %s, want %s", got, want)
	}
}
