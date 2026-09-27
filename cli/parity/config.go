package parity

import (
	"os"
	"path/filepath"
	"reflect"
	"strings"

	"github.com/jessegall/code-commandments/cli/config"
)

// The project config is the one file the Go binary writes in a different format by design: the PHP tool
// executes a config.php, the binary reads a config.json. The two are one file when they say the same.
const (
	phpConfig  = ".commandments/config.php"
	jsonConfig = ".commandments/config.json"
	schemaFile = ".commandments/config.schema.json"
)

// EquateConfigs makes a golden's config.php and the Go run's config.json one entry when both runs wrote
// them the same way and they declare the same settings, so a case differs only where the settings do. Both
// sides' words name the one file the same way, whether the PHP tool or the Go binary recorded the golden, and a layer declaration it shows in config.json's form
// is the golden's config.php one when both declare the same layers. The schema written beside a config.json
// and the ignore rule that keeps it tracked are the rest of its form.
func EquateConfigs(want, got Result, scratch string) (Result, Result) {
	for _, name := range []string{jsonConfig, "config.json already declares", "config.json follows the plugin"} {
		for _, text := range []*string{&want.Stdout, &want.Stderr, &got.Stdout, &got.Stderr} {
			*text = strings.ReplaceAll(*text, name, strings.Replace(name, "json", "php", 1))
		}
	}

	want.Stdout, got.Stdout = equateDeclarations(want.Stdout, got.Stdout, scratch)

	php, wrotePHP := entry(want.Files, phpConfig)
	json, wroteJSON := entry(got.Files, jsonConfig)

	if !wrotePHP || !wroteJSON || php.verb != json.verb || !sameSettings(php.contents, json.contents, scratch) {
		return want, got
	}

	same := "=== " + php.verb + " .commandments/config (the same settings)\n"
	want.Files = strings.Replace(want.Files, php.text, same, 1)
	got.Files = strings.Replace(got.Files, json.text, same, 1)

	if schema, wrote := entry(got.Files, schemaFile); wrote {
		got.Files = strings.Replace(got.Files, schema.text, "", 1)
	}

	got.Files = strings.Replace(got.Files, "!config.php\n!config.json\n", "!config.php\n", 1)

	return want, got
}

// fileEntry is one file's entry in a result's files: how it was written, its contents, and its whole text.
type fileEntry struct {
	verb, contents, text string
}

func entry(files, path string) (fileEntry, bool) {
	for _, verb := range []string{"created", "changed"} {
		header := "=== " + verb + " " + path + "\n"

		start := strings.Index(files, header)
		if start < 0 {
			continue
		}

		rest := files[start+len(header):]
		end := strings.Index(rest, "\n=== ")

		if end < 0 {
			end = len(rest)
		} else {
			end++
		}

		return fileEntry{verb, strings.TrimSuffix(rest[:end], "\n"), header + rest[:end]}, true
	}

	return fileEntry{}, false
}

// sameSettings says whether the config.php and the config.json declare the same config, arguments taken in
// order as config.json passes them.
func sameSettings(php, json, scratch string) bool {
	phpPath, jsonPath := filepath.Join(scratch, "config.php"), filepath.Join(scratch, "config.json")

	if os.WriteFile(phpPath, []byte(php), 0o644) != nil || os.WriteFile(jsonPath, []byte(json), 0o644) != nil {
		return false
	}

	fromPHP, err := config.ReadPHP(phpPath)
	if err != nil {
		return false
	}

	fromJSON, err := config.ReadJSON(jsonPath)
	if err != nil {
		return false
	}

	return reflect.DeepEqual(fromPHP.Positional(), fromJSON)
}

// equateDeclarations makes the configure() statement the golden shows and the "configure" block the run
// shows one line when both declare the same. The statement names its detector as the config.php it is
// written into imports it.
func equateDeclarations(want, got, scratch string) (string, string) {
	php, shown := block(want, "$config->configure(", func(line, _ string) bool { return strings.HasSuffix(line, "));") })
	json, alsoShown := block(got, `"configure": {`, func(line, indent string) bool { return line == indent+"}" })

	if !shown || !alsoShown || !sameSettings("<?php\nuse JesseGall\\CodeCommandments\\Detectors\\Backend\\NamespaceDependencyDetector;\nreturn function ($config) {\n"+php+"\n};\n", "{\n"+json+"\n}\n", scratch) {
		return want, got
	}

	same := "<the same layers declared>"

	return strings.Replace(want, php, same, 1), strings.Replace(got, json, same, 1)
}

// block is the lines of text from the first that holds the opening to the first after it that closes it.
func block(text, opening string, closes func(line, indent string) bool) (string, bool) {
	lines := strings.Split(text, "\n")

	for start, line := range lines {
		if !strings.Contains(line, opening) {
			continue
		}

		indent := line[:len(line)-len(strings.TrimLeft(line, " "))]

		for end := start; end < len(lines); end++ {
			if closes(lines[end], indent) {
				return strings.Join(lines[start:end+1], "\n"), true
			}
		}
	}

	return "", false
}
