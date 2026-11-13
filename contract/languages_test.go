package contract

import "testing"

// A stream is named for the language that holds every other its files are in: Vue components with the TypeScript they
// import make a Vue stream, TypeScript alone a TypeScript one.
func TestAStreamIsNamedForTheLanguageThatHoldsTheOthers(t *testing.T) {
	for _, case_ := range []struct {
		languages []Language
		named     Language
	}{
		{[]Language{TypeScript, Vue}, Vue},
		{[]Language{Vue, TypeScript}, Vue},
		{[]Language{TypeScript}, TypeScript},
		{[]Language{Python}, Python},
	} {
		if named := StreamLanguage(case_.languages); named != case_.named {
			t.Errorf("a stream of %v is named %s, not %s", case_.languages, named, case_.named)
		}
	}
}
