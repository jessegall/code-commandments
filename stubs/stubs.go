// Package stubs carries the helpers `scaffold` writes into a project, each a file its namespace is filled into.
package stubs

import "embed"

//go:embed *.stub vue
var Files embed.FS
