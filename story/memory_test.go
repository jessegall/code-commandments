package story

import (
	"testing"
	"time"
)

func TestTheMemoryFileIsReadOffTheRelaysAnswer(t *testing.T) {
	s, _ := storyOver(t, func(request) Ran {
		return Ran{Out: `{"present": true, "readable": true, "size": 90, "entries": 3, "build": "b", "oldest": 7, "newest": 9}`}
	})

	reading := s.MemoryFile().Reads()

	if reading != (MemoryReading{Present: true, Readable: true, Size: 90, Entries: 3, Build: "b", Oldest: 7, Newest: 9}) {
		t.Errorf("the reading is %+v", reading)
	}
}

func TestAStoryChangesTheMemoryFileOneRelayStepAtATime(t *testing.T) {
	s, asked := storyOver(t, quiet)
	memory := s.MemoryFile()

	memory.Ages(13 * time.Hour)
	memory.Stamp("another")
	memory.Corrupt()
	memory.Remove()

	age, stamp, corrupt, remove := (*asked)[0], (*asked)[1], (*asked)[2], (*asked)[3]
	if age.Action != "age" || age.Many != 46800 {
		t.Errorf("the age asked is %+v", age)
	}

	if stamp.Action != "stamp" || stamp.Name != "another" {
		t.Errorf("the stamp asked is %+v", stamp)
	}

	if corrupt.Action != "replace" || corrupt.Stdin != notAMemoryFile {
		t.Errorf("the corruption asked is %+v", corrupt)
	}

	if remove.Action != "remove" {
		t.Errorf("the removal asked is %+v", remove)
	}
}
