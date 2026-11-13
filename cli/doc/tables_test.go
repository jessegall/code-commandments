package doc_test

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"regexp"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/doc"
	"github.com/jessegall/code-commandments/cli/jsonfile"
	_ "github.com/jessegall/code-commandments/registry"
	_ "github.com/jessegall/code-commandments/scribes/backend"
)

func TestTheGeneratedDocumentsAreCurrent(t *testing.T) {
	root := filepath.Join("..", "..")
	documents, err := doc.Generated(root)
	if err != nil {
		t.Fatal(err)
	}
	for path, content := range documents {
		if current, _ := os.ReadFile(filepath.Join(root, path)); string(current) != content {
			t.Errorf("%s is stale: scripts/dev go run ./cli/doc/readme", path)
		}
	}
}

func TestThePreCommitHookNamesEveryGeneratedDocument(t *testing.T) {
	root := filepath.Join("..", "..")
	documents, err := doc.Generated(root)
	if err != nil {
		t.Fatal(err)
	}
	hook, err := os.ReadFile(filepath.Join(root, "scripts", "hooks", "pre-commit"))
	if err != nil {
		t.Fatal(err)
	}
	generators := regexp.MustCompile(`(?s)GENERATORS=\((.*?)\n\)`).FindSubmatch(hook)
	if generators == nil {
		t.Fatal("the pre-commit hook names no GENERATORS")
	}
	for path := range documents {
		if !strings.Contains(string(generators[1]), path) {
			t.Errorf("the pre-commit hook re-stages no %s, so a commit carries it stale", path)
		}
	}
}

func TestThePluginOffersTheJournalTheBriefingItWouldOtherwiseWrite(t *testing.T) {
	root := filepath.Join("..", "..")

	manifest, err := os.ReadFile(filepath.Join(root, "journal-plugin.5.json"))
	if err != nil {
		t.Fatal(err)
	}

	var declared struct {
		Injected []string `json:"injected_instructions"`
		Blocks   []struct {
			Key  string `json:"key"`
			File string `json:"file"`
		} `json:"instruction_blocks"`
	}

	if err := json.Unmarshal(manifest, &declared); err != nil {
		t.Fatal(err)
	}

	if len(declared.Injected) != 1 || declared.Injected[0] != doc.JournalBriefing {
		t.Errorf("the plugin injects %v, not the briefing at %s", declared.Injected, doc.JournalBriefing)
	}

	if len(declared.Blocks) != 1 || declared.Blocks[0].Key != "briefing" || declared.Blocks[0].File != doc.WorkerBriefing {
		t.Errorf("a worker's profile is offered %+v, not the briefing at %s", declared.Blocks, doc.WorkerBriefing)
	}

	forWorkers, err := os.ReadFile(filepath.Join(root, doc.WorkerBriefing))
	if err != nil {
		t.Fatal(err)
	}

	for _, named := range []string{"journal sins", "journal commandments", "and the rest of", "the commands", "`judge`"} {
		if strings.Contains(string(forWorkers), named) {
			t.Errorf("the briefing for a worker names %q, where it names no command:\n%s", named, forWorkers)
		}
	}

	briefing, err := os.ReadFile(filepath.Join(root, doc.JournalBriefing))
	if err != nil {
		t.Fatal(err)
	}

	if strings.Contains(string(briefing), "{{") || !strings.Contains(string(briefing), "journal sins") {
		t.Errorf("the briefing the journal carries is not rendered for a journal project:\n%s", briefing)
	}
}

func TestEveryPluginManifestNamesOneVersionAndFetchesOneRelease(t *testing.T) {
	root := filepath.Join("..", "..")

	versions, releases := map[string]bool{}, map[string]bool{}
	for _, name := range []string{doc.Plugin, doc.Journal4Plugin, doc.Journal5Plugin} {
		frozen := name == doc.Journal4Plugin
		text, err := os.ReadFile(filepath.Join(root, name))
		if err != nil {
			t.Fatal(err)
		}

		pinned := regexp.MustCompile(`COMMANDMENTS_RELEASE(?:=|": ")v([0-9]+\.[0-9]+\.[0-9]+)`).FindStringSubmatch(string(text))
		if pinned == nil {
			t.Fatalf("%s pins no release", name)
		}

		manifest, read := jsonfile.Read(filepath.Join(root, name))
		if !read {
			t.Fatalf("%s is not a JSON object", name)
		}

		version, _ := manifest.Get("version")
		if frozen {
			if "v"+pinned[1] != doc.FrozenForJournal4 {
				t.Errorf("%s fetches v%s, but journals before version 5 stay on %s for good", name, pinned[1], doc.FrozenForJournal4)
			}
			versions[fmt.Sprint(version)] = true

			continue
		}
		if newer(pinned[1], fmt.Sprint(version)) {
			t.Errorf("%s says version %v and fetches the later v%s, a binary no tag of this version can have built", name, version, pinned[1])
		}
		versions[fmt.Sprint(version)], releases[pinned[1]] = true, true
	}

	if len(versions) > 1 || len(releases) > 1 {
		t.Errorf("the plugin manifests say versions %v and fetch releases %v, so a journal reading one of them runs another plugin: run composer sins", versions, releases)
	}
}

func newer(a, b string) bool {
	var left, right [3]int
	fmt.Sscanf(a, "%d.%d.%d", &left[0], &left[1], &left[2])
	fmt.Sscanf(b, "%d.%d.%d", &right[0], &right[1], &right[2])

	for i := range left {
		if left[i] != right[i] {
			return left[i] > right[i]
		}
	}

	return false
}

func TestEverySettingSaysItsKindInBothWords(t *testing.T) {
	for _, setting := range doc.SettingsSchema() {
		entry := setting.(*jsonfile.Object)
		key, _ := entry.Get("key")
		kind, _ := entry.Get("kind")
		written, _ := entry.Get("type")

		if kind == nil || written == nil || fmt.Sprint(kind) != fmt.Sprint(written) {
			t.Errorf("the setting %v says kind %v and type %v, not one type under both words", key, kind, written)
		}
	}
}

var journal4Knows = map[string][]string{
	"manifest": {"name", "version", "title", "description", "journal", "protocol", "program", "hears", "holds", "reaches",
		"aliases", "cards", "requires", "setup", "installed", "settings", "skills", "health", "services", "dashboard", "ignored"},
	"card":    {"kind", "label", "icon", "tone", "collapsed"},
	"alias":   {"name", "line", "env", "variables", "args", "reader", "description", "background", "workers"},
	"setting": {"text", "number", "flag", "choice", "list", "secret"},
}

func TestVersion4IsOfferedEverySettingVersion5Is(t *testing.T) {
	var named []string
	for _, manifest := range []string{doc.Journal4Plugin, "journal-plugin.5.json"} {
		read, readable := jsonfile.Read(filepath.Join("..", "..", manifest))
		if !readable {
			t.Fatalf("%s is not a JSON object", manifest)
		}

		file, _ := read.Get("settings")
		named = append(named, fmt.Sprint(file))
	}

	if named[0] != doc.PluginSchema || named[1] != doc.PluginSchema {
		t.Errorf("version 4 reads its settings from %s and version 5 from %s, not both from %s", named[0], named[1], doc.PluginSchema)
	}

	for _, setting := range doc.SettingsSchema() {
		entry := setting.(*jsonfile.Object)
		key, _ := entry.Get("key")
		kind, _ := entry.Get("kind")

		if !slices.Contains(journal4Knows["setting"], fmt.Sprint(kind)) {
			t.Errorf("the setting %v is of the kind %v, which version 4 of the journal does not know", key, kind)
		}
	}
}

func TestTheVersion4ManifestHoldsOnlyWhatVersion4Reads(t *testing.T) {
	manifest, read := jsonfile.Read(filepath.Join("..", "..", doc.Journal4Plugin))
	if !read {
		t.Fatalf("%s is not a JSON object", doc.Journal4Plugin)
	}

	unknownIn(t, "the manifest", manifest, journal4Knows["manifest"])

	for _, group := range []struct{ field, part string }{{"cards", "card"}, {"aliases", "alias"}} {
		listed, _ := manifest.Get(group.field)
		entries, _ := listed.([]any)
		if len(entries) == 0 {
			t.Errorf("%s declares no %s, so nothing of them is asked", doc.Journal4Plugin, group.field)
		}

		for _, each := range entries {
			entry, _ := each.(*jsonfile.Object)
			unknownIn(t, "a "+group.part, entry, journal4Knows[group.part])
		}
	}
}

func TestTheVersion4ManifestHearsEveryEventItHoldsOrReaches(t *testing.T) {
	manifest, read := jsonfile.Read(filepath.Join("..", "..", doc.Journal4Plugin))
	if !read {
		t.Fatalf("%s is not a JSON object", doc.Journal4Plugin)
	}

	listed, _ := manifest.Get("hears")
	var heard []string
	for _, event := range listed.([]any) {
		heard = append(heard, fmt.Sprint(event))
	}

	held, _ := manifest.Get("holds")
	for _, each := range held.([]any) {
		event, _ := each.(*jsonfile.Object).Get("event")
		if !slices.Contains(heard, fmt.Sprint(event)) {
			t.Errorf("%s holds %v but does not hear it, so version 4 of the journal refuses to install it", doc.Journal4Plugin, event)
		}
	}

	reached, _ := manifest.Get("reaches")
	for _, event := range reached.(*jsonfile.Object).Keys() {
		if !slices.Contains(heard, event) {
			t.Errorf("%s says who %s reaches but does not hear it, so version 4 of the journal refuses to install it", doc.Journal4Plugin, event)
		}
	}
}

func unknownIn(t *testing.T, what string, entry *jsonfile.Object, known []string) {
	t.Helper()

	for _, key := range entry.Keys() {
		if !slices.Contains(known, key) {
			t.Errorf("%s of %s holds %q, which version 4 of the journal refuses: it installs nothing then", what, doc.Journal4Plugin, key)
		}
	}
}
