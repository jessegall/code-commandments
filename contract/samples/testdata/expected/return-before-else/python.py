def sign(n: int) -> str:
    # @contract kind/if
    # @contract role/if/else -> fallback
    if n < 0:
        # @contract role/return/values -> [negative]
        return "negative" # @label negative literal
    else:
        return "positive" # @label fallback block
