// Package skills carries the curriculum the binary publishes into a project: the generated skills under
// commandments/ and every hand-written skill beside them, each a folder holding its SKILL.md.
package skills

import "embed"

// Files are the skill folders as the package ships them.
//
//go:embed *
var Files embed.FS
