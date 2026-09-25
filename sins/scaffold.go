package sins

// Scaffold is a reusable helper a sin's fix uses, generated into a project from a stub.
type Scaffold struct {
	// Path is where the helper goes: under the PHP source root, or under resources/js for the frontend.
	Path string
	// Stub is the file under stubs/ it is rendered from, its `{namespace}` filled.
	Stub string
	// Frontend says it goes under the frontend root rather than the PHP source root.
	Frontend bool
}

// Scaffolding is a sin whose fix uses helpers the project may not have yet.
type Scaffolding interface {
	Scaffolds() []Scaffold
}
