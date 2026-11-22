import logging


def load() -> str:
    try:
        return read()
    # @contract kind/handler
    except OSError as error:
        logging.warning("read failed: %s", error)
        # @contract role/return/values -> [empty]
        return "" # @label empty literal
