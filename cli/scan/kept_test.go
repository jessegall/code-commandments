package scan

import (
	"os"
	"path/filepath"
	"strconv"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/contract"
)

const servedHeader = `{"header": {"contract": "tree", "version": 1, "language": "python", "bridge": {"name": "test", "version": "1"}, "roots": ["/abs/src"]}}`

// bridgeServingOnce is a served bridge that answers one request and then ends, as a bridge does when it is killed
// for staying silent longer than a read may wait. It counts the processes started in a file, since each start is a
// process of its own.
func bridgeServingOnce(t *testing.T) (command string, starts func() int) {
	t.Helper()
	folder := t.TempDir()
	counter := filepath.Join(folder, "starts")
	script := filepath.Join(folder, "bridge")

	written := "#!/bin/sh\nstarts=$(cat " + counter + " 2>/dev/null || echo 0)\n" +
		"echo $((starts + 1)) > " + counter + "\n" +
		"read line\n" +
		"printf '%s\\n' '" + servedHeader + "'\n" +
		"printf '%s\\n' '{\"trailer\": {\"files\": 0}}'\n"

	if err := os.WriteFile(script, []byte(written), 0o755); err != nil {
		t.Fatal(err)
	}

	return script, func() int {
		raw, err := os.ReadFile(counter)
		if err != nil {
			return 0
		}
		count, _ := strconv.Atoi(string(raw[:len(raw)-1]))

		return count
	}
}

// servedBy is the asker a service holds for a bridge started by the script.
func servedBy(t *testing.T, script string) bridge.Asker {
	t.Helper()
	reach := Process{Command: func([]string) ([]string, error) { return []string{script}, nil }}
	held := Bridge{Kind: "test", Label: "Test", Languages: []source.Language{source.Python}, Reach: reach}

	asker, needed, err := reach.Own(held, []string{"/abs/src"})
	if err != nil || !needed {
		t.Fatalf("the service holds no bridge: needed %v, %v", needed, err)
	}
	t.Cleanup(func() { asker.Close() })

	return asker
}

// TestAServiceWhoseBridgeDiedAnswersFromAFreshOne holds the whole point of the fix. A bridge dies under one request
// for reasons that are no fault of the next — killed for a silence longer than a read may wait, or gone of its own
// accord — and the service used to keep the corpse and answer every later request with a broken pipe for the rest of
// its life: six deaths cost one project 4,159 unanswered requests, with every Python rule silent the whole time.
func TestAServiceWhoseBridgeDiedAnswersFromAFreshOne(t *testing.T) {
	script, starts := bridgeServingOnce(t)
	asker := servedBy(t, script)

	if _, err := asker.AskTallied(bridge.Request{Paths: []string{"/abs/src"}}, func() {}); err != nil {
		t.Fatalf("the first ask was not answered: %v", err)
	}

	stream, err := asker.AskTallied(bridge.Request{Paths: []string{"/abs/src"}}, func() {})
	if err != nil {
		t.Fatalf("the ask after the bridge died was not answered: %v", err)
	}
	if stream == nil {
		t.Fatal("the ask after the bridge died answered no stream")
	}
	if started := starts(); started != 2 {
		t.Errorf("%d bridges were started, want a fresh one for the second ask", started)
	}
}

// TestAServiceAsksTheOneBridgeWhileItAnswers holds the other half: a bridge is replaced only when it has gone, never
// per request, since starting one costs the whole project being read again.
func TestAServiceAsksTheOneBridgeWhileItAnswers(t *testing.T) {
	folder := t.TempDir()
	counter := filepath.Join(folder, "starts")
	script := filepath.Join(folder, "bridge")
	written := "#!/bin/sh\nstarts=$(cat " + counter + " 2>/dev/null || echo 0)\n" +
		"echo $((starts + 1)) > " + counter + "\n" +
		"while read line; do\n" +
		"  printf '%s\\n' '" + servedHeader + "'\n" +
		"  printf '%s\\n' '{\"trailer\": {\"files\": 0}}'\n" +
		"done\n"
	if err := os.WriteFile(script, []byte(written), 0o755); err != nil {
		t.Fatal(err)
	}

	asker := servedBy(t, script)

	for ask := range 3 {
		if _, err := asker.AskTallied(bridge.Request{Paths: []string{"/abs/src"}}, func() {}); err != nil {
			t.Fatalf("ask %d was not answered: %v", ask+1, err)
		}
	}

	raw, err := os.ReadFile(counter)
	if err != nil {
		t.Fatal(err)
	}
	started, _ := strconv.Atoi(string(raw[:len(raw)-1]))
	if started != 1 {
		t.Errorf("%d bridges were started for three asks, want the one", started)
	}
}

// TestAServiceWhoseBridgeDiedMidStreamStartsAFreshOneForTheNextRequest holds the line-by-line path, which a service
// answers its socket with. The lines already handed over cannot be handed over again, so that request fails; what
// must not happen is the next one failing too.
func TestAServiceWhoseBridgeDiedMidStreamStartsAFreshOneForTheNextRequest(t *testing.T) {
	folder := t.TempDir()
	counter := filepath.Join(folder, "starts")
	script := filepath.Join(folder, "bridge")
	// The first process writes a header and dies with no trailer; the second answers in full.
	written := "#!/bin/sh\nstarts=$(cat " + counter + " 2>/dev/null || echo 0)\n" +
		"echo $((starts + 1)) > " + counter + "\n" +
		"read line\n" +
		"printf '%s\\n' '" + servedHeader + "'\n" +
		"if [ \"$starts\" = \"0\" ]; then exit 1; fi\n" +
		"printf '%s\\n' '{\"trailer\": {\"files\": 0}}'\n"
	if err := os.WriteFile(script, []byte(written), 0o755); err != nil {
		t.Fatal(err)
	}

	asker := servedBy(t, script)

	lines := 0
	if err := asker.AskEach(bridge.Request{Paths: []string{"/abs/src"}}, func(contract.Line, []byte) error {
		lines++

		return nil
	}); err == nil {
		t.Error("a bridge that died mid-stream answered as if it had finished")
	}
	if lines == 0 {
		t.Error("the lines the dying bridge did write were not handed over")
	}

	if err := asker.AskEach(bridge.Request{Paths: []string{"/abs/src"}}, func(contract.Line, []byte) error {
		return nil
	}); err != nil {
		t.Errorf("the request after the death was not answered either: %v", err)
	}
}
