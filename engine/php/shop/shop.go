// Package shop holds the PHP analyses to the answers the PHP engine gave on the backend shop fixture before it was
// removed. Those answers are about the fixture as it read it, kept beside them in testdata/oracle/fixtures.tar.gz with
// the backend and frontend fixtures of that day, so the live fixtures stay free to grow: the oracle's stream is that
// frozen shop's, and Project, what the fixture's own markers are checked against, is the live fixture's.
package shop

import (
	"bufio"
	"compress/gzip"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"slices"
	"strings"
	"sync"
	"testing"

	"github.com/jessegall/code-commandments/bridge/bundle"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Root is the path the committed stream gives the shop, so it names no machine's own folders.
const Root = "/shop"

// Repository is this checkout's root.
func Repository() string {
	_, source, _, _ := runtime.Caller(0)

	return filepath.Join(filepath.Dir(source), "..", "..", "..")
}

// Fixture is the backend shop fixture.
func Fixture() string {
	return filepath.Join(Repository(), "tests", "Fixtures", "backend")
}

// Testdata is where the stream, the answers and the digest are committed.
func Testdata() string {
	return filepath.Join(Repository(), "engine", "php", "testdata")
}

// inputs are what the committed streams are generated from: the live fixture and the bridges that read it. The
// oracle's answers are the PHP engine's last word and are never generated again.
var inputs = []string{"tests/Fixtures/backend", "bridge/php", "bridge/frontend/dist"}

// Digest is the hash of every source the committed files are generated from.
func Digest() (string, error) {
	return DigestOf(inputs...)
}

// DigestOf is the hash of every source git tracks under the repository's folders and files: what a committed
// answer was generated from, so a change to any shows the answer stale. Only tracked files count, so every checkout
// of one commit — a fresh clone, a worktree, the dev container — computes the same digest; a file git ignores, such
// as composer.lock or vendor/, never moves it.
func DigestOf(inputs ...string) (string, error) {
	listing := exec.Command("git", append([]string{"ls-files", "-z", "--"}, inputs...)...)
	listing.Dir = Repository()
	tracked, err := listing.Output()
	if err != nil {
		return "", fmt.Errorf("git ls-files: %w", err)
	}
	var paths []string
	for _, path := range strings.Split(strings.TrimRight(string(tracked), "\x00"), "\x00") {
		if slices.Contains([]string{".php", ".txt", ".ts", ".vue", ".mjs"}, filepath.Ext(path)) || filepath.Base(path) == "composer.json" {
			paths = append(paths, path)
		}
	}
	slices.Sort(paths)
	hash := sha256.New()
	for _, path := range paths {
		source, err := os.ReadFile(filepath.Join(Repository(), path))
		if err != nil {
			return "", err
		}
		fmt.Fprintf(hash, "%s\x00%d\x00", path, len(source))
		hash.Write(source)
	}

	return hex.EncodeToString(hash.Sum(nil)), nil
}

// Frozen is the folder the fixtures the PHP engine answered about are unpacked to, once per version of them: its
// backend and frontend folders.
var Frozen = sync.OnceValues(func() (string, error) {
	archive, err := os.ReadFile(filepath.Join(Oracle(), "fixtures.tar.gz"))
	if err != nil {
		return "", err
	}

	return bundle.Archived("shop", archive).Folder()
})

// Oracle is where the PHP engine's answers are committed, beside the fixtures they are about.
func Oracle() string {
	return filepath.Join(Testdata(), "oracle")
}

var loaded = sync.OnceValues(func() (*engine.Codebase, error) {
	stream, err := readStream(filepath.Join("oracle", "shop.jsonl.gz"))
	if err != nil {
		return nil, err
	}
	frozen, err := Frozen()
	if err != nil {
		return nil, err
	}

	return engine.New(func(path string) ([]byte, error) {
		return os.ReadFile(filepath.Join(frozen, "backend", strings.TrimPrefix(path, Root+"/")))
	}, stream), nil
})

var project = sync.OnceValues(func() (*engine.Codebase, error) {
	backend, err := readStream("shop.jsonl.gz")
	if err != nil {
		return nil, err
	}
	frontend, err := readStream("shop-frontend.jsonl.gz")
	if err != nil {
		return nil, err
	}

	return engine.New(readFixture, backend, frontend), nil
})

var oracleProject = sync.OnceValues(func() (*engine.Codebase, error) {
	backend, err := readStream(filepath.Join("oracle", "shop.jsonl.gz"))
	if err != nil {
		return nil, err
	}
	frontend, err := readStream(filepath.Join("oracle", "shop-frontend.jsonl.gz"))
	if err != nil {
		return nil, err
	}
	frozen, err := Frozen()
	if err != nil {
		return nil, err
	}

	return engine.New(func(path string) ([]byte, error) {
		return os.ReadFile(filepath.Join(frozen, "backend", strings.TrimPrefix(path, Root+"/")))
	}, backend, frontend), nil
})

// readStream reads a committed stream of the shop.
func readStream(name string) (*contract.Stream, error) {
	handle, err := os.Open(filepath.Join(Testdata(), name))
	if err != nil {
		return nil, err
	}
	defer handle.Close()
	unzipped, err := gzip.NewReader(handle)
	if err != nil {
		return nil, err
	}

	return contract.ReadAll(unzipped)
}

// readZipped is a committed file of the shop's testdata, unzipped.
func readZipped(name string) ([]byte, error) {
	handle, err := os.Open(filepath.Join(Testdata(), name))
	if err != nil {
		return nil, err
	}
	defer handle.Close()
	unzipped, err := gzip.NewReader(handle)
	if err != nil {
		return nil, err
	}

	return io.ReadAll(unzipped)
}

func readFixture(path string) ([]byte, error) {
	return os.ReadFile(filepath.Join(Fixture(), strings.TrimPrefix(path, Root+"/")))
}

// Project is the whole live shop as judge reads it: its PHP beside its frontend, so a rule reading what one side
// publishes for the other sees both.
func Project(t testing.TB) *engine.Codebase {
	t.Helper()
	codebase, err := project()
	if err != nil {
		t.Fatalf("the committed shop streams do not load (run go generate ./engine/php): %v", err)
	}

	return codebase
}

// OracleProject is the whole shop the PHP engine answered about, its PHP beside its frontend.
func OracleProject(t testing.TB) *engine.Codebase {
	t.Helper()
	codebase, err := oracleProject()
	if err != nil {
		t.Fatalf("the oracle's shop streams do not load: %v", err)
	}

	return codebase
}

// Codebase is the shop the PHP engine answered about, read from the oracle's stream once per test binary.
func Codebase(t testing.TB) *engine.Codebase {
	t.Helper()
	codebase, err := loaded()
	if err != nil {
		t.Fatalf("the committed shop stream does not load (run go generate ./engine/php): %v", err)
	}

	return codebase
}

// Answer is one answer the PHP engine gave: the node asked about, what was asked of it, and what it said.
type Answer struct {
	File   string          `json:"file"`
	Span   [2]int          `json:"span"`
	Kind   string          `json:"kind"`
	Ask    json.RawMessage `json:"ask"`
	Answer json.RawMessage `json:"answer"`
}

// Answers is every answer the PHP engine gave to the question.
func Answers(t testing.TB, question string) []Answer {
	t.Helper()
	handle, err := os.Open(filepath.Join(Oracle(), "answers", question+".jsonl.gz"))
	if err != nil {
		t.Fatalf("no answers to %s (run go generate ./engine/php): %v", question, err)
	}
	defer handle.Close()
	unzipped, err := gzip.NewReader(handle)
	if err != nil {
		t.Fatal(err)
	}
	var answers []Answer
	lines := bufio.NewScanner(unzipped)
	lines.Buffer(make([]byte, 1<<20), 1<<26)
	for lines.Scan() {
		var answer Answer
		if err := json.Unmarshal(lines.Bytes(), &answer); err != nil {
			t.Fatal(err)
		}
		answers = append(answers, answer)
	}
	if err := lines.Err(); err != nil && err != io.EOF {
		t.Fatal(err)
	}
	if len(answers) == 0 {
		t.Fatalf("the PHP engine gave no answers to %s", question)
	}

	return answers
}

type located struct {
	span [2]int
	kind string
}

var indexes = sync.OnceValue(func() map[string]map[located]engine.Match {
	codebase, _ := loaded()
	byFile := map[string]map[located]engine.Match{}
	for _, file := range codebase.Files() {
		nodes := map[located]engine.Match{}
		for _, node := range file.Nodes() {
			key := located{[2]int{node.Span.Start, node.Span.End}, node.Kind}
			if _, taken := nodes[key]; !taken {
				nodes[key] = file.Match(node.ID)
			}
		}
		byFile[strings.TrimPrefix(file.Path, Root+"/")] = nodes
	}

	return byFile
})

// Node is the shop's node an answer is about: the outermost of its kind at its span.
func Node(t testing.TB, answer Answer) (engine.Match, bool) {
	t.Helper()
	Codebase(t)
	match, ok := indexes()[answer.File][located{answer.Span, answer.Kind}]

	return match, ok
}

// Parity asks the Go port each question the PHP engine answered and fails with every answer that differs. The Go
// answer is compared as JSON, so ask returns what the PHP answer decodes to: a string, a bool, a list, a map, or nil.
func Parity(t *testing.T, question string, ask func(answer Answer, node engine.Match) any) {
	t.Helper()
	answers := Answers(t, question)
	var divergences []string
	for _, answer := range answers {
		node, ok := Node(t, answer)
		if !ok {
			divergences = append(divergences, fmt.Sprintf("%s %v %s: the Go tree has no such node", answer.File, answer.Span, answer.Kind))
			continue
		}
		got, err := json.Marshal(ask(answer, node))
		if err != nil {
			t.Fatal(err)
		}
		if !sameJSON(got, answer.Answer) {
			divergences = append(divergences, fmt.Sprintf("%s:%d %s ask %s: PHP %s, Go %s", answer.File, node.Line(), answer.Kind, answer.Ask, answer.Answer, got))
		}
	}
	if len(divergences) > 0 {
		shown := divergences[:min(len(divergences), 40)]
		t.Fatalf("%d of %d answers to %s differ from PHP:\n%s", len(divergences), len(answers), question, strings.Join(shown, "\n"))
	}
}

func sameJSON(a, b []byte) bool {
	var left, right any
	if json.Unmarshal(a, &left) != nil || json.Unmarshal(b, &right) != nil {
		return false
	}
	normal, _ := json.Marshal(left)
	other, _ := json.Marshal(right)

	return string(normal) == string(other)
}
