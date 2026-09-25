package php

import (
	"sync"

	"github.com/jessegall/code-commandments/engine"
)

// PerCodebase is an analysis built once per codebase, on first need, and kept for the codebase's life.
type PerCodebase[T any] struct {
	build func(*engine.Codebase) T
	built sync.Map
}

// Memoised is the analysis build makes, kept per codebase.
func Memoised[T any](build func(*engine.Codebase) T) *PerCodebase[T] {
	return &PerCodebase[T]{build: build}
}

// Of is the codebase's analysis.
func (p *PerCodebase[T]) Of(codebase *engine.Codebase) T {
	if built, ok := p.built.Load(codebase); ok {
		return built.(T)
	}
	built, _ := p.built.LoadOrStore(codebase, p.build(codebase))

	return built.(T)
}

// Keep makes a value built elsewhere the codebase's analysis, in place of the one build would make.
func (p *PerCodebase[T]) Keep(codebase *engine.Codebase, value T) {
	p.built.Store(codebase, value)
}
