package php

import "github.com/jessegall/code-commandments/engine"

// PerCodebase is an analysis built once per codebase, on first need, and kept for the codebase's life.
type PerCodebase[T any] struct {
	build func(*engine.Codebase) T
}

func Memoised[T any](build func(*engine.Codebase) T) *PerCodebase[T] {
	return &PerCodebase[T]{build: build}
}

func (p *PerCodebase[T]) Of(codebase *engine.Codebase) T {
	return engine.Analysis(codebase, p, p.build)
}

func (p *PerCodebase[T]) Keep(codebase *engine.Codebase, value T) {
	engine.Keep(codebase, p, value)
}
