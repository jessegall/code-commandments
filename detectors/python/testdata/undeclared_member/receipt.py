class Receipt:
    def reference(self) -> str:
        return "pending"


class BarcodedReceipt(Receipt):
    def barcode(self) -> str:
        return "RB001"


def receive(receipt: Receipt) -> str:
    # @sin UndeclaredMember
    return receipt.barcode()


def receive_barcoded(receipt: BarcodedReceipt) -> str:
    # @fixed UndeclaredMember
    return receipt.barcode()
