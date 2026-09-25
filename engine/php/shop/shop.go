// Package shop holds the Go port of the PHP analyses to the PHP engine's own answers on the backend shop fixture:
// the fixture's stream and the oracle's answers are generated once and committed, so a parity test runs without PHP.
package shop

import (
	"bufio"
	"compress/gzip"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"io/fs"
	"os"
	"path/filepath"
	"runtime"
	"slices"
	"strings"
	"sync"
	"testing"

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

// inputs are what the committed files are generated from, folders and files: the fixture, the PHP tool the oracle
// asks (its engine, and the rules whose findings and definitions it records), the lock that pins php-parser, the
// bridge and the oracle. A change to any makes them stale.
var inputs = []string{"tests/Fixtures/backend", "src", "composer.lock", "bridge/php", "bridge/frontend/dist", "engine/php/oracle"}

// Digest is the hash of every source the committed files are generated from.
func Digest() (string, error) {
	hash := sha256.New()
	for _, input := range inputs {
		var paths []string
		err := filepath.WalkDir(filepath.Join(Repository(), input), func(path string, entry fs.DirEntry, err error) error {
			if err == nil && !entry.IsDir() && (slices.Contains([]string{".php", ".txt", ".ts", ".vue", ".mjs"}, filepath.Ext(path)) || filepath.Base(path) == "composer.lock") {
				paths = append(paths, path)
			}

			return err
		})
		if err != nil {
			return "", err
		}
		slices.Sort(paths)
		for _, path := range paths {
			source, err := os.ReadFile(path)
			if err != nil {
				return "", err
			}
			relative, _ := filepath.Rel(Repository(), path)
			fmt.Fprintf(hash, "%s\x00%d\x00", filepath.ToSlash(relative), len(source))
			hash.Write(source)
		}
	}

	return hex.EncodeToString(hash.Sum(nil)), nil
}

var loaded = sync.OnceValues(func() (*engine.Codebase, error) {
	stream, err := readStream("shop.jsonl.gz")
	if err != nil {
		return nil, err
	}

	return engine.New(readFixture, stream), nil
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

func readFixture(path string) ([]byte, error) {
	return os.ReadFile(filepath.Join(Fixture(), strings.TrimPrefix(path, Root+"/")))
}

// Project is the whole shop as judge reads it: its PHP beside its frontend, so a rule reading what one side
// publishes for the other sees both.
func Project(t testing.TB) *engine.Codebase {
	t.Helper()
	codebase, err := project()
	if err != nil {
		t.Fatalf("the committed shop streams do not load (run go generate ./engine/php): %v", err)
	}

	return codebase
}

// Codebase is the shop, read from the committed stream once per test binary.
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
	handle, err := os.Open(filepath.Join(Testdata(), "answers", question+".jsonl.gz"))
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
