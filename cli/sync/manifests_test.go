package sync

import (
	"encoding/json"
	"os"
	"path/filepath"
	"regexp"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/health"
)

// The plugin is declared twice, once for each journal protocol, and what the two say has to agree: the release
// they pin, the services the journal keeps running, and the windows the holds declare. All three are read from
// the files themselves, so they are proved here, beside the command that publishes the plugin, rather than in
// the package that builds the whole binary before it can read a line of JSON.

// pins is the little of a plugin manifest this gate reads: the version it declares and the release its first setup
// step fetches. The two manifests answer the same questions in different shapes, so neither's whole type fits both.
type pins struct {
	Version string                 `json:"version"`
	Env     map[string]string      `json:"env"`
	Setup   []struct{ Run string } `json:"setup"`
}

// offered is what version 4's manifest declares about the events it answers: version 3's file writes its own
// services as an object, so only this one is read for them.
type offered struct {
	Hears    []string  `json:"hears"`
	Holds    []hold    `json:"holds"`
	Services []service `json:"services"`
}

// hold is one event the journal asks the plugin before the tool runs, and the window it allows for the answer.
type hold struct {
	Event    string `json:"event"`
	WindowMs int    `json:"window_ms"`
}

// service is one program the journal keeps running for the plugin, and whether it answers the journal's events.
// Version 4 names it and writes its command as a list; version 3 writes the services as an object whose key is the
// name and whose run is the same command as one line, so both are read into this.
type service struct {
	Name    string   `json:"name"`
	Answers bool     `json:"answers"`
	Command []string `json:"command"`
	Run     string   `json:"run"`
	When    string   `json:"when"`
	Restart string   `json:"restart"`
}

// line is the command this service runs, however its manifest writes it.
func (s service) line() string {
	if s.Run != "" {
		return s.Run
	}

	return strings.Join(s.Command, " ")
}

// kept is what each manifest says the journal keeps running, by name.
type kept map[string]service

// keptBy is the services the manifest declares, read from either shape its protocol writes them in.
func keptBy(t *testing.T, name ...string) kept {
	t.Helper()
	content, err := os.ReadFile(filepath.Join(append([]string{"..", ".."}, name...)...))
	if err != nil {
		t.Fatal(err)
	}

	var listed struct {
		Services json.RawMessage `json:"services"`
	}
	if err := json.Unmarshal(content, &listed); err != nil {
		t.Fatal(err)
	}

	var asList []service
	if json.Unmarshal(listed.Services, &asList) == nil {
		held := kept{}
		for _, one := range asList {
			held[one.Name] = one
		}

		return held
	}

	var asObject map[string]service
	if err := json.Unmarshal(listed.Services, &asObject); err != nil {
		t.Fatal(err)
	}
	held := kept{}
	for name, one := range asObject {
		one.Name = name
		held[name] = one
	}

	return held
}

// TestBothPluginManifestsKeepTheSameServices holds the two manifests to one answer about what is kept running. The
// plugin is declared twice, once for each journal protocol, and which bridges stay warm must not depend on which
// file the journal reads: a service missing from one of them starts that language's bridge cold for every single
// run, which is the whole cost the warming exists to remove. The frontend and php services were added to version
// 4's file alone and version 3's went without them for seven releases, which is what this catches.
func TestBothPluginManifestsKeepTheSameServices(t *testing.T) {
	held, offered := keptBy(t, ".journal-plugin", "plugin.json"), keptBy(t, "journal-plugin.json")

	for name, one := range offered {
		same, declared := held[name]
		if !declared {
			t.Errorf("journal-plugin.json keeps the %s service and .journal-plugin/plugin.json keeps none", name)

			continue
		}
		if same.line() != one.line() {
			t.Errorf("the %s service runs %q in one manifest and %q in the other", name, same.line(), one.line())
		}
		if same.When != one.When {
			t.Errorf("the %s service is started when %q in one manifest and when %q in the other", name, same.When, one.When)
		}
		if same.Restart != one.Restart {
			t.Errorf("the %s service restarts %q in one manifest and %q in the other", name, same.Restart, one.Restart)
		}
	}

	for name := range held {
		if _, declared := offered[name]; !declared {
			t.Errorf(".journal-plugin/plugin.json keeps the %s service and journal-plugin.json keeps none", name)
		}
	}
}

// TestBothPluginManifestsPinTheSameRelease holds the two manifests to one answer. The plugin is declared twice, once
// for each journal protocol: .journal-plugin/plugin.json carries the release in its env, journal-plugin.json fetches
// it in its first setup step, and the release workflow refuses a tag either of them does not name. Bumping one and
// forgetting the other is what this catches, which otherwise shows up only as every release between them failing.
func TestBothPluginManifestsPinTheSameRelease(t *testing.T) {
	read := func(name ...string) pins {
		content, err := os.ReadFile(filepath.Join(append([]string{"..", ".."}, name...)...))
		if err != nil {
			t.Fatal(err)
		}
		var read pins
		if err := json.Unmarshal(content, &read); err != nil {
			t.Fatal(err)
		}

		return read
	}
	held, offered := read(".journal-plugin", "plugin.json"), read("journal-plugin.json")

	fetched := regexp.MustCompile(`^COMMANDMENTS_RELEASE=(v\d+\.\d+\.\d+) `).FindStringSubmatch(offered.Setup[0].Run)
	if fetched == nil {
		t.Fatalf("journal-plugin.json's first setup step pins no release: %q", offered.Setup[0].Run)
	}
	if pinned := held.Env["COMMANDMENTS_RELEASE"]; pinned != fetched[1] {
		t.Errorf(".journal-plugin/plugin.json pins %s and journal-plugin.json fetches %s", pinned, fetched[1])
	}
	if held.Version != offered.Version {
		t.Errorf(".journal-plugin/plugin.json is version %s and journal-plugin.json is %s", held.Version, offered.Version)
	}
}

// windowMs is the longest window a hold of ours may declare at a moment of the AGENT's own work. It is far too
// short to parse a file, let alone judge one, which is the point: what the journal waits for while the agent works
// can only be an answer already known.
const windowMs = 10

// decidedMs is the longest window a hold may declare at a moment that is not the agent's work but a command
// someone ran and waits on: `journal worker merge`, which takes a branch in, and a worker's own clock-out, where
// the worker waits to hear whether its work is clean. A gate at either cannot report later — answer late and the
// work has already landed, or already reached the agent that sent the worker — so it answers in the moment. Its
// ceiling is the journal's own, three minutes by default: workers.merge_hold_seconds for the merge and
// plugins.clock_out_window_seconds for the clock-out, each of which the journal cuts our declared window down to.
// An older journal cuts it to its 50 ms instead, so declaring the whole of it costs nothing there, and the judge
// holds itself to the window as a deadline either way.
const decidedMs = 180_000

// decided are the events a hold may take its time at: the ones that are not the agent's own work. Every other
// event is a moment of that work, and a window there is the fault the rule above exists to keep away.
var decided = []string{"worker_delivering", "worker_merging"}

// TestEveryHoldIsAnsweredFromWhatIsAlreadyKnown holds the manifest to the rule every hook of ours answers under: the
// plugin never makes the journal wait on work while the agent works. An event it HOLDS at a moment of that work is
// asked before the tool runs, so it may only declare a window no judge could fit in, and a window that short cannot
// start a process — which is why a service of ours has to answer it. A hold with no window took the journal's old
// ten-second deadline, and one tool call that changed sixty files spent it on a scan and timed out; that is the
// fault these checks keep away. The one event held longer is a worker's merge, which is not the agent working but a
// command someone ran and waits on, and where an answer that comes later cannot stop anything.
func TestEveryHoldIsAnsweredFromWhatIsAlreadyKnown(t *testing.T) {
	content, err := os.ReadFile(filepath.Join("..", "..", "journal-plugin.json"))
	if err != nil {
		t.Fatal(err)
	}
	var manifest offered
	if err := json.Unmarshal(content, &manifest); err != nil {
		t.Fatal(err)
	}

	for _, held := range manifest.Holds {
		longest := windowMs
		if slices.Contains(decided, held.Event) {
			longest = decidedMs
		}

		if held.WindowMs <= 0 || held.WindowMs > longest {
			t.Errorf("%s is held for %dms: a hold of ours declares a window of at most %dms, so nothing can be worked out inside it", held.Event, held.WindowMs, longest)
		}
	}

	if len(manifest.Holds) > 0 && !slices.ContainsFunc(manifest.Services, func(kept service) bool { return kept.Answers }) {
		t.Error("the plugin holds an event and no service of its own answers: a window of milliseconds cannot start a process")
	}

	for _, event := range []string{"command_starting", "file_edited"} {
		if !slices.Contains(manifest.Hears, event) {
			t.Errorf("the plugin no longer hears %s, so it cannot judge what a tool changed", event)
		}
	}

	// A held file_edited blocks the tool's RESULT until the file is put right, which is a judge on the answer path
	// wearing another name: every edit would wait on a scan again.
	for _, held := range manifest.Holds {
		if held.Event == "file_edited" {
			t.Error("file_edited is held: an edit would wait on the judge of it, which is the fault all of this answers")
		}
	}
}

// declaredHealth is what a manifest says about the question the journal asks each service about itself: the variable
// it names the socket in, the line it writes, and the answer it reads back.
type declaredHealth struct {
	SocketEnv string `json:"socket_env"`
	Request   string `json:"request"`
	Answer    string `json:"answer"`
}

// healthOf is the health a manifest declares.
func healthOf(t *testing.T, name ...string) declaredHealth {
	t.Helper()
	content, err := os.ReadFile(filepath.Join(append([]string{"..", ".."}, name...)...))
	if err != nil {
		t.Fatal(err)
	}

	var manifest struct {
		Health declaredHealth `json:"health"`
	}
	if err := json.Unmarshal(content, &manifest); err != nil {
		t.Fatal(err)
	}

	return manifest.Health
}

// TestBothManifestsDeclareTheHealthTheServicesReallyAnswer holds the manifest to the code. The journal reads this
// block to learn how to ask a service how it is: it makes a socket, names it in that variable for every service it
// starts, writes that line and reads the answer — and allows 200ms, after which it restarts the service, and marks
// the plugin broken after five silences. So a variable named here that the service does not listen on, or a request
// it does not answer, is a plugin that reads as broken while every service is up.
func TestBothManifestsDeclareTheHealthTheServicesReallyAnswer(t *testing.T) {
	held, offered := healthOf(t, ".journal-plugin", "plugin.json"), healthOf(t, "journal-plugin.json")

	if held != offered {
		t.Errorf(".journal-plugin/plugin.json declares the health %+v and journal-plugin.json %+v", held, offered)
	}

	if offered.SocketEnv != health.Socket {
		t.Errorf("the manifest names the socket in $%s and the service listens on $%s", offered.SocketEnv, health.Socket)
	}
	if offered.Request != health.Request {
		t.Errorf("the manifest writes %q and the service answers %q", offered.Request, health.Request)
	}

	declared := map[string]any{}
	if err := json.Unmarshal([]byte(offered.Answer), &declared); err != nil {
		t.Fatalf("the answer the manifest declares is no JSON: %v", err)
	}
	answered := map[string]any{}
	if err := json.Unmarshal([]byte(health.Answer("hooks")), &answered); err != nil {
		t.Fatal(err)
	}

	if declared["health"] != "up" {
		t.Errorf("the manifest says a service answers %v, and up is what it says", declared["health"])
	}
	for key := range answered {
		if _, says := declared[key]; !says {
			t.Errorf("the service answers with %q and the manifest declares no such key: %v", key, declared)
		}
	}
	for key := range declared {
		if _, answers := answered[key]; !answers {
			t.Errorf("the manifest declares %q and no answer carries it: %v", key, answered)
		}
	}
}
