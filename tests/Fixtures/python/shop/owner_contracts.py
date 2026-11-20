from typing import Protocol


# @righteous LoneImplementerInterface
class ReceiptFormatter(Protocol):
    def format_number(self, reference: str) -> str:
        ...


class PlainReceipt(ReceiptFormatter):
    def format_number(self, reference: str) -> str:
        return reference.strip()


class NumberedReceipt(ReceiptFormatter):
    def format_number(self, reference: str) -> str:
        return f"Receipt #{reference}"


class ReceiptJob:
    def __init__(
        self,
        # @righteous ConcreteCrossModuleDependency
        # @fixed ConcreteCrossModuleDependency
        receipt: ReceiptFormatter,
        reference: str,
    ):
        self.receipt = receipt
        self.reference = reference

    # @righteous MiddleMan
    # @fixed MiddleMan
    def caption(self) -> str:
        return "Customer copy: " + self._format_current()

    # @righteous UnusedPrivateMember
    def _format_current(self) -> str:
        return self.receipt.format_number(self.reference)


class LongReceipt(PlainReceipt):
    # @righteous RefusedBequest
    # @fixed RefusedBequest
    def format_number(self, reference: str) -> str:
        return "Customer copy: " + super().format_number(reference)


# @righteous HandRolledRegistry
class ReceiptHistory:
    def __init__(self):
        self.tags: dict[str, str] = {}

    def remember(self, key: str, text: str) -> None:
        self.tags[key] = text

    def tag(self, key: str) -> str | None:
        # @sin NullableRegistryLookup
        return self.tags.get(key)


# @righteous TrainWreck
# @fixed TrainWreck
def receipt_caption(job: ReceiptJob) -> str:
    return job.caption()


def affected_receipts(references: list[str]) -> list[str]:
    # @righteous QueryInLoop
    return [reference.strip() for reference in references]
