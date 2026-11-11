//go:build story

package tools

import (
	"testing"
	"time"

	"github.com/jessegall/code-commandments/story"
)

func TestTheMemoryFileIsReadAndAgedByTheRelay(t *testing.T) {
	s := story.Begin(t)
	t.Cleanup(func() { s.WaitsForTheService("hooks", serviceReturnsWithin) })
	memory := s.MemoryFile()
	s.GivenTheTreeIsHeldFor(s.TestCheckout())
	s.Writes(story.ElseAfterRaise, "aged.py")
	s.Exits(s.AsksIn(s.TestCheckout(), "sins", "--changes"), 1)
	memory.StopsTheService()
	before := memory.Reads()

	memory.Ages(13 * time.Hour)

	after := memory.Reads()
	if after.Entries != before.Entries {
		s.Fails("ageing changed how many entries the file holds")
	}

	if before.Oldest-after.Oldest != int64(13*time.Hour) {
		s.Fails("the oldest entry did not move by thirteen hours")
	}
}

func TestACorruptedMemoryFileIsReplacedByTheRelay(t *testing.T) {
	s := story.Begin(t)
	memory := s.MemoryFile()

	memory.Corrupt()

	reading := memory.Reads()
	if !reading.Present || reading.Readable {
		s.Fails("garbage in the memory file does not read as unreadable")
	}

	memory.Remove()

	if memory.Reads().Present {
		s.Fails("the removed memory file is still there")
	}
}
