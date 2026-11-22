def doubled(
    total: int,  # @label total binding
) -> int:
    return total * 2  # @contract promise/read-links-binding -> total
