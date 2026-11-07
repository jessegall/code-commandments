package warm

import (
	"testing"
	"time"
)

func TestARunWaitsForATreeOnItsWayRatherThanReadingTheProjectItself(t *testing.T) {
	asks := 0
	told := ""

	served := func() (Answer, bool) {
		asks++
		if asks < 3 {
			return Answer{Declined: true, Coming: true, Because: "a read of it is running now"}, false
		}

		return Answer{Out: "judged"}, true
	}

	answer, got := waitingFor(served, func(said string) { told = said }, 20*time.Millisecond, time.Minute)

	if !got {
		t.Fatal("the run gave up while the tree was on its way, so it reads every file of the project itself")
	}

	if answer.Out != "judged" {
		t.Errorf("the run was served %q", answer.Out)
	}

	if asks != 3 {
		t.Errorf("the run asked %d times; it must keep asking until the tree is there", asks)
	}

	if told == "" {
		t.Error("the run waited without a word, so a person watching it sees it hang for minutes with no reason given")
	}
}

func TestARunDoesNotWaitForADeclineWaitingCannotMend(t *testing.T) {
	asks := 0

	served := func() (Answer, bool) {
		asks++

		return Answer{Declined: true, Because: "this run reads no file of any language the tree holds"}, false
	}

	began := time.Now()
	if _, got := waitingFor(served, func(string) {}, 20*time.Millisecond, time.Minute); got {
		t.Error("a decline that waiting cannot mend was reported as served")
	}

	if asks != 1 {
		t.Errorf("the run asked %d times about a decline that will never change", asks)
	}

	if waited := time.Since(began); waited > 5*time.Second {
		t.Errorf("the run waited %v for an answer that was final", waited.Round(time.Millisecond))
	}
}

func TestARunStopsWaitingOnceItHasWaitedFarTooLong(t *testing.T) {
	served := func() (Answer, bool) {
		return Answer{Declined: true, Coming: true, Because: "a read of it is running now"}, false
	}

	began := time.Now()
	if _, got := waitingFor(served, func(string) {}, 10*time.Millisecond, 120*time.Millisecond); got {
		t.Error("a tree that never arrives was reported as served")
	}

	if waited := time.Since(began); waited > 10*time.Second {
		t.Errorf("the run waited %v for a tree that never came, so a judging can hang for ever on a read that "+
			"died", waited.Round(time.Millisecond))
	}
}
