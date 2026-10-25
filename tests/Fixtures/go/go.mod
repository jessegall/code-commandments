// The Go fixture is a module of its own, so the tool's own `go build ./...`, `go vet ./...` and `go test ./...`
// never reach it: a fixture holds code written to be judged, not code written to compile cleanly, and a nested
// module is no part of the module above it.
module commandments.fixture/shop

go 1.25
