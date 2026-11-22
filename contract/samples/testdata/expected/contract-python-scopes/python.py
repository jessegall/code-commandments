from os import path as location, sep
from . import sibling
from .pkg import thing

counter = 0


class Counter:
    limit: int = 3
    step = 1


def outer():
    total = 0

    def add():
        nonlocal total
        total += 1

    global counter
    with open("a") as handle, open("b"):
        pass
    for item in range(3):
        break
    else:
        total = 2
    while total:
        total -= 1
    try:
        pass
    except ValueError:
        pass
    else:
        pass
    finally:
        pass


async def guarded(lock):
    async with lock:
        pass
