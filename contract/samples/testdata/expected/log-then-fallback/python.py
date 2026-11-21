import logging


def load() -> str:
    try:
        return read()
    except OSError as error:
        logging.warning("read failed: %s", error)
        return ""
