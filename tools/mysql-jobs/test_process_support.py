"""Offline adversarial controls for the fixture's bounded readiness probes."""
import io
import subprocess
import sys
import time
import unittest
from unittest.mock import patch

import process_support


class ReadinessTests(unittest.TestCase):
    def run_probe(self, source, *, phase='startup', timeout=0.4):
        original_popen = subprocess.Popen
        children = []

        def start(_command, **options):
            child = original_popen([sys.executable, '-c', source], **options)
            children.append(child)
            return child

        try:
            with patch.object(process_support.subprocess, 'Popen', side_effect=start):
                process_support.wait_for_mysql('synthetic-fixture', timeout=timeout, phase=phase)
        finally:
            stopped = all(child.poll() is not None for child in children)
            for child in children:
                if child.poll() is None:
                    child.kill()
                    child.wait(timeout=1)
            self.assertTrue(stopped, 'Readiness left a probe process alive.')

    def test_ready_requires_exact_success(self):
        self.run_probe('print(1)', timeout=3)
        with self.assertRaisesRegex(RuntimeError, '^mysql_fixture_startup_timeout exit=0$'):
            self.run_probe('print("unexpected-private-sentinel")')

    def test_failed_queries_retain_codes_without_native_details(self):
        source = 'import sys; sys.stderr.write("ERROR 1045 (28000): private-password and SQL\\n"); sys.exit(1)'
        for phase in ('startup', 'restart'):
            with self.subTest(phase=phase), self.assertRaises(RuntimeError) as failure:
                self.run_probe(source, phase=phase)
            self.assertEqual(str(failure.exception),
                             'mysql_fixture_' + phase + '_timeout exit=1 mysql_error=1045 sqlstate=28000')

    def test_hanging_probe_obeys_deadline_and_is_reaped(self):
        began = time.monotonic()
        with self.assertRaisesRegex(RuntimeError, '^mysql_fixture_startup_timeout probe=timeout$'):
            self.run_probe('import time; time.sleep(30)', timeout=0.15)
        self.assertLess(time.monotonic() - began, 2)

    def test_output_flood_is_bounded_and_redacted(self):
        with self.assertRaisesRegex(RuntimeError, '^mysql_fixture_startup_failed probe=output_limit$'):
            self.run_probe('import os, time; os.write(2, b"private-sentinel" * 1000); time.sleep(30)', timeout=3)

    def test_diagnostics_allow_only_codes(self):
        self.assertEqual(process_support.failure_details(1, b'ERROR 1064 (42000) at line 2: SELECT private-password'),
                         'exit=1 mysql_error=1064 sqlstate=42000')
        for value in (b'private-password', b'ERROR secret (TOKEN): private-password',
                      b'prefix ERROR 1064 (42000): private-password', b'x' * 4096 + b'ERROR 1064 (42000)'):
            self.assertEqual(process_support.failure_details(1, value), 'exit=1')


if __name__ == '__main__':
    output = io.StringIO()
    result = unittest.TextTestRunner(stream=output).run(unittest.defaultTestLoader.loadTestsFromTestCase(ReadinessTests))
    if not result.wasSuccessful():
        sys.stderr.write(output.getvalue())
        raise SystemExit(1)
    print('PASS MySQL fixture process controls')
