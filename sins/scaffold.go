package sins

// Scaffold is a reusable helper a sin's fix uses, generated into a project from a stub.
type Scaffold struct {
	Path     string
	Stub     string
	Frontend bool
}

// Scaffolding is a sin whose fix uses helpers the project may not have yet.
type Scaffolding interface {
	Scaffolds() []Scaffold
}
