package atomic

import (
	"os"
	"path/filepath"
	"testing"
	"time"
)

func TestASecondRunStepsAsideWhileTheFirstHoldsTheClaim(t *testing.T) {
	claim := ClaimAt(filepath.Join(t.TempDir(), "reading"), 5*time.Minute)

	release, taken := claim.Take()
	if !taken {
		t.Fatal("the first run could not claim a project nobody reads")
	}

	started := time.Now()
	if _, again := claim.Take(); again {
		t.Error("a second run claimed the project while the first was reading it")
	}

	if waited := time.Since(started); waited > time.Second {
		t.Errorf("the second run waited %v instead of stepping aside at once", waited)
	}

	release()

	if _, after := claim.Take(); !after {
		t.Error("a run could not claim the project after the first let it go")
	}
}

func TestAClaimOlderThanItsExpiryCountsAsGone(t *testing.T) {
	path := filepath.Join(t.TempDir(), "reading")
	claim := ClaimAt(path, 5*time.Minute)

	claim.Take()

	crashed := time.Now().Add(-6 * time.Minute)
	if err := os.Chtimes(path, crashed, crashed); err != nil {
		t.Fatal(err)
	}

	if _, taken := claim.Take(); !taken {
		t.Error("a claim left six minutes ago by a run that crashed still blocks the project")
	}
}

func TestOnlyTheRunThatMadeAClaimRemovesIt(t *testing.T) {
	path := filepath.Join(t.TempDir(), "reading")
	claim := ClaimAt(path, 5*time.Minute)

	first, _ := claim.Take()
	crashed := time.Now().Add(-6 * time.Minute)
	os.Chtimes(path, crashed, crashed)

	second, taken := claim.Take()
	if !taken {
		t.Fatal("a claim left untouched past its expiry still blocks")
	}

	first()

	if _, again := claim.Take(); again {
		t.Error("the first run let go of the second run's claim")
	}

	second()
}

func TestAHeldClaimIsKeptAlivePastItsExpiry(t *testing.T) {
	path := filepath.Join(t.TempDir(), "reading")
	claim := ClaimAt(path, 300*time.Millisecond)

	release, _ := claim.Take()
	defer release()

	time.Sleep(time.Second)

	if _, taken := claim.Take(); taken {
		t.Error("a claim its run still holds was taken over once its expiry passed")
	}
}
