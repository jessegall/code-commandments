// Package shop holds the backend shop fixture as the bridges read it, committed as streams so a test reads a whole project
// without running a bridge.
package shop

import (
	"compress/gzip"
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"os"
	"path/filepath"
	"runtime"
	"slices"
	"strings"
	"sync"
	"testing"

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

var project = sync.OnceValues(func() (*engine.Codebase, error) {
	backend, err := readStream("shop.jsonl.gz")
	if err != nil {
		return nil, err
	}
	frontend, err := readStream("shop-frontend.jsonl.gz")
	if err != nil {
		return nil, err
	}

	codebase := engine.New(readFixture, backend, frontend)
	codebase.Rooted(Root)
	return codebase, nil
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

func readFixture(path string) ([]byte, error) {
	return os.ReadFile(filepath.Join(Fixture(), strings.TrimPrefix(path, Root+"/")))
}

// Files is a codebase of only the shop files at the paths given under the fixture, from the committed stream.
func Files(t testing.TB, paths ...string) *engine.Codebase {
	t.Helper()
	stream, err := readStream("shop.jsonl.gz")
	if err != nil {
		t.Fatalf("the committed shop stream does not load (run go run ./engine/php/shop/generate): %v", err)
	}
	var kept []*contract.File
	for _, file := range stream.Files {
		if slices.Contains(paths, strings.TrimPrefix(file.Path, Root+"/")) {
			kept = append(kept, file)
		}
	}
	if len(kept) != len(paths) {
		t.Fatalf("the shop stream holds %d of the %d files asked for", len(kept), len(paths))
	}
	stream.Files = kept

	return engine.New(readFixture, stream)
}

func Project(t testing.TB) *engine.Codebase {
	t.Helper()
	codebase, err := project()
	if err != nil {
		t.Fatalf("the committed shop streams do not load (run go run ./engine/php/shop/generate): %v", err)
	}

	return codebase
}
