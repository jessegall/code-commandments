package prose

import (
	"regexp"
	"strings"
)

var marker = regexp.MustCompile(`^@(?:(?:sin|fixed|righteous)\s+\w+|example\s+\w+\s+(?:bad|good))$`)

func IsFixtureMarker(words string) bool {
	return marker.MatchString(strings.TrimSpace(words))
}
