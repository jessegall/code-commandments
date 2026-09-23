"""Staff badges, printed from the code each badge carries."""


def badge_text(code: str) -> str:
    return f"[{code.upper()}]"


def front_desk(code: str) -> str:
    return badge_text(code)


def warehouse(code: str) -> str:
    return badge_text(code.strip())


def visitor(number: int) -> str:
    # @righteous ConvertedArgument
    return badge_text(str(number))
