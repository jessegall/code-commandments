# The facts a dealer's record is built from, gathered by steps that each write one field onto the record they
# were handed. The gatherer below them reads every field straight back off, so each step's signature says the
# record goes in and says nothing at all about what comes out of it.


class DealerRecord:
    def __init__(self, dealer_id: str) -> None:
        self.dealer_id = dealer_id
        self.plan = ""
        self.language = ""


class DealerFacts:
    def __init__(self, plans: dict[str, str], languages: dict[str, str]) -> None:
        self.plans = plans
        self.languages = languages

    def gather(self, dealer_id: str) -> str:
        record = DealerRecord(dealer_id)
        self.apply_plan(record)
        self.apply_language(record)

        return f"{record.dealer_id}:{record.plan}:{record.language}"

    # @sin FilledParameter
    # @sin FeatureEnvy
    def apply_plan(self, record: DealerRecord) -> None:
        record.plan = self.plans.get(record.dealer_id, "free")

    # @sin FilledParameter
    # @sin FeatureEnvy
    def apply_language(self, record: DealerRecord) -> None:
        record.language = self.languages.get(record.dealer_id, "nl")
