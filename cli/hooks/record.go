package hooks

import (
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"time"
)

type Judged struct {
	At        time.Time
	Event     string
	Checkout  string
	Kind      string
	Branch    string
	Held      bool
	Why       Narrowed
	Overlay   int
	Parsed    int
	ReadMs    int64
	RuleMs    int64
	Asked     int
	NotAsked  int
	Sins      int
	Dropped   Dropped
	Memory    Memory
	Abandoned string
	Said      string
}

func (j Judged) Line() string {
	held := "held"
	if !j.Held {
		held = "declined"
	}

	said := []string{
		j.At.Format("2006-01-02 15:04:05"),
		j.Event,
		j.Kind + "=" + j.Checkout,
		"branch=" + j.Branch,
		"tree=" + held,
		fmt.Sprintf("overlay=%d", j.Overlay),
		fmt.Sprintf("parsed=%d in %dms", j.Parsed, j.ReadMs),
		fmt.Sprintf("rules=%d of %d in %dms", j.Asked, j.Asked+j.NotAsked, j.RuleMs),
		fmt.Sprintf("sins=%d", j.Sins),
	}

	if j.Dropped != (Dropped{}) {
		said = append(said, fmt.Sprintf("dropped=%d outside the roots, %d not a file, %d too large, %d past the budget",
			j.Dropped.NotJudged, j.Dropped.NotAFile, j.Dropped.TooLarge, j.Dropped.PastBudget))
	}

	if j.Memory != (Memory{}) {
		said = append(said, j.Memory.Line())
	}

	if j.Said != "" {
		said = append(said, "checkout from "+j.Said)
	}

	if j.Abandoned != "" {
		said = append(said, "abandoned, because "+j.Abandoned)
	}

	if j.Why != "" {
		said = append(said, "because "+string(j.Why))
	}

	return strings.Join(said, " | ")
}

func Record(folder string, judged Judged) {
	if folder == "" {
		return
	}

	file, err := os.OpenFile(filepath.Join(folder, "judgings.log"), os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0o644)
	if err != nil {
		return
	}
	defer file.Close()

	fmt.Fprintln(file, judged.Line())
}
