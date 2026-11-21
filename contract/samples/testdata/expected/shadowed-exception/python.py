class ValueError(Exception):
    pass


def check() -> None:
    try:
        int("x")
    except ValueError:
        pass
