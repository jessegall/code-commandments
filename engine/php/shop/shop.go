// Package shop holds the PHP analyses to the answers the PHP engine gave on the backend shop fixture before it was removed.
package shop

import (
	"bufio"
	"bytes"
	"compress/gzip"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"runtime"
	"slices"
	"strings"
	"sync"
	"testing"

	"github.com/jessegall/code-commandments/bridge/bundle"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

const Root = "/shop"

func Repository() string {
	_, source, _, _ := runtime.Caller(0)

	return filepath.Join(filepath.Dir(source), "..", "..", "..")
}

func Fixture() string {
	return filepath.Join(Repository(), "tests", "Fixtures", "backend")
}

func Testdata() string {
	return filepath.Join(Repository(), "engine", "php", "testdata")
}

var inputs = []string{"tests/Fixtures/backend", "bridge/php", "bridge/frontend/dist"}

func Digest() (string, error) {
	return DigestOf(inputs...)
}

func DigestOf(inputs ...string) (string, error) {
	tracked, err := git.Tracked(Repository(), inputs...)
	if err != nil {
		return "", err
	}
	var paths []string
	for _, path := range tracked {
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

var Frozen = sync.OnceValues(func() (string, error) {
	archive, err := os.ReadFile(filepath.Join(Oracle(), "fixtures.tar.gz"))
	if err != nil {
		return "", err
	}

	return bundle.Archived("shop", archive).Folder()
})

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

func Project(t testing.TB) *engine.Codebase {
	t.Helper()
	codebase, err := project()
	if err != nil {
		t.Fatalf("the committed shop streams do not load (run go generate ./engine/php): %v", err)
	}

	return codebase
}

func OracleProject(t testing.TB) *engine.Codebase {
	t.Helper()
	codebase, err := oracleProject()
	if err != nil {
		t.Fatalf("the oracle's shop streams do not load: %v", err)
	}

	return codebase
}

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

func Node(t testing.TB, answer Answer) (engine.Match, bool) {
	t.Helper()
	Codebase(t)
	match, ok := indexes()[answer.File][located{answer.Span, answer.Kind}]

	return match, ok
}

func Parity(t *testing.T, question string, ask func(answer Answer, node engine.Match) any) {
	t.Helper()
	answers := Answers(t, question)
	var divergences []string
	for i, answer := range answers {
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
			answers[i].Answer = got
			divergences = append(divergences, fmt.Sprintf("%s:%d %s ask %s: PHP %s, Go %s", answer.File, node.Line(), answer.Kind, answer.Ask, answer.Answer, got))
		}
	}
	if len(divergences) > 0 && os.Getenv(Amend) == question {
		amend(t, question, answers)
		t.Logf("%d of %d answers to %s now say what Go says:\n%s", len(divergences), len(answers), question, strings.Join(divergences, "\n"))

		return
	}
	if len(divergences) > 0 {
		shown := divergences[:min(len(divergences), 40)]
		t.Fatalf("%d of %d answers to %s differ from PHP:\n%s", len(divergences), len(answers), question, strings.Join(shown, "\n"))
	}
}

const Amend = "COMMANDMENTS_AMEND_ORACLE"

func amend(t *testing.T, question string, answers []Answer) {
	t.Helper()
	var written bytes.Buffer
	zipped := gzip.NewWriter(&written)
	for _, answer := range answers {
		line, err := json.Marshal(answer)
		if err != nil {
			t.Fatal(err)
		}
		zipped.Write(append(line, '\n'))
	}
	if err := zipped.Close(); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(Oracle(), "answers", question+".jsonl.gz"), written.Bytes(), 0o644); err != nil {
		t.Fatal(err)
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
