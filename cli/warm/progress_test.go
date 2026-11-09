package warm

import (
	"bufio"
	"net"
	"reflect"
	"testing"
)

func answeringWith(t *testing.T, project string, lines ...[]byte) {
	t.Helper()

	listener, err := net.Listen("unix", Socket(project))
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { listener.Close() })

	go func() {
		connection, err := listener.Accept()
		if err != nil {
			return
		}
		defer connection.Close()

		if _, err := bufio.NewReader(connection).ReadBytes('\n'); err != nil {
			return
		}

		for _, line := range lines {
			connection.Write(line)
		}
	}()
}

func TestProgressLinesReachTheRunBeforeItsAnswer(t *testing.T) {
	project := t.TempDir()
	answeringWith(t, project, Progressed("reading 1/2"), Progressed("detector 2/2"), Answered(Answer{Out: "judged", Code: 1}))

	var heard []string
	answer, asked := Asked(project, Request{Judge: []string{}, In: project, Progress: true}, func(line string) { heard = append(heard, line) })

	if !asked || answer.Out != "judged" || answer.Code != 1 {
		t.Fatalf("the answer was lost behind the progress: %+v, asked %v", answer, asked)
	}

	if want := []string{"reading 1/2", "detector 2/2"}; !reflect.DeepEqual(heard, want) {
		t.Fatalf("heard %v, want %v", heard, want)
	}
}

func TestAnAnswerWithNoProgressAheadOfItStillReads(t *testing.T) {
	project := t.TempDir()
	answeringWith(t, project, Answered(Answer{Out: "judged"}))

	answer, asked := Asked(project, Request{Judge: []string{}, In: project}, func(line string) { t.Fatalf("no progress was sent, heard %q", line) })

	if !asked || answer.Out != "judged" {
		t.Fatalf("got %+v, asked %v", answer, asked)
	}
}
