from one import Base


class Job(Base):
    def run(self) -> None:  # @contract promise/obligation-across-files -> one.py#base
        pass
