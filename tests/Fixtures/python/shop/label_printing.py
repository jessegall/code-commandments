# Prints a label by shelling out to the printer's own tool, inside a coroutine. The subprocess holds the event
# loop until the tool exits, so nothing else the shop is doing asynchronously moves until the label is out.
# Below it, the look-alike that is not the sin: the same call handed to a thread, which is how it is done.

import asyncio
import subprocess


class LabelPrinter:
    async def print_label(self, label: str) -> int:
        # @sin BlockedAwait
        done = subprocess.run(["lp", label], check=False)

        return done.returncode

    # @righteous BlockedAwait
    async def print_on_a_thread(self, label: str) -> int:
        done = await asyncio.to_thread(subprocess.run, ["lp", label], check=False)

        return done.returncode
