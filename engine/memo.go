package engine

// PerCodebase is an analysis built once per codebase, on first need, and kept for the codebase's life.
type PerCodebase[T any] struct {
	build func(*Codebase) T
}

func Memoised[T any](build func(*Codebase) T) *PerCodebase[T] {
	return &PerCodebase[T]{build: build}
}

func (p *PerCodebase[T]) Of(codebase *Codebase) T {
	return Analysis(codebase, p, p.build)
}

func (p *PerCodebase[T]) Over() any {
	return p
}

func (p *PerCodebase[T]) Keep(codebase *Codebase, value T) {
	Keep(codebase, p, value)
}
