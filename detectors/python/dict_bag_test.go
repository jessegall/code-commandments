package python_test

import (
	"testing"

	"github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// TestReadsThatBecomeOneValueObjectAreTheFixAndNotTheSin holds the rule off the conversion it asks for: loose data
// off the wire, read key by key and handed straight to a type the project declares, IS the dict bag's own fix.
// httpx reads five fields out of an HTTP challenge header and builds its own value object out of them, and the rule
// flagged all five. A mapping read in two places, or read and handed on as a mapping, is the sin it was written for.
func TestReadsThatBecomeOneValueObjectAreTheFixAndNotTheSin(t *testing.T) {
	for name, test := range map[string]struct {
		source string
		want   int
	}{
		"five reads that become one value object": {
			"class Challenge:\n    def __init__(self, realm: str, nonce: str, qop: str | None) -> None:\n        self.realm = realm\n        self.nonce = nonce\n        self.qop = qop\n\n\ndef parse(header: dict[str, str]) -> Challenge:\n    realm = header['realm']\n    nonce = header['nonce']\n    qop = header['qop'] if 'qop' in header else None\n\n    return Challenge(realm=realm, nonce=nonce, qop=qop)\n",
			0,
		},
		"a read that goes nowhere near a declared type": {
			"def shout(header: dict[str, str]) -> str:\n    return header['realm'].upper() + header['nonce']\n",
			2,
		},
		"a mapping read and handed on as a mapping": {
			"class Challenge:\n    def __init__(self, realm: str) -> None:\n        self.realm = realm\n\n\ndef parse(header: dict[str, str]) -> tuple[Challenge, dict[str, str]]:\n    realm = header['realm']\n\n    return Challenge(realm=realm), header\n",
			1,
		},
	} {
		t.Run(name, func(t *testing.T) {
			codebase := pythontest.FromSource(t, map[string]string{"shop/auth.py": test.source})
			found := (python.DictBagDetector{}).Find(codebase)

			if len(found) != test.want {
				t.Errorf("found %d, want %d: %v", len(found), test.want, found)
			}
		})
	}
}
