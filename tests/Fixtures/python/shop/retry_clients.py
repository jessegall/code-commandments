# A supplier client whose retry limit is declared under the method that reads it. Below it, the FIX:
# the constant at the head of the class.


class SupplierClient:
    def __init__(self, http) -> None:
        self.http = http

    def fetch(self, path: str) -> bytes:
        for _ in range(self.RETRIES):
            response = self.http.get(path)
            if response.ok:
                return response.body
        return b""

    # @sin MemberAfterMethod
    RETRIES = 3


# @fixed MemberAfterMethod
class PatientSupplierClient:
    RETRIES = 3

    def __init__(self, http) -> None:
        self.http = http

    def fetch(self, path: str) -> bytes:
        responses = (self.http.get(path) for _ in range(self.RETRIES))
        return next((response.body for response in responses if response.ok), b"")
