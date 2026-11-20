def reservation(
    seat: str,
    # @sin UnusedParameter
    section: str,
):
    row, _, number = seat.partition(":")
    if number.isdigit():
        return f"{row.upper()}-{int(number):02d}"
    return row.strip().upper()


def reservation_label(value: str, prefix: str) -> str:
    return prefix + value
