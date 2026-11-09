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

	answer, got := waitingFor(served, func(said string) { told = said }, 20*time.Millisecond, time.Minute, time.Minute)

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
	if _, got := waitingFor(served, func(string) {}, 20*time.Millisecond, time.Minute, time.Minute); got {
		t.Error("a decline that waiting cannot mend was reported as served")
	}

	if asks != 1 {
		t.Errorf("the run asked %d times about a decline that will never change", asks)
	}

	if waited := time.Since(began); waited > 5*time.Second {
		t.Errorf("the run waited %v for an answer that was final", waited.Round(time.Millisecond))
	}
}

func TestARunWaitingForTheTreeKeepsAskingThroughAServiceThatStopsAndReturns(t *testing.T) {
	asks := 0

	served := func() (Answer, bool) {
		asks++

		switch {
		case asks == 1:
			return Answer{Declined: true, Coming: true, Because: "a read of it is running now"}, false
		case asks < 5:
			return Answer{}, false
		}

		return Answer{Out: "judged"}, true
	}

	answer, got := waitingFor(served, func(string) {}, 5*time.Millisecond, time.Minute, time.Minute)

	if !got || answer.Out != "judged" {
		t.Errorf("the run gave up when the service stopped answering: served %v, %q", got, answer.Out)
	}
}

func TestARunWaitingForTheTreeGivesUpOnAServiceThatNeverReturns(t *testing.T) {
	asks := 0

	served := func() (Answer, bool) {
		asks++
		if asks == 1 {
			return Answer{Declined: true, Coming: true, Because: "a read of it is running now"}, false
		}

		return Answer{}, false
	}

	began := time.Now()
	if _, got := waitingFor(served, func(string) {}, 5*time.Millisecond, time.Minute, 50*time.Millisecond); got {
		t.Error("a service that never returned was reported as served")
	}

	if waited := time.Since(began); waited > 10*time.Second {
		t.Errorf("the run waited %v for a service that was gone", waited.Round(time.Millisecond))
	}
}

func TestARunNeverToldTheTreeIsComingDoesNotWaitForASilentService(t *testing.T) {
	asks := 0

	served := func() (Answer, bool) {
		asks++

		return Answer{}, false
	}

	if _, got := waitingFor(served, func(string) {}, 5*time.Millisecond, time.Minute, time.Minute); got {
		t.Error("no answer at all was reported as served")
	}

	if asks != 1 {
		t.Errorf("the run asked %d times when no service had ever said a tree was coming", asks)
	}
}

func TestARunStopsWaitingOnceItHasWaitedFarTooLong(t *testing.T) {
	served := func() (Answer, bool) {
		return Answer{Declined: true, Coming: true, Because: "a read of it is running now"}, false
	}

	began := time.Now()
	if _, got := waitingFor(served, func(string) {}, 10*time.Millisecond, 120*time.Millisecond, time.Minute); got {
		t.Error("a tree that never arrives was reported as served")
	}

	if waited := time.Since(began); waited > 10*time.Second {
		t.Errorf("the run waited %v for a tree that never came, so a judging can hang for ever on a read that "+
			"died", waited.Round(time.Millisecond))
	}
}

func TestARunWaitingForTheTreeKeepsSayingSo(t *testing.T) {
	told := 0

	waitingFor(func() (Answer, bool) {
		return Answer{Declined: true, Coming: true, Because: "a read runs"}, false
	}, func(string) { told++ }, time.Millisecond, 120*time.Millisecond, time.Hour)

	if told < 3 {
		t.Errorf("the run said it was waiting %d times in the whole wait", told)
	}
}
