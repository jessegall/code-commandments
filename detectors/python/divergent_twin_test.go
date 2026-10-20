package python_test

import (
	"fmt"
	"testing"

	"github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// signing is two defs signing a receipt alike, the second never comparing the signature it built.
const signing = `import base64
import binascii
import hashlib
import hmac


def %s(secret: bytes, receipt: bytes, claimed: str) -> bool:
    key = hashlib.pbkdf2_hmac("sha256", secret, binascii.hexlify(receipt[:4]), 1000)
    digest = hmac.new(key, receipt, "sha512").digest()
    expected = base64.urlsafe_b64encode(digest).decode()
    return hmac.compare_digest(expected, claimed)


def %s(secret: bytes, receipt: bytes, claimed: str) -> bool:
    key = hashlib.pbkdf2_hmac("sha256", secret, binascii.hexlify(receipt[:4]), 1000)
    digest = hmac.new(key, receipt, "sha512").digest()
    expected = base64.urlsafe_b64encode(digest).decode()
    return expected == claimed
`

// TestTwoTestsAreNoTwins holds the twin reading to production code: two tests of different scenarios, in a module
// the project's pytest python_files names, share the steps of what they test, and one taking a step the other does
// not is a scenario of its own, not a fix forgotten (#673). The same two defs outside a test still diverge.
func TestTwoTestsAreNoTwins(t *testing.T) {
	tested := pythontest.FromSource(t, map[string]string{
		"pytest.ini":           "[pytest]\npython_files = test.py\n",
		"receipts/__init__.py": "",
		"receipts/test.py":     fmt.Sprintf(signing, "test_a_signed_receipt_is_accepted", "test_a_refund_receipt_is_signed"),
	})
	for _, finding := range (python.DivergentTwinDetector{}).Find(tested) {
		t.Errorf("%s is a test, yet it is read as a divergent twin", finding.Location())
	}

	shipped := pythontest.FromSource(t, map[string]string{
		"receipts/__init__.py": "",
		"receipts/signing.py":  fmt.Sprintf(signing, "signature_matches", "refund_signature_matches"),
	})
	if found := (python.DivergentTwinDetector{}).Find(shipped); len(found) != 2 {
		t.Errorf("the two signing defs outside a test are found %d times, not as one divergent pair", len(found))
	}
}
