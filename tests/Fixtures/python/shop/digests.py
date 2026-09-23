# Daily digests hook into the event bus the same way stock alerts do — the registration copied, with
# only the feature's name changed.


# @sin NearDuplicateFunction
def register(bus) -> None:
    bus.intercept(WatchKeyword("digests", whole_words=True))
    bus.handle(RepeatStanding("digests", every=50))
    bus.handle(MentionInChat("digests", limit=1))
    bus.log.info("registered %s", "digests")
