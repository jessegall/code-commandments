package story

import (
	"fmt"
	"path/filepath"
	"strings"
	"sync"
	"time"
)

var sweepFalls = 35 * time.Second

func (s *Story) Given(fixture string, build func()) {
	s.t.Helper()

	before := len(s.steps)

	build()

	s.steps = append(s.steps[:before], StepOf("given "+shapeOf(fixture)))
}

func (s *Story) WritesMany(sample Sample, many int) {
	s.t.Helper()

	s.steps = append(s.steps, StepOf("writes many files like "+shapeOf(sample.Label)+", %s of them", fmt.Sprint(many)))

	s.writesManyIn(s.TestCheckout(), sample, many)
}

func (s *Story) writesManyIn(checkout string, sample Sample, many int) {
	s.t.Helper()

	folder := s.ScratchIn(checkout, sample.Under)
	s.removesAtTheEnd(folder)

	if ran, err := s.journal.WritesMany(filepath.Join(folder, "many.py"), sample.Text, many); err != nil || ran.Code != 0 {
		s.Fails("the files could not be written: " + failure(ran, err))
	}
}

func (s *Story) LetsTheSweepFall() {
	s.t.Helper()

	s.steps = append(s.steps, StepOf("lets the service's sweep come due"))

	if s.rehearsal {
		return
	}

	time.Sleep(sweepFalls)
}

func (s *Story) LetsTimePass(while time.Duration) {
	s.t.Helper()

	s.steps = append(s.steps, StepOf("lets %s pass", while.String()))

	if s.rehearsal {
		return
	}

	time.Sleep(while)
}

func (s *Story) AsksTogether(folder string, asked ...[]string) []Ran {
	s.t.Helper()

	s.steps = append(s.steps, StepOf("asks the journal %s things at once", fmt.Sprint(len(asked))))

	answers := make([]Ran, len(asked))

	var together sync.WaitGroup

	for at, argv := range asked {
		together.Add(1)

		go func() {
			defer together.Done()

			ran, err := s.journal.Runs(folder, argv...)
			if err != nil {
				ran = Ran{Code: 126, Err: err.Error()}
			}

			answers[at] = ran
		}()
	}

	together.Wait()

	return answers
}

func (s *Story) SwitchesToAnotherBranch() {
	s.t.Helper()

	s.moves("switch", "switches the test checkout to another branch")
}

func (s *Story) DetachesTheHead() {
	s.t.Helper()

	s.moves("detach", "detaches the head of the test checkout")
}

func (s *Story) moves(action, step string) {
	s.t.Helper()

	s.steps = append(s.steps, StepOf(step))

	s.t.Cleanup(func() { _, _ = s.journal.Moves("back") })

	if ran, err := s.journal.Moves(action); err != nil || ran.Code != 0 {
		s.Fails("the test checkout could not be moved: " + failure(ran, err))
	}
}

func (s *Story) Merges(worker *Agent) Ran {
	s.t.Helper()

	s.Exits(s.AsksIn(s.TestCheckout(), "proof", "check", worker.proof, "--environment", testEnvironment), 0)
	row := strings.TrimSpace(worker.row)

	s.Exits(s.asksPast("has delivered nothing", "worker", "logs", row, "--environment", testEnvironment), 0)

	tip := s.branchTip()
	merged := s.asksPast("has a command running", "worker", "merge", row, "--environment", testEnvironment)

	s.landsPast(tip)

	worker.folder = s.TestCheckout()
	worker.merged = true

	return merged
}

func (s *Story) landsPast(tip string) {
	s.t.Helper()

	if s.rehearsal {
		return
	}

	for until := time.Now().Add(landsWithin); time.Now().Before(until); time.Sleep(betweenMoments) {
		if s.branchTip() != tip {
			return
		}
	}

	s.Fails("the merge never landed on the story branch")
}

func (s *Story) asksPast(holding string, argv ...string) Ran {
	s.t.Helper()

	answer := s.AsksIn(s.TestCheckout(), argv...)
	for until := time.Now().Add(landsWithin); strings.Contains(answer.Text(), holding) && time.Now().Before(until); {
		time.Sleep(betweenMoments)
		answer = s.AsksIn(s.TestCheckout(), argv...)
	}

	return answer
}
