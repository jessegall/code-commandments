package scan

import (
	"testing"

	"github.com/jessegall/code-commandments/contract"
)

func streamHolding(count int) *contract.Stream {
	stream := &contract.Stream{}
	for range count {
		stream.Files = append(stream.Files, &contract.File{Path: "/a.py"})
	}

	return stream
}

func TestAScanThatComesOutWithMoreFilesThanItWasGivenPanics(t *testing.T) {
	defer func() {
		if recover() == nil {
			t.Error("a bridge answered with more files than it was given, and the scan went on")
		}
	}()

	stayedWithin(streamHolding(2), 1, "Python")
}

func TestAScanOfExactlyItsFilesDoesNotPanic(t *testing.T) {
	stayedWithin(streamHolding(1), 1, "Python")
	stayedWithin(streamHolding(0), 1, "Python")
}
