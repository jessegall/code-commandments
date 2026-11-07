package hooks

import (
	"strings"
	"sync"
	"testing"
	"time"
)

func TestOnlySoManyJudgingsRunAtOnceAndTheRestSayTheyWaited(t *testing.T) {
	var busiest, running int
	var own sync.Mutex
	var waiting sync.WaitGroup

	var said []string

	for range slotsToJudgeIn * 4 {
		waiting.Add(1)

		go func() {
			defer waiting.Done()

			waited := aSlotToJudgeIn()
			defer freeTheSlot()

			own.Lock()
			running++
			if running > busiest {
				busiest = running
			}
			said = append(said, waited)
			own.Unlock()

			time.Sleep(20 * time.Millisecond)

			own.Lock()
			running--
			own.Unlock()
		}()
	}

	waiting.Wait()

	if busiest > slotsToJudgeIn {
		t.Errorf("%d judgings ran at once where the service allows %d: a worker per core then reads a project "+
			"each, which is how two CPUs became five", busiest, slotsToJudgeIn)
	}

	told := 0
	for _, each := range said {
		if strings.Contains(each, "waited") {
			told++
		}
	}

	if told == 0 {
		t.Error("not one judging said it had waited for a slot, so a run held up by another looks simply slow")
	}
}
