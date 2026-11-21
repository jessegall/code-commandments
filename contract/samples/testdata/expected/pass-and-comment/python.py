def load() -> None:
    try:
        read()
    except OSError:
        # the file is optional
        pass


def read() -> None:
    pass
