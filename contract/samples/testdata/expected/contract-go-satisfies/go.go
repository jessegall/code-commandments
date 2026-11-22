package shop

// @label reader type
type Reader interface{ Read() string }

// @contract link/type/satisfies -> [reader]
type File struct{}

// @contract fact/*/obligations on function = [{"symbol":"contract_go_satisfies.Reader.Read","origin":"project"}]
func (File) Read() string { return "" }

type Pair[K comparable, V any] struct{ key K }

// @contract role/type-expression/typeArguments at 12 -> [stringArg, intArg]
// @label stringArg type-expression 17
// @label intArg type-expression 25
var shared Pair[string, int]
