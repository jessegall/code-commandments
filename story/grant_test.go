package story

import (
	"strings"
	"testing"
)

const labEnvironments = `{"rows": [{"id": 2, "name": "main"}, {"id": 3, "name": "story-engine"}]}`

func TestTheGrantNamesTheDispatchingAgentInTheStoryEnvironment(t *testing.T) {
	grant, err := dispatchGrantOf(Ran{Out: labEnvironments}, &Agent{name: "Ada", row: "41"})
	if err != nil {
		t.Fatal(err)
	}

	if line := strings.Join(grant[:6], " "); line != "environment edit 3 --test --test-agent-id 41" {
		t.Errorf("the grant reads %q, where the story-engine row must be edited to be a test environment naming agent 41", line)
	}
}

func TestAListThatSaysNothingFoundIsNamedInTheFailure(t *testing.T) {
	_, err := dispatchGrantOf(Ran{Out: "nothing found\n"}, &Agent{name: "Ada", row: "41"})
	if err == nil || !strings.Contains(err.Error(), "nothing found") {
		t.Errorf("a list that said nothing found failed with %v, which does not name what the journal printed", err)
	}
}

func TestAListWithoutTheStoryEnvironmentIsNamedInTheFailure(t *testing.T) {
	_, err := dispatchGrantOf(Ran{Out: `{"rows": [{"id": 2, "name": "main"}]}`}, &Agent{name: "Ada", row: "41"})
	if err == nil || !strings.Contains(err.Error(), testEnvironment) || !strings.Contains(err.Error(), `"main"`) {
		t.Errorf("a list without %s failed with %v, which names neither the environment nor what was listed", testEnvironment, err)
	}
}

func TestAStoryListsTheEnvironmentsThenEditsTheStoryOneToNameTheAgent(t *testing.T) {
	address, asked := relayAnswering(t, func(sent request) Ran {
		if strings.Join(sent.Argv, " ") == "environment list --json" {
			return Ran{Out: labEnvironments}
		}

		return Ran{Out: "updated: environment:3\n"}
	})

	story := &Story{t: t, journal: Reached(address), project: "/lab"}
	story.grantsDispatchTo(&Agent{s: story, name: "Ada", row: "41"})

	if len(*asked) != 2 {
		t.Fatalf("the relay was asked %d times, where the story lists once and edits once", len(*asked))
	}

	edit := (*asked)[1]
	if edit.In != "/lab" || !strings.HasPrefix(strings.Join(edit.Argv, " "), "environment edit 3 --test --test-agent-id 41 --reason") {
		t.Errorf("the second ask was %+v, which does not edit the story environment from the project to name agent 41", edit)
	}
}
