package story

import (
	"bufio"
	"encoding/json"
	"net"
	"strings"
	"testing"
)

func relayAnswering(t *testing.T, answer func(request) Ran) (string, *[]request) {
	t.Helper()

	listener, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { listener.Close() })

	var asked []request

	go func() {
		for {
			connection, err := listener.Accept()
			if err != nil {
				return
			}

			line, _ := bufio.NewReader(connection).ReadBytes('\n')

			var one request
			_ = json.Unmarshal(line, &one)
			asked = append(asked, one)

			said, _ := json.Marshal(answer(one))
			_, _ = connection.Write(append(said, '\n'))
			connection.Close()
		}
	}()

	return listener.Addr().String(), &asked
}

func TestACommandReachesTheRelayWithItsWordsAndItsFolder(t *testing.T) {
	address, asked := relayAnswering(t, func(request) Ran { return Ran{Code: 0, Out: "created: environment:3\n"} })

	ran, err := Reached(address).Runs("/project/.journal/worktrees/story", "environment", "list")
	if err != nil {
		t.Fatal(err)
	}

	if ran.Out != "created: environment:3\n" {
		t.Errorf("the story was handed %q, where the relay answered with what the journal printed", ran.Out)
	}

	if len(*asked) != 1 {
		t.Fatalf("the relay was asked %d times for one command", len(*asked))
	}

	sent := (*asked)[0]
	if sent.Kind != "command" || strings.Join(sent.Argv, " ") != "environment list" || sent.In != "/project/.journal/worktrees/story" {
		t.Errorf("the relay was asked %+v, where the command, its words and the folder it runs in must all arrive", sent)
	}
}

func TestAHookCarriesItsBodyToTheRelay(t *testing.T) {
	address, asked := relayAnswering(t, func(request) Ran { return Ran{Code: 2, Err: "refused"} })

	ran, err := Reached(address).Hooks("/project", `{"hook_event_name":"Stop"}`)
	if err != nil {
		t.Fatal(err)
	}

	if ran.Code != 2 || ran.Err != "refused" {
		t.Errorf("the hook's answer came back as %+v, so a story could not see a refusal", ran)
	}

	if sent := (*asked)[0]; sent.Kind != "hook" || sent.Stdin != `{"hook_event_name":"Stop"}` {
		t.Errorf("the relay was asked %+v, where a hook's body is what the journal reads", sent)
	}
}

func TestARelayThatIsNotThereSaysSoInsteadOfHanging(t *testing.T) {
	listener, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		t.Fatal(err)
	}
	address := listener.Addr().String()
	listener.Close()

	_, err = Reached(address).Runs("/project", "environment", "list")
	if err == nil || !strings.Contains(err.Error(), "does not answer") {
		t.Errorf("a story with no relay was told %v, where it must say the relay does not answer", err)
	}
}
