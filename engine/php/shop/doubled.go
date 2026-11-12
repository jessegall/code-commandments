package shop

import (
	"testing"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func Fresh(t testing.TB) *engine.Codebase {
	t.Helper()

	return engine.New(readFixture, streamsOf(t)...)
}

func Doubled(t testing.TB) *engine.Codebase {
	t.Helper()

	var doubled []*contract.Stream
	for _, stream := range streamsOf(t) {
		doubled = append(doubled, stream.TwiceOver())
	}

	return engine.New(readFixture, doubled...)
}

func streamsOf(t testing.TB) []*contract.Stream {
	t.Helper()

	var streams []*contract.Stream
	for _, name := range []string{"shop.jsonl.gz", "shop-frontend.jsonl.gz"} {
		stream, err := readStream(name)
		if err != nil {
			t.Fatalf("the committed shop streams do not load (run go generate ./engine/php): %v", err)
		}

		streams = append(streams, stream)
	}

	return streams
}
