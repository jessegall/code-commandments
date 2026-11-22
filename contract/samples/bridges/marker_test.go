package bridges

import (
	"bytes"
	"os"
	"path/filepath"
	"slices"
	"strings"
	"sync"
	"sync/atomic"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/contract/marker"
	"github.com/jessegall/code-commandments/contract/samples"
)

const expectedFolder = "../testdata/expected"

// markedSample is one sample source as its markers read it, before any bridge has seen it.
type markedSample struct {
	construct string
	path      string
	language  contract.Language
	bytes     []byte
	source    marker.Source
}

func TestEveryBridgeProvesItsMarkers(t *testing.T) {
	began := time.Now()
	held, faults := markedSamples(t)
	for language, found := range parityFaults(t, held) {
		faults[language] = append(faults[language], found...)
	}
	scenarios, read := samples.Scenarios(filepath.Join("..", "testdata", "scenarios"))
	for _, language := range contract.Languages {
		faults[language] = append(faults[language], read...)
	}
	var requests atomic.Int64
	var mutex sync.Mutex
	occurrences := map[contract.Language][]samples.Occurrence{}
	incomplete := map[contract.Language]bool{}
	starts := 0
	t.Run("bridges", func(t *testing.T) {
		for _, language := range contract.Languages {
			own := held[language]
			if len(own) == 0 && !slices.ContainsFunc(scenarios, func(each samples.Scenario) bool { _, has := each.Languages[language]; return has }) {
				continue
			}
			starts++
			t.Run(string(language), func(t *testing.T) {
				t.Parallel()
				served := servedBridges{t: t, roots: map[contract.Language]string{}, askers: map[contract.Language]bridge.Asker{}}
				defer served.close()
				found, ok := proveSamples(t, &served, own, &requests)
				promised, ran := proveScenarios(t, scenarios, language, &requests)
				found, ok = append(found, promised...), ok && ran
				mutex.Lock()
				defer mutex.Unlock()
				occurrences[language], incomplete[language] = found, !ok
			})
		}
	})
	failed := false
	for _, language := range contract.Languages {
		debt, owing := samples.Owed(filepath.Join("..", "..", "coverage"), language)
		score := samples.ScoreOf(language, occurrences[language], debt, append(slices.Clone(faults[language]), owing...), incomplete[language])
		for _, line := range score.Report() {
			t.Log(line)
		}
		failed = failed || !score.Holds()
	}
	t.Logf("%d bridge starts, %d requests, %s", starts, requests.Load(), time.Since(began).Round(time.Second))
	if failed {
		t.Error("a bridge does not prove the whole contract by its markers: a marker is wrong or malformed, an owed key has no marker, a construct's markers differ across languages, or a bridge did not run")
	}
}

func markedSamples(t *testing.T) (map[contract.Language][]markedSample, map[contract.Language][]error) {
	t.Helper()
	paths, err := filepath.Glob(filepath.Join(expectedFolder, "*", "*.*"))
	if err != nil {
		t.Fatal(err)
	}
	held := map[contract.Language][]markedSample{}
	faults := map[contract.Language][]error{}
	for _, path := range paths {
		language, known := contract.LanguageOfPath(path)
		if !known || strings.HasSuffix(path, ".jsonl") {
			continue
		}
		text, err := os.ReadFile(path)
		if err != nil {
			t.Fatal(err)
		}
		construct := filepath.Base(filepath.Dir(path))
		source, read := marker.ReadSource(language, construct+"/"+filepath.Base(path), text)
		faults[language] = append(faults[language], read...)
		faults[language] = append(faults[language], marker.CheckTargets([]marker.Source{source})...)
		held[language] = append(held[language], markedSample{construct: construct, path: path, language: language, bytes: text, source: source})
	}

	return held, faults
}

func parityFaults(t *testing.T, held map[contract.Language][]markedSample) map[contract.Language][]error {
	t.Helper()
	byConstruct := map[string]map[contract.Language]marker.Source{}
	var constructs []string
	for language, own := range held {
		for _, sample := range own {
			if byConstruct[sample.construct] == nil {
				byConstruct[sample.construct] = map[contract.Language]marker.Source{}
				constructs = append(constructs, sample.construct)
			}
			byConstruct[sample.construct][language] = sample.source
		}
	}
	readme, err := os.ReadFile(filepath.Join(expectedFolder, "README.md"))
	if err != nil {
		t.Fatal(err)
	}
	exclusions, faults := samples.ExclusionsFrom("README.md", readme, constructs)
	for _, construct := range constructs {
		faults = append(faults, samples.Parity(construct, byConstruct[construct], exclusions)...)
	}
	byLanguage := map[contract.Language][]error{}
	for _, language := range contract.Languages {
		byLanguage[language] = faults
	}

	return byLanguage
}

func proveSamples(t *testing.T, served *servedBridges, own []markedSample, requests *atomic.Int64) ([]samples.Occurrence, bool) {
	var found []samples.Occurrence
	for _, sample := range own {
		root, asker, err := served.of(sample.language)
		if err != nil {
			t.Errorf("the %s bridge could not start: %v", sample.language, err)

			return found, false
		}
		module := ""
		if sample.language == contract.Go {
			module = samples.PlacedFolder(sample.construct)
		}
		placed, err := samples.Place(root, sample.construct, sample.path, sample.language, module)
		if err != nil {
			t.Fatal(err)
		}
		requests.Add(1)
		stream, err := asker.AskTallied(bridge.Request{Paths: []string{placed}, Write: []string{placed}}, func() {})
		if err != nil {
			t.Errorf("the %s bridge could not read %s: %v", sample.language, sample.path, err)

			return found, false
		}
		file := writtenFile(stream, placed)
		if file == nil {
			t.Errorf("the %s bridge wrote no file for %s", sample.language, sample.path)

			return found, false
		}
		written := samples.Written{Source: sample.source, Bytes: sample.bytes, File: sampled(t, stream.Header, file, root)}
		scenario := map[string]samples.Written{sample.source.Path: written}
		for _, each := range sample.source.Markers {
			switch {
			case each.Key == "", strings.HasPrefix(each.Key, "promise/"):
			case strings.HasPrefix(each.Key, "fact/"):
				found = append(found, samples.CheckFact(scenario, written, each))
			default:
				found = append(found, samples.CheckShape(scenario, written, each))
			}
		}
	}

	return found, true
}

func proveScenarios(t *testing.T, scenarios []samples.Scenario, language contract.Language, requests *atomic.Int64) ([]samples.Occurrence, bool) {
	var found []samples.Occurrence
	for _, scenario := range scenarios {
		run, has := scenario.Languages[language]
		if !has {
			continue
		}
		root, err := filepath.EvalSymlinks(t.TempDir())
		if err != nil {
			t.Fatal(err)
		}
		if err := scenario.Place(root, language); err != nil {
			t.Fatal(err)
		}
		inRoot := func(names []string) []string {
			var paths []string
			for _, name := range names {
				paths = append(paths, filepath.Join(root, name))
			}

			return paths
		}
		answer := samples.ScenarioAnswer{Root: root}
		for _, mask := range run.Masks {
			requests.Add(1)
			stream, err := bridgeOf[language].ReadWriting([]string{root}, inRoot(run.Files), inRoot(mask))
			if err != nil {
				t.Errorf("the %s bridge could not read the scenario %s: %v", language, scenario.Name, err)

				return found, false
			}
			answer.Streams = append(answer.Streams, inScenario(t, stream, root))
		}
		found = append(found, scenario.Check(language, answer)...)
	}

	return found, true
}

func inScenario(t *testing.T, stream *contract.Stream, root string) []*contract.File {
	t.Helper()
	var written bytes.Buffer
	if err := contract.Write(&written, stream); err != nil {
		t.Fatal(err)
	}
	read, err := contract.ReadAll(strings.NewReader(strings.ReplaceAll(written.String(), root, "/scenario")))
	if err != nil {
		t.Fatal(err)
	}

	return read.Files
}
