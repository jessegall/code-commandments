package shop

import (
	"testing"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func Doubled(t testing.TB) *engine.Codebase {
	t.Helper()

	backend, err := readStream("shop.jsonl.gz")
	if err != nil {
		t.Fatalf("the committed shop streams do not load (run go generate ./engine/php): %v", err)
	}

	frontend, err := readStream("shop-frontend.jsonl.gz")
	if err != nil {
		t.Fatalf("the committed shop streams do not load (run go generate ./engine/php): %v", err)
	}

	return engine.New(readFixture, twiceOver(backend), twiceOver(frontend))
}

func twiceOver(stream *contract.Stream) *contract.Stream {
	doubled := *stream
	doubled.Files = make([]*contract.File, 0, len(stream.Files)*2)

	for _, file := range stream.Files {
		doubled.Files = append(doubled.Files, file)
	}

	for _, file := range stream.Files {
		again := *file
		again.Path = file.Path + ".twin.php"
		doubled.Files = append(doubled.Files, &again)
	}

	return &doubled
}
