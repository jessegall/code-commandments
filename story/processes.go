package story

import (
	"encoding/json"
	"fmt"
	"slices"
	"strings"
	"time"
)

const Terminates, Kills = "SIGTERM", "SIGKILL"

const noParentLeft = "1"

// Process is one of the plugin's processes on the host, as the relay read it.
type Process struct {
	Pid     string
	Parent  string
	Command string
}

// Service is a throwaway service a story started under a parent of its own, in a folder of its own.
type Service struct {
	story      *Story
	Pid        int    `json:"pid"`
	Parent     int    `json:"parent"`
	Socket     string `json:"socket"`
	DataFolder string `json:"data"`
}

func (s *Story) Processes() []Process {
	s.t.Helper()

	s.steps = append(s.steps, StepOf("reads the plugin's processes"))

	return s.processesNow()
}

func (s *Story) processesNow() []Process {
	s.t.Helper()

	ran, err := s.journal.ListsProcesses()
	if err != nil || ran.Code != 0 {
		s.Fails("the plugin's processes could not be read: " + failure(ran, err))
	}

	return processesIn(ran.Out)
}

func processesIn(listed string) []Process {
	var processes []Process

	for _, line := range strings.Split(strings.TrimSpace(listed), "\n") {
		if fields := strings.SplitN(line, "\t", 3); len(fields) == 3 {
			processes = append(processes, Process{Pid: fields[0], Parent: fields[1], Command: fields[2]})
		}
	}

	return processes
}

func (s *Story) TheService(verb string) Process {
	s.t.Helper()

	s.steps = append(s.steps, StepOf("finds the service %s", verb))

	if s.rehearsal {
		return Process{Pid: "2", Parent: "3", Command: verb}
	}

	processes := s.processesNow()

	at := slices.IndexFunc(processes, func(each Process) bool { return strings.Contains(each.Command, verb) })
	if at < 0 {
		s.Fails(fmt.Sprintf("no process of the plugin runs %s; the plugin's processes are:\n%v", verb, processes))
	}

	return processes[at]
}

func (s *Story) NoOrphans() {
	s.t.Helper()

	s.steps = append(s.steps, StepOf("finds no process of the plugin without a parent"))

	if orphans := orphansIn(s.processesNow()); len(orphans) > 0 {
		s.Fails(fmt.Sprintf("these processes outlived their parent: %v", orphans))
	}
}

func orphansIn(processes []Process) []Process {
	var orphans []Process

	for _, process := range processes {
		if process.Parent == noParentLeft {
			orphans = append(orphans, process)
		}
	}

	return orphans
}

func (s *Story) StopsAService(pid, signal string) Ran {
	s.t.Helper()

	s.steps = append(s.steps, StepOf("stops a service with "+signal+", process %s", pid))

	ran, err := s.journal.StopsAService(pid, signal)
	if err != nil {
		s.Fails(err.Error())
	}

	return ran
}

func (s *Story) EventuallyGone(pid string, within time.Duration) {
	s.t.Helper()

	s.steps = append(s.steps, StepOf("sees process %s gone", pid))

	if s.rehearsal {
		return
	}

	for until := time.Now().Add(within); time.Now().Before(until); time.Sleep(betweenMoments) {
		if !holdsPid(s.processesNow(), pid) {
			return
		}
	}

	s.Fails(fmt.Sprintf("process %s was still there after %v", pid, within))
}

func holdsPid(processes []Process, pid string) bool {
	return slices.ContainsFunc(processes, func(each Process) bool { return each.Pid == pid })
}

func (s *Story) WaitsForTheService(name string, within time.Duration) Ran {
	s.t.Helper()

	s.steps = append(s.steps, StepOf("waits for the service %s", name))

	ran, err := s.journal.WaitsForTheService(name, int(within.Seconds()))
	if err != nil {
		s.Fails(err.Error())
	}

	return ran
}

func (s *Story) StartsAPlainProcess() string {
	s.t.Helper()

	s.steps = append(s.steps, StepOf("starts a process that is not the plugin's"))

	process, err := s.journal.StartsProcess()
	if err != nil || process.Code != 0 {
		s.Fails("a process could not be started: " + failure(process, err))
	}

	pid := strings.TrimSpace(process.Out)
	s.t.Cleanup(func() { _, _ = s.journal.EndsProcess(pid) })

	return pid
}

func (s *Story) StartsAServiceUnderAParent(kind string) *Service {
	s.t.Helper()

	s.steps = append(s.steps, StepOf("starts a throwaway "+kind+" service under a parent of its own"))

	if s.rehearsal {
		return &Service{story: s}
	}

	ran, err := s.journal.StartsAThrowawayService(kind)
	if err != nil || ran.Code != 0 {
		s.Fails("the throwaway service could not start: " + failure(ran, err))
	}

	service := &Service{story: s}
	if err := json.Unmarshal([]byte(ran.Out), service); err != nil {
		s.Fails("the relay answered " + ran.Out + ", which names no service: " + err.Error())
	}

	s.tearsDownAtTheEnd()

	return service
}

func (s *Story) tearsDownAtTheEnd() {
	if s.tearsDown {
		return
	}

	s.tearsDown = true

	s.t.Cleanup(func() {
		if ran, err := s.journal.TearsDownThrowawayServices(); err != nil || ran.Code != 0 {
			s.t.Errorf("the throwaway services were not all gone at the end: %s", failure(ran, err))
		}
	})
}

func (s *Service) IsListedUnderItsParent() {
	s.story.t.Helper()

	s.story.steps = append(s.story.steps, StepOf("sees the throwaway service under its parent"))

	if s.story.rehearsal {
		return
	}

	processes := s.story.processesNow()

	listed := slices.ContainsFunc(processes, func(each Process) bool {
		return each.Pid == fmt.Sprint(s.Pid) && each.Parent == fmt.Sprint(s.Parent)
	})
	if !listed {
		s.story.Fails(fmt.Sprintf("service %d under parent %d is not listed; the plugin's processes are:\n%v", s.Pid, s.Parent, processes))
	}
}

func (s *Service) EndsItsParent() {
	s.story.t.Helper()

	s.story.Exits(s.story.EndsAThrowawayParent(fmt.Sprint(s.Parent)), 0)
}

func (s *Story) EndsAThrowawayParent(pid string) Ran {
	s.t.Helper()

	s.steps = append(s.steps, StepOf("ends the parent %s", pid))

	ran, err := s.journal.EndsAThrowawayParent(pid)
	if err != nil {
		s.Fails(err.Error())
	}

	return ran
}
