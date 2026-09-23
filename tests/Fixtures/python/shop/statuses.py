# A status code from the payment provider, read one `==` at a time, the code on the right-hand side.


def outcome(response) -> str:
    code = response.code
    # @sin SubjectLadder
    if 200 == code:
        return "paid"
    elif 402 == code:
        return "declined"
    elif 409 == code:
        return "duplicate"
    elif 429 == code:
        return "throttled"
    elif 503 == code:
        return "unavailable"
    return "unknown"
