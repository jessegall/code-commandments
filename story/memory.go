package story

import (
	"encoding/json"
	"fmt"
	"time"
)

const notAMemoryFile = "{not json"

// MemoryFile is the project's saved detector memory, read and changed by the relay one step at a time.
type MemoryFile struct {
	story *Story
}

// MemoryReading is what the saved detector memory held when the relay last read it.
type MemoryReading struct {
	Present  bool   `json:"present"`
	Readable bool   `json:"readable"`
	Size     int    `json:"size"`
	Entries  int    `json:"entries"`
	Build    string `json:"build"`
	Oldest   int64  `json:"oldest"`
	Newest   int64  `json:"newest"`
}

func (s *Story) MemoryFile() *MemoryFile {
	s.t.Helper()

	memory := &MemoryFile{story: s}
	s.t.Cleanup(func() { _, _ = s.journal.ChangesTheMemoryFile("remove", "", "", 0) })

	return memory
}

func (m *MemoryFile) Reads() MemoryReading {
	m.story.t.Helper()

	m.story.steps = append(m.story.steps, StepOf("reads the memory file"))

	return m.readNow()
}

func (m *MemoryFile) readNow() MemoryReading {
	m.story.t.Helper()

	if m.story.rehearsal {
		return MemoryReading{}
	}

	ran := m.changes("read", "", "", 0)

	var reading MemoryReading
	if err := json.Unmarshal([]byte(ran.Out), &reading); err != nil {
		m.story.Fails("the relay answered " + ran.Out + ", which is no reading of the memory file: " + err.Error())
	}

	return reading
}

func (m *MemoryFile) EventuallyHoldsEntries(within time.Duration) MemoryReading {
	m.story.t.Helper()

	m.story.steps = append(m.story.steps, StepOf("waits for the memory file to hold entries"))

	if m.story.rehearsal {
		return MemoryReading{}
	}

	for until := time.Now().Add(within); time.Now().Before(until); time.Sleep(betweenMoments) {
		if reading := m.readNow(); reading.Entries > 0 {
			return reading
		}
	}

	m.story.Fails(fmt.Sprintf("the memory file held no entries after %v: %+v", within, m.readNow()))

	return MemoryReading{}
}

func (m *MemoryFile) Ages(by time.Duration) {
	m.story.t.Helper()

	m.story.steps = append(m.story.steps, StepOf("ages the memory file by %s", by.String()))
	m.changes("age", "", "", int(by.Seconds()))
}

func (m *MemoryFile) Stamp(build string) {
	m.story.t.Helper()

	m.story.steps = append(m.story.steps, StepOf("stamps the memory file %s", build))
	m.changes("stamp", build, "", 0)
}

func (m *MemoryFile) Corrupt() {
	m.story.t.Helper()

	m.story.steps = append(m.story.steps, StepOf("corrupts the memory file"))
	m.changes("replace", "", notAMemoryFile, 0)
}

func (m *MemoryFile) Remove() {
	m.story.t.Helper()

	m.story.steps = append(m.story.steps, StepOf("removes the memory file"))
	m.changes("remove", "", "", 0)
}

func (m *MemoryFile) changes(action, build, text string, seconds int) Ran {
	m.story.t.Helper()

	ran, err := m.story.journal.ChangesTheMemoryFile(action, build, text, seconds)
	if err != nil || ran.Code != 0 {
		m.story.Fails("the memory file could not be asked to " + action + ": " + failure(ran, err))
	}

	return ran
}
