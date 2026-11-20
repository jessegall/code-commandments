# Look-alikes that do not swallow everything. A handler that names the failure it expects has decided
# what that failure means; a boundary that catches everything records it before moving on.

import logging
import os

log = logging.getLogger(__name__)


def remove_upload(path: str) -> None:
    try:
        os.remove(path)
    # @righteous SwallowCatch
    except FileNotFoundError:
        pass


def nightly(jobs) -> None:
    for job in jobs:
        try:
            job.run()
        # @righteous SwallowCatch
        except Exception:
            log.exception("nightly job %s failed", job.name)
