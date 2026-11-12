package hooks

import (
	"bufio"
	"encoding/json"
	"net"
	"os"
	"os/exec"
	"sort"
	"strconv"
	"strings"
	"sync"
	"sync/atomic"
	"time"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/warm"
	"github.com/jessegall/code-commandments/cli/workspace"
)

const lanesEvent = "commandments.lanes"

// Lanes is `lanes`: what the running service holds, and the way to drop a lane so the next question reads it again.
type Lanes struct{}

const droppingTheTree = "tree:"

func (Lanes) Names() []string {
	return []string{"lanes"}
}

func (Lanes) Help() help.Help {
	return help.Of("Say what the running service holds: the trees held for the project, the files it knows by their contents, the ceiling over them all, and what the last merges and hand-ins brought in.").
		Form("lanes", "what the service holds now").
		Form("lanes --drop-tree=<checkout>", "let the tree of that checkout go with its saved copy, so the next question reads the whole tree again").
		Option("--drop-tree=<checkout>", "the checkout whose tree to let go, with its saved copy").
		Note("It asks the running service, since what is held lives in that process and nowhere else. With no service " +
			"running there is nothing to say, and a judging then reads what it needs each time. Dropping a tree is how " +
			"a stale answer is told from a wrong rule: drop it, ask again, and compare. What the last merges and " +
			"hand-ins brought in is read from the project's own state, so it is there whether a service is running or " +
			"not: the journal drops a line it has no agent to tell, and a merge run at a terminal has nobody.").
		In(help.Hooks)
}

func (Lanes) Run(in *cli.Input, console cli.Console) (int, error) {
	asked := map[string]any{"event": lanesEvent}

	if tree, named := in.Option("drop-tree"); named && tree != "" {
		asked["drop"] = droppingTheTree + tree
	}

	said, err := askTheService(asked)
	if err != nil {
		console.Warn("✋ no service is running here, so nothing is held: a judging reads what it needs each time." +
			mergesSaid())

		return 2, nil
	}

	console.Say(said.tell)
	said.shownInTheChat()

	return 0, nil
}

// lanesAnswer is what the service says about what it holds: the whole of it, and the one line a chat card leads with.
type lanesAnswer struct {
	tell  string
	label string
}

func (a lanesAnswer) shownInTheChat() {
	journal := journalBinary()
	if journal == "" || a.label == "" {
		return
	}

	card := exec.Command(journal, "plugin", "card", workspace.JournalPlugin, "--label", a.label, "--detail", a.tell,
		"--tone", "note")
	card.Env = os.Environ()

	logStarted("journal plugin card | label=" + a.label)

	_ = card.Run()
}

func lanesAnswered(drop string) []byte {
	said, _ := json.Marshal(map[string]any{"v": protocol4, "answer": "tell", "tell": lanesHeld(drop), "label": lanesLabel()})

	return append(said, '\n')
}

func theServicesSocket() string {
	if path := os.Getenv(pluginSocket); path != "" {
		return path
	}

	return warm.Socket(git.ProjectRoot(cwdOf()))
}

func askTheService(asked map[string]any) (lanesAnswer, error) {
	path := theServicesSocket()

	connection, err := net.Dial("unix", path)
	if err != nil {
		return lanesAnswer{}, err
	}
	defer connection.Close()

	line, err := json.Marshal(asked)
	if err != nil {
		return lanesAnswer{}, err
	}

	if _, err := connection.Write(append(line, '\n')); err != nil {
		return lanesAnswer{}, err
	}

	connection.SetDeadline(time.Now().Add(5 * time.Second))

	read, err := bufio.NewReader(connection).ReadString('\n')
	if err != nil && read == "" {
		return lanesAnswer{}, err
	}

	var answer map[string]any
	if json.Unmarshal([]byte(strings.TrimSpace(read)), &answer) != nil {
		return lanesAnswer{tell: strings.TrimSpace(read)}, nil
	}

	return lanesAnswer{tell: asText(answer["tell"]), label: asText(answer["label"])}, nil
}

func lanesLabel() string {
	held, running := kept.Trees(), readsRunning()

	said := "judge lanes: " + strconv.Itoa(len(held)) + " tree(s) warm"
	for _, read := range running {
		said += ", 1 warming " + strconv.FormatInt(read.percent(), 10) + "%"
	}

	return said + ", " + strconv.Itoa(kept.Contents().Count()) + " file(s) known by contents"
}

func treeDropped(checkout string) string {
	named, ok := rootOf(checkout)
	if !ok {
		return checkout + " is not on a branch of a project, so it has no tree to let go."
	}

	kept.Drop(named.project, named.branch)
	lastRead.Delete(named.key())

	if err := os.RemoveAll(savedTreeFolder(named)); err != nil {
		return "Let go of the tree of " + named.project + ", but its saved copy stays: " + err.Error()
	}

	return "Let go of the tree of " + named.project + " on " + named.branch + " and its saved copy. The next question about it reads the whole tree again."
}

func lanesAsked(line map[string]any) (string, bool) {
	if asText(line["event"]) != lanesEvent {
		return "", false
	}

	return asText(line["drop"]), true
}

func lanesHeld(drop string) string {
	if checkout, tree := strings.CutPrefix(drop, droppingTheTree); tree {
		return treeDropped(checkout)
	}

	said := strconv.Itoa(kept.Contents().Count()) + " file(s) known by their contents, " + strconv.Itoa(kept.NodesHeld()) + " nodes held in all" + ceilingSaid()

	return treesSaid() + said + healthSaid() + mergesSaid()
}

func treesSaid() string {
	held, running := kept.Trees(), readsRunning()

	sort.Slice(held, func(one, two int) bool { return held[one].Branch < held[two].Branch })
	sort.Slice(running, func(one, two int) bool { return running[one].branch < running[two].branch })

	said := []string{strconv.Itoa(len(held)) + " tree(s) held, " + strconv.Itoa(len(running)) + " being read"}

	for _, tree := range held {
		said = append(said, "  "+tree.Branch+" | warm | "+strconv.Itoa(tree.Files)+" file(s), "+strconv.Itoa(tree.Nodes)+
			" nodes | held since "+tree.Read.Format("15:04:05"))
	}

	for _, read := range running {
		said = append(said, "  "+read.branch+" | warming "+strconv.FormatInt(read.percent(), 10)+"% | "+
			strconv.FormatInt(read.done.Load(), 10)+" of "+strconv.FormatInt(read.total.Load(), 10)+" file(s) read | since "+
			read.began.Format("15:04:05"))
	}

	return strings.Join(said, "\n") + "\n\n"
}

var cannotBeAsked atomic.Pointer[string]

func healthSaid() string {
	said := cannotBeAsked.Load()
	if said == nil {
		return ""
	}

	return "\n" + *said
}

func mergesSaid() string {
	return mergesSaidIn(workspace.ProjectRoot(cwdOf()))
}

func mergesSaidIn(root string) string {
	found := MergesIn(workspace.At(root, "")).Found()
	if len(found) == 0 {
		return ""
	}

	said := []string{"", "What the last " + strconv.Itoa(len(found)) + " merge(s) and hand-in(s) brought in, kept because a delivery with no agent has nobody to tell:"}
	for _, each := range found {
		branch, rest, _ := strings.Cut(each, "\t")
		_, words, _ := strings.Cut(rest, "\t")
		said = append(said, "  "+branch+": "+strings.ReplaceAll(words, "\n", "\n    "))
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

func NodesHeld() int {
	return kept.NodesHeld()
}

func Holding(project string) (int, bool) {
	base, holds := kept.Any(project)
	if !holds {
		return 0, false
	}

	return base.Files(), true
}

func LastRead(root string) string {
	named, ok := rootOf(root)
	if !ok {
		return "this folder is no checkout we know"
	}

	said, tried := howTheReadWent(named)
	if !tried {
		return "nothing has read it"
	}

	return said
}

func ReadingNow(project string) bool {
	named, ok := rootOf(project)
	if !ok {
		return false
	}

	_, running := reading.Load(named.key())

	return running
}

var letGo sync.Map

func WhyATreeWasLetGo(project string) string {
	why, said := letGo.Load(project)
	if !said {
		return ""
	}

	return string(why.(Narrowed))
}
