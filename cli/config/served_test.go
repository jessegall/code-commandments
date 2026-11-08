package config

import (
	"errors"
	"path/filepath"
	"slices"
	"testing"
)

func servedAs(document string, asked *int) func() ([]byte, error) {
	return func() ([]byte, error) {
		*asked++

		return []byte(`{"version": 1, "config": ` + document + `}`), nil
	}
}

func freshCopy(t *testing.T) {
	t.Helper()

	journalCopy.mu.Lock()
	journalCopy.project, journalCopy.version, journalCopy.config, journalCopy.held = "", 0, Config{}, false
	journalCopy.mu.Unlock()

	t.Cleanup(func() {
		journalCopy.mu.Lock()
		journalCopy.project, journalCopy.version, journalCopy.config, journalCopy.held = "", 0, Config{}, false
		journalCopy.mu.Unlock()
	})
}

func TestTheJournalsCopyIsWhatTheProjectLoadsOnceAVersionIsSeen(t *testing.T) {
	freshCopy(t)
	project := t.TempDir()

	asked := 0
	_, _, moved := TheJournalHolds(project, 1, servedAs(`{"paths": ["served"]}`, &asked))
	if moved {
		t.Error("the first copy fetched was told as a change")
	}

	loaded, err := Load(project)
	if err != nil {
		t.Fatal(err)
	}
	if !slices.Equal(loaded.Paths, []string{"served"}) {
		t.Errorf("the project loaded %v, not the journal's copy", loaded.Paths)
	}

	TheJournalHolds(project, 1, servedAs(`{"paths": ["served"]}`, &asked))
	if asked != 1 {
		t.Errorf("the journal was asked %d times for one version, want once", asked)
	}
}

func TestANewerVersionIsFetchedAndToldAsAChange(t *testing.T) {
	freshCopy(t)
	project := t.TempDir()

	asked := 0
	TheJournalHolds(project, 1, servedAs(`{"paths": ["src"]}`, &asked))

	was, now, moved := TheJournalHolds(project, 2, servedAs(`{"paths": ["src", "tests"]}`, &asked))
	if !moved || !slices.Equal(was.Paths, []string{"src"}) || !slices.Equal(now.Paths, []string{"src", "tests"}) {
		t.Errorf("version 2 was told as moved=%v from %v to %v", moved, was.Paths, now.Paths)
	}
}

func TestAnotherProjectLoadsItsOwnFile(t *testing.T) {
	freshCopy(t)

	asked := 0
	TheJournalHolds(t.TempDir(), 1, servedAs(`{"paths": ["served"]}`, &asked))

	elsewhere := t.TempDir()
	if loaded, _ := Load(elsewhere); slices.Equal(loaded.Paths, []string{"served"}) {
		t.Error("a project the journal does not hold loaded the journal's copy")
	}
}

func TestACopyTheJournalCannotGiveLeavesTheFileInCharge(t *testing.T) {
	freshCopy(t)
	project := filepath.Clean(t.TempDir())

	_, _, moved := TheJournalHolds(project, 1, func() ([]byte, error) { return nil, errors.New("no config declared") })
	if moved {
		t.Error("a fetch that failed was told as a change")
	}

	if _, held := journalCopy.of(project); held {
		t.Error("a fetch that failed left a copy held")
	}
}

func TestAConfigTheJournalServesAsNullLeavesTheFileInCharge(t *testing.T) {
	freshCopy(t)
	project := filepath.Clean(t.TempDir())

	asked := 0
	TheJournalHolds(project, 1, servedAs(`null`, &asked))

	if _, held := journalCopy.of(project); held {
		t.Error("a config the journal serves as null, a file that is not there yet, was held as an empty config")
	}
}
