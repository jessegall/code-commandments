package python_test

import (
	"testing"

	"github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

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
