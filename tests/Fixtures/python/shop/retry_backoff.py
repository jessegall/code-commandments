# A retry backoff whose comment defends the delay against being thought random. Below it, the FIX: the comment
# says what the delay is.


# the delay is not random, it follows the attempt count
# @sin NegativeSpaceComment
def backoff(attempt: int) -> int:
    return 2 ** attempt


# the delay doubles with every attempt
# @fixed NegativeSpaceComment
def retry_delay(attempt: int) -> int:
    return min(2 ** attempt, 60)
