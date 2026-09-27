// Package stubs carries the helpers `scaffold` writes into a project, each a file its namespace is filled into.
package stubs

import "embed"

// Files are the stubs, by the path a sin's scaffold names.
//
//go:embed *.stub vue
var Files embed.FS
