package parity

import (
	"os"
	"path/filepath"
	"reflect"
	"strings"

	"github.com/jessegall/code-commandments/cli/config"
)

const (
	phpConfig  = ".commandments/config.php"
	jsonConfig = ".commandments/config.json"
	schemaFile = ".commandments/config.schema.json"
)

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

func equateDeclarations(want, got, scratch string) (string, string) {
	php, shown := block(want, "$config->configure(", func(line, _ string) bool { return strings.HasSuffix(line, "));") })
	json, alsoShown := block(got, `"configure": {`, func(line, indent string) bool { return line == indent+"}" })

	if !shown || !alsoShown || !sameSettings("<?php\nuse JesseGall\\CodeCommandments\\Detectors\\Backend\\NamespaceDependencyDetector;\nreturn function ($config) {\n"+php+"\n};\n", "{\n"+json+"\n}\n", scratch) {
		return want, got
	}

	same := "<the same layers declared>"

	return strings.Replace(want, php, same, 1), strings.Replace(got, json, same, 1)
}

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
