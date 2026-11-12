package engine_test

import (
	"os"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

const fillCounted contract.Language = "fillCounted-fill"

var fills int

func init() {
	engine.Fills(fillCounted, func(*engine.Codebase, []string) { fills++ })
}

func countedStream(t *testing.T) *contract.Stream {
	t.Helper()

	sampled, err := os.Open("../contract/samples/php.jsonl")
	if err != nil {
		t.Fatal(err)
	}
	defer sampled.Close()

	stream, err := contract.ReadAll(sampled)
	if err != nil {
		t.Fatal(err)
	}
	stream.Header.Language = fillCounted

	return stream
}

func padded(path string) ([]byte, error) {
	return []byte(path + strings.Repeat(" ", 1<<20)), nil
}

func TestARunWhoseEveryAnswerIsRememberedNeverFillsTheCodebase(t *testing.T) {
	stream := countedStream(t)
	memory := engine.Remembering(time.Now)
	detector := &asking{}
	fills = 0

	memory.Find(engine.Unfilled(padded, stream), detector.find)
	if fills != 1 || detector.asked == 0 {
		t.Fatalf("a first run that asked about %d files filled the codebase %d times; it fills once", detector.asked, fills)
	}

	asked := detector.asked
	memory.Find(engine.Unfilled(padded, stream), detector.find)

	if detector.asked != asked {
		t.Fatalf("the second run asked about %d more files though nothing changed", detector.asked-asked)
	}
	if fills != 1 {
		t.Errorf("a run whose every answer was remembered filled the codebase anyway (%d fills in all)", fills)
	}
}

func TestAnUnfilledCodebaseIsFilledOnceHoweverManyPartsAsk(t *testing.T) {
	codebase := engine.Unfilled(padded, countedStream(t))
	fills = 0

	codebase.Filled()
	codebase.Of(fillCounted).Filled()
	codebase.Reporting("x").Filled()

	if fills != 1 {
		t.Errorf("the codebase and two of its parts asked for its facts and it was filled %d times", fills)
	}
}
