// Package skills carries the curriculum the binary publishes into a project: the generated skills under commandments/ and every hand-written skill beside them, each a folder holding its SKILL.md.
package skills

import "embed"

//go:embed *
var Files embed.FS
