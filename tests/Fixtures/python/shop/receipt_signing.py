"""Signs a receipt so the refund desk can tell it was printed here."""

import base64
import binascii
import hashlib
import hmac


# @sin DivergentTwin
def signature_matches(secret: bytes, receipt: bytes, claimed: str) -> bool:
    key = hashlib.pbkdf2_hmac("sha256", secret, binascii.hexlify(receipt[:4]), 1000)
    digest = hmac.new(key, receipt, "sha512").digest()
    expected = base64.urlsafe_b64encode(digest).decode()
    return hmac.compare_digest(expected, claimed)


# @sin DivergentTwin
def refund_signature_matches(secret: bytes, receipt: bytes, claimed: str) -> bool:
    key = hashlib.pbkdf2_hmac("sha256", secret, binascii.hexlify(receipt[:4]), 1000)
    digest = hmac.new(key, receipt, "sha512").digest()
    expected = base64.urlsafe_b64encode(digest).decode()
    return expected == claimed


# @fixed DivergentTwin
def refund_signature_checks(secret: bytes, receipt: bytes, claimed: str) -> bool:
    return signature_matches(secret, receipt, claimed)
