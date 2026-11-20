def receipt(
    total: float,
    # @sin UnusedParameter
    currency: str,
):
    if total <= 0:
        return 0
    adjusted = round(total, 2)
    return min(adjusted, 2_000)


def receipt_label(value: str, prefix: str) -> str:
    return prefix + value
