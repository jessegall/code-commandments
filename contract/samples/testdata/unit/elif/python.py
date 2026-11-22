def size(n: int) -> str:
    if n < 10:
        return "small"
    elif n < 100:
        return "medium"
    else:
        return "large"
