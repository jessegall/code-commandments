package php

import "github.com/jessegall/code-commandments/engine"

// PerCodebase is an analysis built once per codebase, on first need, and kept for the codebase's life.
type PerCodebase[T any] struct {
	build func(*engine.Codebase) T
}

// Memoised is the analysis build makes, kept per codebase.
func Memoised[T any](build func(*engine.Codebase) T) *PerCodebase[T] {
	return &PerCodebase[T]{build: build}
}

// Of is the codebase's analysis, kept on the codebase itself so it goes when the codebase does.
func (p *PerCodebase[T]) Of(codebase *engine.Codebase) T {
	return engine.Analysis(codebase, p, p.build)
}
