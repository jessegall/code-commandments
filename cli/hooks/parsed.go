package hooks

import (
	"errors"
	"slices"
	"sync"
	"time"

	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/walk"
)

var parsed = keepParsing()

// read is one file as a bridge last wrote it, with what the file was when it was read.
type read struct {
	mark   string
	stream *contract.Stream
}

// parsing holds the files this process has parsed ahead of their judging, and the ones waiting to be.
type parsing struct {
	mu      sync.Mutex
	held    map[string]read
	waiting []string
	asking  map[string]bool
	filled  chan struct{}
	wake    chan struct{}
	reading func(files []string) ([]*contract.Stream, error)
	ahead   bool
}

func keepParsing() *parsing {
	p := &parsing{
		held:    map[string]read{},
		asking:  map[string]bool{},
		filled:  make(chan struct{}),
		wake:    make(chan struct{}, 1),
		reading: readTogether,
		ahead:   true,
	}

	go p.work()

	return p
}

func readTogether(files []string) ([]*contract.Stream, error) {
	codebase, err := scan.Walk(files, source.Excluded{}).Load()

	var incomplete scan.Incomplete
	if errors.As(err, &incomplete) {
		err = nil
	}

	if err != nil || codebase == nil {
		return nil, err
	}

	return codebase.Streams(), nil
}

func (p *parsing) work() {
	for range p.wake {
		for {
			group := p.take()
			if len(group) == 0 {
				break
			}

			p.fill(group)
		}
	}
}

func (p *parsing) take() []string {
	p.mu.Lock()
	defer p.mu.Unlock()

	if len(p.waiting) == 0 {
		return nil
	}

	checkout := git.Root(p.waiting[0])

	var group, later []string
	for _, file := range p.waiting {
		if git.Root(file) != checkout {
			later = append(later, file)

			continue
		}

		group = append(group, file)
		p.asking[file] = true
	}

	p.waiting = later

	return group
}

func (p *parsing) fill(group []string) {
	marks := map[string]string{}
	for _, file := range group {
		marks[file] = walk.Mark(file)
	}

	streams, err := p.reading(group)

	p.mu.Lock()
	defer p.landed(group)
	defer p.mu.Unlock()

	if err != nil {
		return
	}

	p.holdsEach(streams, marks)
}

func (p *parsing) keeps(streams []*contract.Stream, marks map[string]string) {
	p.mu.Lock()
	defer p.mu.Unlock()

	p.holdsEach(streams, marks)
}

func (p *parsing) holdsEach(streams []*contract.Stream, marks map[string]string) {
	for _, stream := range streams {
		for _, file := range stream.Files {
			alone := *stream
			alone.Files = []*contract.File{file}

			p.held[file.Path] = read{mark: marks[file.Path], stream: &alone}
		}
	}
}

func (p *parsing) landed(group []string) {
	p.mu.Lock()

	for _, file := range group {
		delete(p.asking, file)
	}

	woken := p.filled
	p.filled = make(chan struct{})

	p.mu.Unlock()

	close(woken)
}

func (p *parsing) leaves(checkout string) {
	p.mu.Lock()
	defer p.mu.Unlock()

	for file := range p.held {
		if under, inside := walk.Inside(checkout, file); inside && under != "" {
			delete(p.held, file)
		}
	}

	p.waiting = slices.DeleteFunc(p.waiting, func(file string) bool {
		under, inside := walk.Inside(checkout, file)

		return inside && under != ""
	})
}

const Coming = time.Minute
