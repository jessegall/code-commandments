package frontend

// The frontend scribes are held to what the PHP tool's own frontend detector steps rewrite. Recording asks PHP once
// for every project the tests give a scribe, and commits the answers, so the tests never need PHP.
//go:generate go test -count=1 -run . -record
