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

	"github.com/jessegall/code-commandments/cli/binary"
	"github.com/jessegall/code-commandments/cli/hooks"
)

// pins is the little of a plugin manifest this gate reads: the version it declares and the release its first setup step fetches.
type pins struct {
	Version string                 `json:"version"`
	Env     map[string]string      `json:"env"`
	Setup   []struct{ Run string } `json:"setup"`
}

// offered is what version 4's manifest declares about the events it answers.
type offered struct {
	Hears []string `json:"hears"`
	Holds []hold   `json:"holds"`
}

// hold is one event the journal asks the plugin before the tool runs, and the window it allows for the answer.
type hold struct {
	Event    string `json:"event"`
	WindowMs int    `json:"window_ms"`
}

func TestNoManifestKeepsAServiceRunning(t *testing.T) {
	for _, name := range []string{"journal-plugin.json", "journal-plugin.5.json", filepath.Join(".journal-plugin", "plugin.json")} {
		content, err := os.ReadFile(filepath.Join("..", "..", name))
		if err != nil {
			t.Fatal(err)
		}

		var manifest map[string]json.RawMessage
		if err := json.Unmarshal(content, &manifest); err != nil {
			t.Fatal(err)
		}

		for _, key := range []string{"services", "health", "refuse_socket"} {
			if _, declared := manifest[key]; declared {
				t.Errorf("%s declares %s: nothing of ours runs between events, each event and each judge starts what it needs and stops it", name, key)
			}
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

const startedWithinMs = 500

const quickMs = 1_000

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
		longest := quickMs
		if slices.Contains(decided, held.Event) {
			longest = decidedMs
		}

		if held.WindowMs < startedWithinMs || held.WindowMs > longest {
			t.Errorf("%s is held for %dms: a hold of ours is answered by a process started for it, so its window is %dms to %dms", held.Event, held.WindowMs, startedWithinMs, longest)
		}
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

func TestBothManifestsFitTheSameLanguages(t *testing.T) {
	var fits []string
	for _, name := range [][]string{{".journal-plugin", "plugin.json"}, {"journal-plugin.5.json"}} {
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
	text, err := os.ReadFile(filepath.Join("..", "..", "journal-plugin.5.json"))
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

func TestNothingReachesAnAgentOtherThanTheMainOneOrAWorker(t *testing.T) {
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
		if !slices.Contains(read.Hears, event) {
			t.Errorf("%s reaches %v but is not heard, which a journal refuses by name", event, roles)
		}

		for _, role := range roles {
			if role != "workers" {
				t.Errorf("%s reaches %s: only a worker's own work is judged for it, and only when the setting "+
					"judge_workers is on", event, role)
			}
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
	text, err := os.ReadFile(filepath.Join("..", "..", "journal-plugin.5.json"))
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

func TestOnlyTheAliasesAWorkerIsToldToRunAreOpenToWorkers(t *testing.T) {
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

	told := map[string]bool{
		strings.TrimPrefix(binary.ChangesAlias, "journal "): true,
		strings.TrimPrefix(binary.Alias, "journal "):        true,
	}

	for name, open := range mayRun {
		if open && !told[name] {
			t.Errorf("the %s alias is open to workers, and only the ones our lines and the briefing tell a worker "+
				"to run may be", name)
		}
	}

	for name := range told {
		if !mayRun[name] {
			t.Errorf("the %s alias is closed to workers, and our lines or the briefing tell a worker to run it, so "+
				"the journal refuses the very command we named", name)
		}
	}

	brief, err := os.ReadFile(filepath.Join("..", "library", "brief.md"))
	if err != nil {
		t.Fatal(err)
	}

	if !strings.Contains(string(brief), "judged as it comes in") {
		t.Error("the briefing does not say that work another agent wrote is judged as it comes in")
	}

	if !strings.Contains(string(brief), "merge is judged the\nmoment it lands") {
		t.Error("the briefing does not say that the merge itself is judged, so an agent would still think it must remember to")
	}

	if !strings.Contains(string(brief), "findings go to the worker that wrote them") {
		t.Error("the briefing does not say the worker is told, so an agent expecting a merge report and getting none " +
			"would conclude nothing was judged")
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

}

func TestEveryEventTheJournalSendsRunsThePluginsOwnProgram(t *testing.T) {
	text, err := os.ReadFile(filepath.Join("..", "..", "journal-plugin.5.json"))
	if err != nil {
		t.Fatal(err)
	}

	var fifth struct {
		Program string           `json:"program"`
		Listen  []map[string]any `json:"listen"`
	}
	if err := json.Unmarshal(text, &fifth); err != nil {
		t.Fatal(err)
	}

	if len(fifth.Listen) == 0 {
		t.Fatal("the version 5 manifest listens to nothing, so no merge or hand-in reaches the plugin")
	}

	for _, entry := range fifth.Listen {
		for _, form := range []string{"guard", "listen", "commit"} {
			named, held := entry[form].(string)
			if held && named != fifth.Program {
				t.Errorf("the listen entry for %v names %s as its %s form, and only the plugin's program %s answers an event, started for it", entry["event"], named, form, fifth.Program)
			}
		}
	}
}

func TestNoFileTheJournalRunsIsFetchedPerRelease(t *testing.T) {
	text, err := os.ReadFile(filepath.Join("..", "..", "journal-plugin.json"))
	if err != nil {
		t.Fatal(err)
	}

	var read struct {
		Program string `json:"program"`
	}
	if err := json.Unmarshal(text, &read); err != nil {
		t.Fatal(err)
	}

	fetched := "bin/commandments-release"

	if read.Program == fetched {
		t.Errorf("the program the journal runs for every event IS the fetched binary, which every release replaces: "+
			"the journal digests the files a plugin runs, so each release would move that digest (%s)", read.Program)
	}

}

func TestEveryAliasOurLinesNameIsDeclaredInEachManifest(t *testing.T) {
	for _, manifest := range []string{"journal-plugin.json", "journal-plugin.5.json"} {
		text, err := os.ReadFile(filepath.Join("..", "..", manifest))
		if err != nil {
			t.Fatal(err)
		}

		var read struct {
			Aliases []struct {
				Name string `json:"name"`
			} `json:"aliases"`
		}
		if err := json.Unmarshal(text, &read); err != nil {
			t.Fatal(err)
		}

		declared := []string{}
		for _, alias := range read.Aliases {
			declared = append(declared, "journal "+alias.Name)
		}

		for _, named := range []string{binary.Alias, binary.ChangesAlias} {
			if !slices.Contains(declared, named) {
				t.Errorf("%s does not declare %q, which our lines tell an agent to run, so the agent is sent to a "+
					"command the journal does not know", manifest, named)
			}
		}
	}
}
