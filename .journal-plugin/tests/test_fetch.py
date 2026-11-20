import gzip
import hashlib
import os
import platform
from pathlib import Path
import shutil
import stat
import subprocess
import tempfile
import unittest


class FetchTest(unittest.TestCase):
    def setUp(self):
        folder = tempfile.TemporaryDirectory()
        self.addCleanup(folder.cleanup)
        root = Path(folder.name)
        plugin = root / ".journal-plugin"
        plugin.mkdir()
        self.fetch = plugin / "fetch"
        shutil.copyfile(Path(__file__).resolve().parents[1] / "fetch", self.fetch)
        self.target = root / "bin" / "commandments-release"
        self.target.parent.mkdir()
        self.releases = root / "releases"
        self.release = self.releases / "v1.2.3"
        self.release.mkdir(parents=True)
        os_name = platform.system().lower()
        machine = platform.machine().lower()
        arch = {"x86_64": "amd64", "amd64": "amd64", "aarch64": "arm64", "arm64": "arm64"}.get(machine)
        if os_name not in ("darwin", "linux") or not arch:
            self.skipTest("fetch only supports Darwin and Linux on ARM64 and AMD64")
        self.name = f"commandments-{os_name}-{arch}"
        self.binary = b"the release executable\n"

    def asset(self, name, content, checksum=None):
        (self.release / name).write_bytes(content)
        digest = checksum or hashlib.sha256(content).hexdigest()
        with (self.release / "SHA256SUMS").open("a", encoding="utf-8") as sums:
            sums.write(f"{digest}  {name}\n")

    def run_fetch(self):
        return subprocess.run(
            ["sh", str(self.fetch)],
            env={
                **os.environ,
                "COMMANDMENTS_RELEASE": "v1.2.3",
                "COMMANDMENTS_RELEASES": self.releases.as_uri(),
            },
            text=True,
            capture_output=True,
        )

    def test_prefers_the_verified_gzip_and_installs_the_executable(self):
        self.asset(self.name, self.binary)
        self.asset(self.name + ".gz", gzip.compress(self.binary))
        result = self.run_fetch()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn(self.name + ".gz", result.stdout)
        self.assertEqual(self.target.read_bytes(), self.binary)
        self.assertEqual(stat.S_IMODE(self.target.stat().st_mode), 0o755)

    def test_legacy_release_still_installs_the_verified_raw_binary(self):
        self.asset(self.name, self.binary)
        result = self.run_fetch()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(self.target.read_bytes(), self.binary)

    def test_corrupt_gzip_leaves_the_existing_installation_in_place(self):
        self.target.write_bytes(b"existing executable")
        self.asset(self.name, self.binary)
        self.asset(self.name + ".gz", gzip.compress(self.binary), checksum="0" * 64)
        result = self.run_fetch()
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("does not match its SHA256SUMS", result.stderr)
        self.assertEqual(self.target.read_bytes(), b"existing executable")
        self.assertEqual(list(self.target.parent.iterdir()), [self.target])

    def test_malformed_verified_archive_is_not_installed(self):
        self.asset(self.name, self.binary)
        self.asset(self.name + ".gz", b"not a gzip archive")
        result = self.run_fetch()
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("could not unpack", result.stderr)
        self.assertEqual(list(self.target.parent.iterdir()), [])
