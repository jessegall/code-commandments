package prose

import (
	"regexp"
	"strings"
)

// marker is a fixture marker: `@sin Name`, `@fixed Name`, `@righteous Name` or `@example Name bad|good`.
var marker = regexp.MustCompile(`^@(?:(?:sin|fixed|righteous)\s+\w+|example\s+\w+\s+(?:bad|good))$`)

// IsFixtureMarker says whether a comment's words are nothing but a fixture marker, which marks code for the
// fixture and says nothing about it.
func IsFixtureMarker(words string) bool {
	return marker.MatchString(strings.TrimSpace(words))
}
