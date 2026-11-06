package hooks

import (
	"bufio"
	"encoding/json"
	"net"
	"os"
	"sort"
	"strconv"
	"strings"
	"time"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/help"
)

const lanesEvent = "commandments.lanes"

// Lanes is `lanes`: what the running service holds, and the way to drop a lane so the next question reads it again.
type Lanes struct{}

func (Lanes) Names() []string {
	return []string{"lanes"}
}

func (Lanes) Help() help.Help {
	return help.Of("Say what the running service holds: a lane per checkout with the commit it was cut from, how much of it is held and when it last answered, the trees held for the project, and the ceiling over them all.").
		Form("lanes", "what the service holds now").
		Form("lanes --drop <checkout>", "let that lane go, so the next question about it reads the checkout again").
		Form("lanes --drop-all", "let every lane go").
		Option("--drop", "the checkout whose lane to let go").
		Option("--drop-all", "let every lane go").
		Note("It asks the running service, since what is held lives in that process and nowhere else. With no service " +
			"running there is nothing to say, and a judging then reads what it needs each time. Dropping a lane is how " +
			"a stale answer is told from a wrong rule: drop it, ask again, and compare.").
		In(help.Hooks)
}

func (Lanes) Run(in *cli.Input, console cli.Console) (int, error) {
	asked := map[string]any{"event": lanesEvent}

	dropped, named := in.Option("drop")

	switch {
	case in.HasFlag("drop-all"):
		asked["drop"] = "*"
	case named && dropped != "":
		asked["drop"] = dropped
	}

	said, err := askTheService(asked)
	if err != nil {
		console.Warn("✋ no service is running here, so nothing is held: a judging reads what it needs each time.")

		return 2, nil
	}

	console.Say(said)

	return 0, nil
}

func askTheService(asked map[string]any) (string, error) {
	path := os.Getenv(pluginSocket)
	if path == "" {
		return "", os.ErrNotExist
	}

	connection, err := net.Dial("unix", path)
	if err != nil {
		return "", err
	}
	defer connection.Close()

	line, err := json.Marshal(asked)
	if err != nil {
		return "", err
	}

	if _, err := connection.Write(append(line, '\n')); err != nil {
		return "", err
	}

	connection.SetDeadline(time.Now().Add(5 * time.Second))

	read, err := bufio.NewReader(connection).ReadString('\n')
	if err != nil && read == "" {
		return "", err
	}

	var answer map[string]any
	if json.Unmarshal([]byte(strings.TrimSpace(read)), &answer) != nil {
		return strings.TrimSpace(read), nil
	}

	return asText(answer["tell"]), nil
}

func lanesAsked(line map[string]any) (string, bool) {
	if asText(line["event"]) != lanesEvent {
		return "", false
	}

	return asText(line["drop"]), true
}

func lanesHeld(drop string) string {
	if drop == "*" {
		return "Let go of " + strconv.Itoa(kept.DropsEveryLane()) + " lane(s). The next question about each reads its checkout again."
	}

	if drop != "" {
		if !kept.DropsLane(drop) {
			return "No lane of " + drop + " was held, so there was nothing to let go."
		}

		return "Let go of the lane of " + drop + ". The next question about it reads the checkout again."
	}

	lanes := kept.Lanes()
	if len(lanes) == 0 {
		return "The service holds no lane: every question so far was answered from what it read at the time."
	}

	sort.Slice(lanes, func(one, two int) bool { return lanes[one].Checkout < lanes[two].Checkout })

	said := []string{strconv.Itoa(len(lanes)) + " lane(s) held, " + strconv.Itoa(kept.NodesHeld()) + " nodes in all" + ceilingSaid()}
	for _, lane := range lanes {
		said = append(said, "  "+lane.Checkout+
			" | cut from "+orNothing(lane.Cut)+
			" | "+strconv.Itoa(lane.Nodes)+" nodes of "+strconv.Itoa(lane.Files)+" file(s)"+
			" | asked "+lane.Asked.Format("15:04:05")+
			" | "+lane.Filling)
	}

	return strings.Join(said, "\n")
}

func ceilingSaid() string {
	held, ceiling, past := kept.PastItsCeiling()
	if ceiling == 0 {
		return ", with no ceiling over them"
	}

	said := ", of a ceiling of " + strconv.Itoa(ceiling)
	if past {
		said += ", which " + strconv.Itoa(held) + " is already past, so no new lane is opened"
	}

	return said
}

func orNothing(said string) string {
	if said == "" {
		return "no commit the journal named"
	}

	return said
}
