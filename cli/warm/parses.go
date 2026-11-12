package warm

import (
	"path/filepath"
	"slices"
	"strings"
	"sync"
	"time"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/cache"
)

const mostNodesKept = 1_000_000

// Parses is what the bridges wrote for a file, kept by the digest of the file's bytes and nothing else, so any checkout holding those bytes is answered without a bridge.
type Parses struct {
	now     func() time.Time
	mu      sync.Mutex
	held    map[string]*parse
	headers map[contract.Language]contract.Header
	nodes   int
}

// parse is one kept tree: the file as a bridge wrote it, the checkout it was read in, the stream it came in and when it was last used.
type parse struct {
	file     *contract.File
	checkout string
	language contract.Language
	used     time.Time
}

func Parsing(now func() time.Time) *Parses {
	return &Parses{now: now, held: map[string]*parse{}, headers: map[contract.Language]contract.Header{}}
}

func (p *Parses) Find(files []string, checkout string) ([]*contract.Stream, []string, map[string]string) {
	p.mu.Lock()
	defer p.mu.Unlock()

	now := p.now()
	p.forgetExpired(now)

	found := map[contract.Language][]*contract.File{}
	digests := make(map[string]string, len(files))

	var unknown []string

	for _, path := range files {
		digests[path] = Digest(path)

		kept, known := p.held[digests[path]]
		if !known || !kept.fits(path) {
			unknown = append(unknown, path)

			continue
		}

		kept.used = now
		found[kept.language] = append(found[kept.language], kept.file.CopiedTo(path, kept.movedTo(checkout)))
	}

	var streams []*contract.Stream
	for language, parsed := range found {
		streams = append(streams, &contract.Stream{Header: p.headers[language], Files: parsed})
	}

	return streams, unknown, digests
}

func (k *parse) fits(path string) bool {
	if filepath.Ext(path) != filepath.Ext(k.file.Path) {
		return false
	}

	if k.file.Module == "" {
		return true
	}

	dotted := strings.ReplaceAll(strings.TrimSuffix(filepath.ToSlash(path), filepath.Ext(path)), "/", ".")
	dotted = strings.TrimSuffix(dotted, ".__init__")

	return strings.HasSuffix(dotted, "."+k.file.Module)
}

func (k *parse) movedTo(checkout string) func(string) string {
	return func(path string) string {
		under, inside := strings.CutPrefix(path, strings.TrimRight(k.checkout, "/")+"/")
		if !inside {
			return path
		}

		return filepath.Join(checkout, under)
	}
}

func (p *Parses) Keeps(streams []*contract.Stream, digests map[string]string, checkout string) {
	p.mu.Lock()
	defer p.mu.Unlock()

	now := p.now()

	for _, stream := range streams {
		p.headers[stream.Header.Language] = stream.Header

		for _, file := range stream.Files {
			if digest := digests[file.Path]; digest != "" && digest == Digest(file.Path) {
				p.keep(digest, file, stream.Header.Language, checkout, now)
			}
		}
	}

	p.forgetTheOldest()
}

func (p *Parses) keepsAsTheyWere(streams []*contract.Stream, digests map[string]string, checkout string) {
	p.mu.Lock()
	defer p.mu.Unlock()

	now := p.now()

	for _, stream := range streams {
		p.headers[stream.Header.Language] = stream.Header

		for _, file := range stream.Files {
			if digest := digests[file.Path]; digest != "" {
				p.keep(digest, file, stream.Header.Language, checkout, now)
			}
		}
	}

	p.forgetTheOldest()
}

func (p *Parses) keep(digest string, file *contract.File, language contract.Language, checkout string, now time.Time) {
	if was, known := p.held[digest]; known {
		was.used = now

		return
	}

	p.held[digest] = &parse{file.CopiedTo(file.Path, func(path string) string { return path }), checkout, language, now}
	p.nodes += len(file.Nodes())
}

func (p *Parses) forgetExpired(now time.Time) {
	for digest, kept := range p.held {
		if now.Sub(kept.used) >= cache.KeptFor() {
			p.forget(digest)
		}
	}
}

func (p *Parses) forget(digest string) {
	p.nodes -= len(p.held[digest].file.Nodes())
	delete(p.held, digest)
}

func (p *Parses) forgetTheOldest() {
	if p.nodes <= mostNodesKept {
		return
	}

	digests := make([]string, 0, len(p.held))
	for digest := range p.held {
		digests = append(digests, digest)
	}

	slices.SortFunc(digests, func(one, other string) int { return p.held[one].used.Compare(p.held[other].used) })

	for _, digest := range digests {
		if p.nodes <= mostNodesKept*3/4 {
			return
		}

		p.forget(digest)
	}
}

func (p *Parses) Knows(path string) bool {
	p.mu.Lock()
	defer p.mu.Unlock()

	_, known := p.held[Digest(path)]

	return known
}

func (p *Parses) Nodes() int {
	p.mu.Lock()
	defer p.mu.Unlock()

	return p.nodes
}

func (p *Parses) Count() int {
	p.mu.Lock()
	defer p.mu.Unlock()

	return len(p.held)
}
