package samples

import (
	"encoding/json"
	"fmt"
	"maps"
	"os"
	"path/filepath"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/contract/marker"
)

// Scenario is a small project that proves a promise across files: per language, the files a bridge reads and the
// sets of them it is asked to write.
type Scenario struct {
	Name      string
	Folder    string
	Languages map[contract.Language]ScenarioRun `json:"languages"`
}

// ScenarioRun is one language's part of a scenario: the files read, each write mask tried, and the marked sources.
type ScenarioRun struct {
	Files   []string   `json:"files"`
	Masks   [][]string `json:"masks"`
	Sources map[string]marker.Source
	Bytes   map[string][]byte
}

// ScenarioAnswer is what a bridge wrote for each write mask of a scenario, in the masks' order, and where the project
// was placed.
type ScenarioAnswer struct {
	Root    string
	Streams [][]*contract.File
}

// Relation is what a promise asserts, checked over one language's answers to a scenario.
type Relation func(run ScenarioRun, answer ScenarioAnswer, each marker.Marker) Occurrence

var relations = map[string]Relation{}

func Register(promise string, relation Relation) {
	relations[promise] = relation
}

func Scenarios(folder string) ([]Scenario, []error) {
	described, err := filepath.Glob(filepath.Join(folder, "*", "scenario.json"))
	if err != nil {
		return nil, []error{err}
	}
	var scenarios []Scenario
	var faults []error
	for _, path := range described {
		text, err := os.ReadFile(path)
		if err != nil {
			return nil, []error{err}
		}
		scenario := Scenario{Name: filepath.Base(filepath.Dir(path)), Folder: filepath.Dir(path)}
		if err := json.Unmarshal(text, &scenario); err != nil {
			faults = append(faults, fmt.Errorf("%s: %v", path, err))

			continue
		}
		for language, run := range scenario.Languages {
			run.Sources, run.Bytes = map[string]marker.Source{}, map[string][]byte{}
			var sources []marker.Source
			for _, file := range run.Files {
				text, err := os.ReadFile(filepath.Join(scenario.Folder, string(language), file))
				if err != nil {
					faults = append(faults, err)

					continue
				}
				fileLanguage, known := contract.LanguageOfPath(file)
				if !known {
					continue
				}
				source, read := marker.ReadSource(fileLanguage, scenario.Name+"/"+string(language)+"/"+file, text)
				faults = append(faults, read...)
				run.Sources[file], run.Bytes[file] = source, text
				sources = append(sources, source)
			}
			faults = append(faults, marker.CheckTargets(sources)...)
			scenario.Languages[language] = run
		}
		scenarios = append(scenarios, scenario)
	}

	return scenarios, faults
}

func (s Scenario) Place(root string, language contract.Language) error {
	return os.CopyFS(root, os.DirFS(filepath.Join(s.Folder, string(language))))
}

func (s Scenario) Check(language contract.Language, answer ScenarioAnswer) []Occurrence {
	run := s.Languages[language]
	var found []Occurrence
	for _, file := range slices.Sorted(maps.Keys(run.Sources)) {
		for _, each := range run.Sources[file].Markers {
			if each.Key == "" {
				continue
			}
			if !strings.HasPrefix(each.Key, "promise/") {
				found = append(found, s.checked(run, answer, file, each, language))

				continue
			}
			relation, known := relations[strings.TrimPrefix(each.Key, "promise/")]
			if !known {
				found = append(found, Occurrence{Marker: each, Language: language, Case: s.Name, Status: Invalid, Reason: "no relation checks this promise"})

				continue
			}
			occurrence := relation(run, answer, each)
			occurrence.Marker, occurrence.Language, occurrence.Case = each, language, s.Name
			found = append(found, occurrence)
		}
	}

	return found
}

func (r ScenarioRun) Written(answer ScenarioAnswer, mask int, name string) *contract.File {
	if mask >= len(answer.Streams) {
		return nil
	}
	for _, file := range answer.Streams[mask] {
		if filepath.Base(file.Path) == filepath.Base(name) {
			return file
		}
	}

	return nil
}

func (s Scenario) checked(run ScenarioRun, answer ScenarioAnswer, name string, each marker.Marker, language contract.Language) Occurrence {
	written := Written{Source: run.Sources[name], Bytes: run.Bytes[name], File: run.Written(answer, len(answer.Streams)-1, name)}
	var occurrence Occurrence
	switch {
	case written.File == nil:
		occurrence = Occurrence{Status: Wrong, Reason: name + " was not written"}
	case strings.HasPrefix(each.Key, "fact/"):
		occurrence = CheckFact(nil, written, each)
	default:
		occurrence = CheckShape(nil, written, each)
	}
	occurrence.Marker, occurrence.Language, occurrence.Case = each, language, s.Name

	return occurrence
}
