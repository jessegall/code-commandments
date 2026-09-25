package repent_test

import (
	"bufio"
	"compress/gzip"
	"encoding/json"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine/frontend"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/shop"
	_ "github.com/jessegall/code-commandments/registry"
	"github.com/jessegall/code-commandments/scribes"
	"github.com/jessegall/code-commandments/scribes/repent"
)

// answer is one file the PHP tool's repent rewrote in a fixture: by one step alone, or by the whole chain ("*").
type answer struct {
	Fixture string `json:"fixture"`
	Step    string `json:"step"`
	Path    string `json:"path"`
	Content string `json:"content"`
	Error   string `json:"error"`
	Skipped string `json:"skipped"`
}

type everywhere struct{}

func (everywhere) Includes(string) bool { return true }
func (everywhere) IsScoped() bool       { return false }

// answers is the PHP tool's rewrites, keyed by fixture, then step, then path.
func answers(t *testing.T) map[string]map[string]map[string]answer {
	file, err := os.Open(filepath.Join(shop.Testdata(), "repent.jsonl.gz"))
	if err != nil {
		t.Fatal(err)
	}
	defer file.Close()
	unzipped, err := gzip.NewReader(file)
	if err != nil {
		t.Fatal(err)
	}
	all := map[string]map[string]map[string]answer{}
	lines := bufio.NewScanner(unzipped)
	lines.Buffer(nil, 64<<20)
	for lines.Scan() {
		var line answer
		if err := json.Unmarshal(lines.Bytes(), &line); err != nil {
			t.Fatal(err)
		}
		if all[line.Fixture] == nil {
			all[line.Fixture] = map[string]map[string]answer{}
		}
		if all[line.Fixture][line.Step] == nil {
			all[line.Fixture][line.Step] = map[string]answer{}
		}
		all[line.Fixture][line.Step][line.Path] = line
	}
	if err := lines.Err(); err != nil {
		t.Fatal(err)
	}

	return all
}

func TestEveryPortedStepRewritesEachFixtureAsThePHPToolDoes(t *testing.T) {
	answered := answers(t)
	for _, fixture := range []string{"backend", "frontend"} {
		root, err := filepath.EvalSymlinks(filepath.Join(shop.Repository(), "tests", "Fixtures", fixture))
		if err != nil {
			t.Fatal(err)
		}
		backend, vue := scanners(t)
		for _, step := range repent.Chain(backend, vue, detectors.All()).Steps() {
			t.Run(fixture+"/"+step.Name(), func(t *testing.T) {
				rewrites, err := step.Run(scribes.Pass{Roots: []string{root}, Scope: everywhere{}, Frozen: scribes.Frozens{backend, vue}})
				want := answered[fixture][step.Name()]
				if err != nil {
					t.Fatalf("the step broke: %v (PHP: %q)", err, want[""].Error)
				}
				got := map[string]string{}
				for _, path := range rewrites.Paths() {
					got[strings.TrimPrefix(path, root+"/")] = rewrites.Content(path)
				}
				for path, answer := range want {
					if path == "" {
						continue
					}
					if got[path] != answer.Content {
						t.Errorf("%s differs from PHP's rewrite:\n--- go\n%s\n--- php\n%s", path, got[path], answer.Content)
					}
				}
				for path := range got {
					if _, ok := want[path]; !ok {
						t.Errorf("%s is rewritten here, and not by PHP", path)
					}
				}
			})
		}
	}
}

func scanners(t *testing.T) (*scribes.Scanner, *scribes.Scanner) {
	command, err := php.Here().Command()
	if err != nil {
		t.Fatal(err)
	}
	backend, err := scribes.Serve(command, php.Over)
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { backend.Close() })
	command, err = frontend.Here().Command()
	if err != nil {
		t.Fatal(err)
	}
	vue, err := scribes.Serve(command, frontend.Over)
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { vue.Close() })

	return backend, vue
}

func TestTheWholeChainRewritesEachFixtureAsThePHPToolDoes(t *testing.T) {
	answered := answers(t)
	for _, fixture := range []string{"backend", "frontend"} {
		t.Run(fixture, func(t *testing.T) {
			root, err := filepath.EvalSymlinks(filepath.Join(shop.Repository(), "tests", "Fixtures", fixture))
			if err != nil {
				t.Fatal(err)
			}
			backend, vue := scanners(t)
			chain := repent.Chain(backend, vue, detectors.All())
			ported := map[string]bool{}
			for _, step := range chain.Steps() {
				ported[step.Name()] = true
			}
			var missing []string
			for step := range answered[fixture] {
				if step != "*" && !ported[step] {
					missing = append(missing, step)
				}
			}
			if len(missing) > 0 {
				t.Fatalf("PHP's chain runs steps the Go chain lacks: %v", missing)
			}

			converged := scribes.Converge(chain, []string{root}, everywhere{}, scribes.Frozens{backend, vue})
			if !converged.Settled || len(converged.Skipped) > 0 {
				t.Fatalf("settled %v, skipped %v", converged.Settled, converged.Skipped)
			}
			want := answered[fixture]["*"]
			got := converged.Files.Contents()
			for path, answer := range want {
				if answer.Skipped != "" {
					t.Errorf("PHP skipped %s", answer.Skipped)

					continue
				}
				if got[root+"/"+path] != answer.Content {
					t.Errorf("%s differs from PHP's:\n--- go\n%s\n--- php\n%s", path, got[root+"/"+path], answer.Content)
				}
			}
			for path := range got {
				if _, ok := want[strings.TrimPrefix(path, root+"/")]; !ok {
					t.Errorf("%s is rewritten here, and not by PHP", path)
				}
			}
		})
	}
}
