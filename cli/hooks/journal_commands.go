package hooks

import (
	"encoding/json"
	"fmt"
	"github.com/jessegall/code-commandments/cli/jsonfile"
	"os"
	"path/filepath"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/library"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/sins"
)

const (
	// journalSettings holds the switches the journal's user chose for the plugin, as JSON.
	journalSettings = "JOURNAL_SETTINGS"
	// journalProject names the project the plugin works for.
	journalProject = "CLAUDE_PROJECT_DIR"
	// journalPluginDir names the plugin's own folder, set by the journal for every command it runs for the plugin.
	journalPluginDir = "JOURNAL_PLUGIN_DIR"
	// judgedKey is the setting that lists the folders judged.
	judgedKey = "folders_judged"
	// skippedKey is the setting that lists the folders left out.
	skippedKey = "folders_skipped"
	// parallelKey is the setting that says how many detectors judge runs at once.
	parallelKey = "judge_parallel"
	// skillsFolder is where the plugin renders the skills for the journal to publish.
	skillsFolder = "journal-skills"
)

// languageKey is the setting that turns a language on or off.
func languageKey(language source.Language) string {
	return "language_" + string(language)
}

// sinKey is the setting that turns a sin on or off.
func sinKey(sin sins.Sin) string {
	return "sin_" + sin.Definition().Name
}

// JournalScan is `journal-scan`: the folders to check and to leave out, and the languages written, as the
// plugin's settings.
type JournalScan struct{}

// Names are the verbs it answers to.
func (JournalScan) Names() []string {
	return []string{"journal-scan"}
}

// Help documents it.
func (JournalScan) Help() help.Help {
	return help.Of("Scan the project for the folders to check and the ones to leave out, for the agent journal plugin.").
		Form("journal-scan", "answer the detected folders as settings (run by the journal plugin)")
}

// Run answers the settings: the folders the project's config already judges and leaves out, detected only where it
// names none, so applying them back, as an upgrade does, never drops a folder the project chose.
func (JournalScan) Run(in *cli.Input, console cli.Console) (int, error) {
	project := journalProjectRoot()
	judged, skipped := config.DetectRoots(project), config.BuiltFolders(project)
	if own, err := config.Load(project); err == nil {
		if len(own.Paths) > 0 {
			judged = own.Paths
		}
		if len(own.Excluded) > 0 {
			skipped = own.Excluded
		}
	}
	settings := jsonfile.NewObject(
		judgedKey, strings.Join(judged, "\n"),
		skippedKey, strings.Join(skipped, "\n"),
	)

	for _, language := range source.Languages {
		settings.Set(languageKey(language), strconv.FormatBool(config.WritesLanguage(project, language)))
	}

	text, err := jsonfile.Compact(jsonfile.NewObject("settings", settings), false)
	if err != nil {
		return 0, err
	}

	console.Say(text)

	return 0, nil
}

// JournalSkills is `journal-skills`: the skills rendered into the plugin's folder for the journal to publish.
type JournalSkills struct{}

// Names are the verbs it answers to.
func (JournalSkills) Names() []string {
	return []string{"journal-skills"}
}

// Help documents it.
func (JournalSkills) Help() help.Help {
	return help.Of("Render the skills into the agent journal plugin's folder, for the journal to publish.").
		Form("journal-skills", "render the skills into ./"+skillsFolder+"/.agents/skills (run by the journal plugin)")
}

// Run renders the skills and says how many.
func (JournalSkills) Run(in *cli.Input, console cli.Console) (int, error) {
	console.Say("{}")

	rendered, err := renderSkills()
	if err != nil {
		return 0, err
	}

	console.Warn(strconv.Itoa(rendered) + " skills rendered for the journal to publish")

	return 0, nil
}

// renderSkills publishes the skills the project can use into the plugin's own folder.
func renderSkills() (int, error) {
	project, err := config.Load(journalProjectRoot())
	if err != nil {
		return 0, err
	}

	published, err := library.At(filepath.Join(pluginRoot(), skillsFolder), project).Publish()

	return len(published), err
}

// pluginRoot is the plugin's folder: the nearest folder above the executable that holds a plugin manifest, as the
// binary the plugin fetches into its own bin/ does, else the one the journal names, else the folder above the
// executable's own. The executable comes first because the journal runs a plugin's setup from a staging copy while
// JOURNAL_PLUGIN_DIR already names its final folder, which it clears before moving the copy in.
func pluginRoot() string {
	self, err := os.Executable()
	if err != nil {
		return "."
	}

	self, _ = filepath.EvalSymlinks(self)

	for dir := filepath.Dir(self); dir != filepath.Dir(dir); dir = filepath.Dir(dir) {
		if _, err := os.Stat(filepath.Join(dir, ".journal-plugin", "plugin.json")); err == nil {
			return dir
		}
	}

	if dir := os.Getenv(journalPluginDir); dir != "" {
		return dir
	}

	return filepath.Dir(filepath.Dir(self))
}

// JournalConfig is `journal-config`: the plugin's chosen switches written into the project's config.
type JournalConfig struct{}

// Names are the verbs it answers to.
func (JournalConfig) Names() []string {
	return []string{"journal-config"}
}

// Help documents it.
func (JournalConfig) Help() help.Help {
	return help.Of("Write the agent journal plugin's chosen switches into .commandments/config.json.").
		Form("journal-config", "apply JOURNAL_SETTINGS to the project config (run by the journal plugin)")
}

// Run writes each chosen switch, renders the skills again, and notifies how many switches changed.
func (JournalConfig) Run(in *cli.Input, console cli.Console) (int, error) {
	var chosen map[string]any
	if json.Unmarshal([]byte(os.Getenv(journalSettings)), &chosen) != nil || chosen == nil {
		console.Warn(journalSettings + " holds no settings to apply.")

		return 2, nil
	}

	root := journalProjectRoot()

	if _, err := config.Migrate(root); err != nil {
		return 0, err
	}

	editor, switches := config.EditorIn(root).(config.Switches)
	if !switches {
		console.Warn("⚠ .commandments/config.php could not be migrated to config.json — the switches were left unapplied.")

		return 1, nil
	}

	changed := 0

	for _, language := range source.Languages {
		on, set := chosen[languageKey(language)]
		if !set || on == nil {
			continue
		}

		toggle := editor.EnableLanguage
		if on == "false" {
			toggle = editor.DisableLanguage
		}

		if did, err := toggle(language); err != nil {
			return 0, err
		} else if did {
			changed++
		}
	}

	for _, sin := range everySin() {
		on, set := chosen[sinKey(sin)]
		if !set || on == nil {
			continue
		}

		toggle := editor.Enable
		if on == "false" {
			toggle = editor.Disable
		}

		if did, err := toggle(config.ClassOf(config.Sin, sin)); err != nil {
			return 0, err
		} else if did {
			changed++
		}
	}

	if judged := folders(chosen, judgedKey); len(judged) > 0 {
		if err := editor.JudgeFolders(judged); err != nil {
			return 0, err
		}
	}

	if _, set := chosen[skippedKey]; set {
		if err := editor.SkipFolders(folders(chosen, skippedKey)); err != nil {
			return 0, err
		}
	}

	if parallel, err := strconv.Atoi(strings.TrimSpace(fmt.Sprint(chosen[parallelKey]))); err == nil && parallel >= 1 {
		if did, err := editor.RunWith(parallel); err != nil {
			return 0, err
		} else if did {
			changed++
		}
	}

	if _, err := renderSkills(); err != nil {
		return 0, err
	}

	if changed == 0 {
		console.Say("{}")

		return 0, nil
	}

	text, _ := jsonfile.Compact(jsonfile.NewObject("notify", editor.Name()[len(".commandments/"):]+" follows the plugin's switches: "+strconv.Itoa(changed)+" changed"), true)
	console.Say(text)

	return 0, nil
}

// everySin is every sin the tool ships, those still calibrating included, engine by engine.
func everySin() []sins.Sin {
	var every []sins.Sin

	for _, engine := range catalog.Engines {
		every = append(every, sins.Every(engine)...)
	}

	return every
}

func folders(chosen map[string]any, key string) []string {
	var found []string

	for _, line := range strings.Split(asText(chosen[key]), "\n") {
		if line = strings.TrimSpace(line); line != "" {
			found = append(found, line)
		}
	}

	return found
}

func journalProjectRoot() string {
	if project := os.Getenv(journalProject); project != "" {
		return project
	}

	cwd, _ := os.Getwd()

	return cwd
}
