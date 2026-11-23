package scan

import (
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/walk"
)

func settledFile(t *testing.T, name, text string) string {
	t.Helper()
	file := filepath.Join(t.TempDir(), name)
	if err := os.WriteFile(file, []byte(text), 0o644); err != nil {
		t.Fatal(err)
	}
	anHourAgo := time.Now().Add(-time.Hour)
	if err := os.Chtimes(file, anHourAgo, anHourAgo); err != nil {
		t.Fatal(err)
	}

	return file
}

func keptWith(t *testing.T, entries map[string][2]string) keptPrints {
	t.Helper()
	kept := keptPrints{filepath.Join(t.TempDir(), "prints.json")}
	raw, err := json.Marshal(entries)
	if err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(kept.path, raw, 0o644); err != nil {
		t.Fatal(err)
	}

	return kept
}

func TestAFileAnEarlierRunPrintedIsNotReadAgainWhileItsSizeAndTimeStand(t *testing.T) {
	file := settledFile(t, "cart.php", "<?php\n")
	earlier := sha256.Sum256([]byte("the print the earlier run kept"))
	keptWith(t, map[string][2]string{file: {walk.Mark(file), hex.EncodeToString(earlier[:])}}).hold()

	read := walk.ReadForPrint()
	print, err := walk.Print(file)

	if err != nil || print != earlier {
		t.Errorf("the file printed %x, %v, where the earlier run kept %x for the size and time it still has", print, err, earlier)
	}
	if again := walk.ReadForPrint() - read; again != 0 {
		t.Errorf("the file was read %d times for a print an earlier run already kept", again)
	}
}

func TestAFileThatChangedSinceAnEarlierRunIsReadForItsPrint(t *testing.T) {
	file := settledFile(t, "cart.php", "<?php\n")
	earlier := sha256.Sum256([]byte("what it held before"))
	keptWith(t, map[string][2]string{file: {"2020-01-01T00:00:00Z\x001", hex.EncodeToString(earlier[:])}}).hold()

	if print, err := walk.Print(file); err != nil || print != sha256.Sum256([]byte("<?php\n")) {
		t.Errorf("a file whose size and time moved printed %x, %v, where it is read for what it holds now", print, err)
	}
}

func TestARunKeepsEachSettledPrintAndLeavesOutAFileJustWritten(t *testing.T) {
	settled := settledFile(t, "cart.php", "<?php\n")
	fresh := filepath.Join(t.TempDir(), "stock.php")
	if err := os.WriteFile(fresh, []byte("<?php\n\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	gone := filepath.Join(t.TempDir(), "removed.php")
	kept := keptWith(t, map[string][2]string{gone: {"2020-01-01T00:00:00Z\x001", "00"}})
	kept.hold()
	for _, file := range []string{settled, fresh} {
		if _, err := walk.Print(file); err != nil {
			t.Fatal(err)
		}
	}

	kept.keep()

	now := kept.read()
	want := sha256.Sum256([]byte("<?php\n"))
	if now[settled] != [2]string{walk.Mark(settled), hex.EncodeToString(want[:])} {
		t.Errorf("the settled file is kept as %q, where its size, time and print are", now[settled])
	}
	if _, held := now[fresh]; held {
		t.Errorf("a file written this second is kept, where a second edit in the same tick would not move its time")
	}
	if _, held := now[gone]; held {
		t.Errorf("a file that is gone is still kept")
	}
}
