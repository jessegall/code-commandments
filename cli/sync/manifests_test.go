package sync

import (
	"encoding/json"
	"os"
	"path/filepath"
	"regexp"
	"slices"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/health"
	"github.com/jessegall/code-commandments/cli/hooks"
)

// pins is the little of a plugin manifest this gate reads: the version it declares and the release its first setup step fetches.
type pins struct {
	Version string                 `json:"version"`
	Env     map[string]string      `json:"env"`
	Setup   []struct{ Run string } `json:"setup"`
}

// offered is what version 4's manifest declares about the events it answers: version 3's file writes its own services as an object, so only this one is read for them.
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
type service struct {
	Name    string   `json:"name"`
	Answers bool     `json:"answers"`
	Command []string `json:"command"`
	Run     string   `json:"run"`
	When    string   `json:"when"`
	Restart string   `json:"restart"`
}

func (s service) line() string {
	if s.Run != "" {
		return s.Run
	}

	return strings.Join(s.Command, " ")
}

// kept is what each manifest says the journal keeps running, by name.
type kept map[string]service

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

const windowMs = 10

const decidedMs = 180_000

var decided = []string{"worker_delivering", "worker_merging"}

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

	for _, held := range manifest.Holds {
		if held.Event == "file_edited" {
			t.Error("file_edited is held: an edit would wait on the judge of it, which is the fault all of this answers")
		}
	}
}

// declaredHealth is what a manifest says about the question the journal asks each service about itself: the variable it names the socket in, the line it writes, and the answer it reads back.
type declaredHealth struct {
	SocketEnv string `json:"socket_env"`
	Request   string `json:"request"`
	Answer    string `json:"answer"`
}

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

	anything := regexp.MustCompile(`<[^>]*>`)

	for _, service := range []string{"hooks", "roslyn", "mypy", "frontend", "php"} {
		if said, declared := health.Answer(service), anything.ReplaceAllString(offered.Answer, service); said != declared {
			t.Errorf("the %s service answers %s and the manifest declares %s", service, said, declared)
		}
	}

	declared := map[string]any{}
	if err := json.Unmarshal([]byte(offered.Answer), &declared); err != nil {
		t.Fatalf("the answer the manifest declares is no JSON: %v", err)
	}
	if declared["health"] != "up" {
		t.Errorf("the manifest says a service answers %v, and up is what it says", declared["health"])
	}
}

func TestBothManifestsFitTheSameLanguages(t *testing.T) {
	var fits []string
	for _, name := range [][]string{{".journal-plugin", "plugin.json"}, {"journal-plugin.json"}} {
		text, err := os.ReadFile(filepath.Join(append([]string{"..", ".."}, name...)...))
		if err != nil {
			t.Fatal(err)
		}

		var read struct {
			Fits struct {
				Languages []string `json:"languages"`
			} `json:"fits"`
		}
		if err := json.Unmarshal(text, &read); err != nil {
			t.Fatalf("%s: %v", filepath.Join(name...), err)
		}

		if len(read.Fits.Languages) == 0 {
			t.Errorf("%s names no languages it fits, so it is suggested to every project", filepath.Join(name...))

			continue
		}

		listed := slices.Clone(read.Fits.Languages)
		slices.Sort(listed)
		fits = append(fits, strings.Join(listed, ","))
	}

	if len(fits) == 2 && fits[0] != fits[1] {
		t.Errorf("the manifests fit different languages: %s against %s", fits[0], fits[1])
	}
}

func TestTheManifestDeclaresTheFolderItReads(t *testing.T) {
	text, err := os.ReadFile(filepath.Join("..", "..", "journal-plugin.json"))
	if err != nil {
		t.Fatal(err)
	}

	var read struct {
		Reads []string `json:"project_reads"`
	}
	if err := json.Unmarshal(text, &read); err != nil {
		t.Fatal(err)
	}

	if !slices.Contains(read.Reads, ".commandments") {
		t.Errorf("the manifest declares %v as the project folders it reads, and the config it judges by is in .commandments", read.Reads)
	}
}

func TestNothingOfOursReachesAWorker(t *testing.T) {
	text, err := os.ReadFile(filepath.Join("..", "..", "journal-plugin.json"))
	if err != nil {
		t.Fatal(err)
	}

	var read struct {
		Hears   []string            `json:"hears"`
		Reaches map[string][]string `json:"reaches"`
		Holds   []hold              `json:"holds"`
		Aliases []struct {
			Name    string `json:"name"`
			Workers bool   `json:"workers"`
		} `json:"aliases"`
	}
	if err := json.Unmarshal(text, &read); err != nil {
		t.Fatal(err)
	}

	for event, roles := range read.Reaches {
		t.Errorf("%s reaches %v: an event of ours reaches an agent that is not the main one", event, roles)
	}

	for _, alias := range read.Aliases {
		if alias.Workers {
			t.Errorf("the %s command is offered to workers", alias.Name)
		}
	}

	if slices.Contains(read.Hears, "worker_delivering") {
		for _, held := range read.Holds {
			if held.Event != "worker_delivering" {
				continue
			}

			if asked := time.Duration(held.WindowMs) * time.Millisecond; asked != hooks.DeclaredWindow {
				t.Errorf("the manifest asks the journal to hold a delivery for %s and the code judges in %s", asked, hooks.DeclaredWindow)
			}

			return
		}

		t.Error("the plugin hears worker_delivering and asks for no hold on it, so a delivery has no time to be judged in")
	}
}

func TestEachAliasSaysWhatKindOfCommandItIs(t *testing.T) {
	text, err := os.ReadFile(filepath.Join("..", "..", "journal-plugin.json"))
	if err != nil {
		t.Fatal(err)
	}

	var read struct {
		Aliases []struct {
			Name     string `json:"name"`
			Category string `json:"category"`
		} `json:"aliases"`
	}
	if err := json.Unmarshal(text, &read); err != nil {
		t.Fatal(err)
	}

	known := []string{"search", "read", "edit", "version control", "test run", "build", "check", "package", "network", "process", "journal", "unknown"}

	declared := map[string]string{}
	for _, alias := range read.Aliases {
		declared[alias.Name] = alias.Category

		if alias.Category != "" && !slices.Contains(known, alias.Category) {
			t.Errorf("the %s alias declares the category %q, which the journal does not know, and the install is refused for the whole plugin", alias.Name, alias.Category)
		}
	}

	for _, checking := range []string{"sins", "rules"} {
		if declared[checking] != "check" {
			t.Errorf("the %s alias declares %q, and it runs a check", checking, declared[checking])
		}
	}

	if declared["commandments"] != "" {
		t.Errorf("the commandments alias declares %q, and it is the passthrough that also repents and reports", declared["commandments"])
	}
}

func TestNoAliasOfOursIsOpenToAWorker(t *testing.T) {
	text, err := os.ReadFile(filepath.Join("..", "..", "journal-plugin.json"))
	if err != nil {
		t.Fatal(err)
	}

	var read struct {
		Aliases []struct {
			Name    string `json:"name"`
			Workers bool   `json:"workers"`
		} `json:"aliases"`
	}
	if err := json.Unmarshal(text, &read); err != nil {
		t.Fatal(err)
	}

	mayRun := map[string]bool{}
	for _, alias := range read.Aliases {
		mayRun[alias.Name] = alias.Workers
	}

	for name, open := range mayRun {
		if open {
			t.Errorf("the %s alias is open to workers, and a worker is judged by nobody here", name)
		}
	}

	brief, err := os.ReadFile(filepath.Join("..", "library", "brief.md"))
	if err != nil {
		t.Fatal(err)
	}

	if !strings.Contains(string(brief), "judged when you take it in") {
		t.Error("the briefing does not say that work another agent wrote is judged when it is taken in")
	}
}

func TestTheSettingsFileCarriesEverySettingTheCodeReads(t *testing.T) {
	text, err := os.ReadFile(filepath.Join("..", "..", ".journal-plugin", "settings.json"))
	if err != nil {
		t.Fatal(err)
	}

	var declared []struct {
		Key string `json:"key"`
	}
	if err := json.Unmarshal(text, &declared); err != nil {
		t.Fatal(err)
	}

	var keys []string
	for _, setting := range declared {
		keys = append(keys, setting.Key)
	}

	for _, named := range []string{"folders_judged", "folders_skipped", "judge_parallel", "judge_record", "judge_workers", "judge_nodes_held"} {
		if !slices.Contains(keys, named) {
			t.Errorf("the settings file declares no %s, so a person cannot switch it and the code reads a setting nobody can set", named)
		}
	}
}

func TestOnlyTheVersionFourManifestCanCarryTheWorktreeKeys(t *testing.T) {
	four, err := os.ReadFile(filepath.Join("..", "..", "journal-plugin.json"))
	if err != nil {
		t.Fatal(err)
	}

	var fourth struct {
		Journal string           `json:"journal"`
		Hears   []string         `json:"hears"`
		Listen  []map[string]any `json:"listen"`
	}
	if err := json.Unmarshal(four, &fourth); err != nil {
		t.Fatal(err)
	}

	older, err := os.ReadFile(filepath.Join("..", "..", ".journal-plugin", "plugin.json"))
	if err != nil {
		t.Fatal(err)
	}

	var third struct {
		Journal string           `json:"journal"`
		Listen  []map[string]any `json:"listen"`
	}
	if err := json.Unmarshal(older, &third); err != nil {
		t.Fatal(err)
	}

	if fourth.Journal == third.Journal {
		t.Errorf("both manifests declare journal %s, and they speak to different journals: the version four manifest and the older one", fourth.Journal)
	}

	if len(third.Listen) > 0 {
		t.Errorf("the older manifest carries %d listen entry(s), and only the version four manifest can carry them", len(third.Listen))
	}

	if !slices.Contains(fourth.Hears, "file_edited") {
		t.Error("the version four manifest no longer hears file_edited, and their journal keeps sending it with no date for its removal, so an edit would be heard by nobody")
	}

	for _, entry := range fourth.Listen {
		for _, form := range []string{"guard", "listen", "commit"} {
			named, held := entry[form].(string)
			if !held {
				continue
			}

			if !strings.HasPrefix(named, "service:") {
				t.Errorf("the listen entry for %v names %s as its %s form, and only service:<name> reaches our running service; a script would start a new process per event", entry["event"], named, form)
			}
		}
	}
}
