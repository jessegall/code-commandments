package hooks

import (
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"time"
)

type Judged struct {
	At       time.Time
	Event    string
	Checkout string
	Kind     string
	Branch   string
	Held     bool
	Why      Narrowed
	Parsed   int
	// Kept is how many of the parsed files the cache served, and ReadWaitMs how long the read waited for another run
	// reading the same checkout.
	Kept       int
	Missed     []string
	ReadWaitMs int64
	ReadMs     int64
	RuleMs     int64
	Asked      int
	NotAsked   int
	Sins       int
	Dropped    Dropped
	Memory     Memory
	Abandoned  string
	Said       string
	Waited     string
	Files      []string
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
		fmt.Sprintf("parsed=%d in %dms", j.Parsed, j.ReadMs) + j.readFrom(),
		fmt.Sprintf("rules=%d of %d in %dms", j.Asked, j.Asked+j.NotAsked, j.RuleMs),
		fmt.Sprintf("sins=%d", j.Sins),
	}

	if len(j.Files) > 0 {
		said = append(said, "files="+strings.Join(j.Files, " "))
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

	if j.Waited != "" {
		said = append(said, j.Waited)
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
	logFromTheService(judged.Line())

	if folder == "" {
		return
	}

	if err := os.MkdirAll(folder, 0o755); err != nil {
		return
	}

	file, err := os.OpenFile(filepath.Join(folder, "judgings.log"), os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0o644)
	if err != nil {
		return
	}
	defer file.Close()

	fmt.Fprintln(file, judged.Line())
}

// readFrom says how many parsed files the cache served and how long the read waited its turn, when either is known.
func (j Judged) readFrom() string {
	if j.Kept == 0 && j.ReadWaitMs == 0 {
		return ""
	}

	return fmt.Sprintf(" (%d from the cache, %dms waiting for the checkout)", j.Kept, j.ReadWaitMs) + j.missedFiles()
}

// missedFilesShown is how many of the files the cache missed a line names, so a churning cache can be traced.
const missedFilesShown = 12

func (j Judged) missedFiles() string {
	if len(j.Missed) == 0 {
		return ""
	}
	var named []string
	for _, file := range j.Missed[:min(len(j.Missed), missedFilesShown)] {
		named = append(named, strings.TrimPrefix(file, strings.TrimRight(j.Checkout, "/")+"/"))
	}
	if more := len(j.Missed) - len(named); more > 0 {
		named = append(named, fmt.Sprintf("and %d more", more))
	}

	return " missed " + strings.Join(named, ", ")
}
