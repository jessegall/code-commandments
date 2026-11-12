package atomic

import (
	"os"
	"os/exec"
	"path/filepath"
	"testing"
	"time"
)

func TestASecondRunStepsAsideWhileTheFirstHoldsTheClaim(t *testing.T) {
	claim := ClaimAt(filepath.Join(t.TempDir(), "reading"))

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

const claimHolder = "COMMANDMENTS_TEST_CLAIM_HOLDER"

func TestHoldTheClaimUntilKilled(t *testing.T) {
	path := os.Getenv(claimHolder)
	if path == "" {
		t.Skip("only run as the process another test kills")
	}

	if _, taken := ClaimAt(path).Take(); !taken {
		os.Exit(3)
	}
	os.WriteFile(path+".held", nil, 0o644)

	time.Sleep(time.Minute)
}

func TestARunKilledWhileItHoldsTheClaimBlocksNobody(t *testing.T) {
	path := filepath.Join(t.TempDir(), "reading")

	holder := exec.Command(os.Args[0], "-test.run=^TestHoldTheClaimUntilKilled$")
	holder.Env = append(os.Environ(), claimHolder+"="+path)
	if err := holder.Start(); err != nil {
		t.Fatal(err)
	}

	for deadline := time.Now().Add(10 * time.Second); ; time.Sleep(10 * time.Millisecond) {
		if _, err := os.Stat(path + ".held"); err == nil {
			break
		}
		if time.Now().After(deadline) {
			holder.Process.Kill()
			t.Fatal("the run that should hold the claim never took it")
		}
	}

	if _, taken := ClaimAt(path).Take(); taken {
		t.Error("the project was claimed while another live run held it")
	}

	holder.Process.Kill()
	holder.Wait()

	release, taken := ClaimAt(path).Take()
	if !taken {
		t.Error("a run that was killed while it held the claim still blocks the next run")
	}
	release()
}
