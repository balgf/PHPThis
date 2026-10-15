"""Offline controls for case-specific, private real-parser failure reporting."""
import io
import json
import sys
import unittest

import run


class ParserDiagnosticsTests(unittest.TestCase):
    def failure(self, code, out, err):
        with self.assertRaises(RuntimeError) as failure:
            run.checked_parser_result(code, out, err)
        return str(failure.exception)

    def test_success_preserves_parser_evidence(self):
        evidence = {'format_cases': ['valid_rgb'], 'resource_cases': ['memory']}
        self.assertEqual(run.checked_parser_result(0, json.dumps(evidence).encode(), b''), evidence)

    def test_format_resource_and_cleanup_failures_keep_the_case_and_stage(self):
        for label, cleanup in (
            ('format_valid_rgb_inspect', 'passed'),
            ('format_large_pipe_input_cleanup', 'failed'),
            ('resource_memory_container_state', 'passed'),
            ('resource_memory_termination', 'passed'),
            ('resource_memory_small_termination', 'passed'),
            ('resource_memory_kill_termination', 'passed'),
            ('resource_timeout_elapsed', 'passed'),
            ('resource_output_inspect', 'failed'),
        ):
            with self.subTest(label=label, cleanup=cleanup):
                error = f'REAL_PARSER_FAIL {label} cleanup={cleanup}\n'.encode()
                self.assertEqual(self.failure(1, b'', error), f'real_parser_{label}_cleanup_{cleanup}')

    def test_unrecognized_or_mixed_process_output_remains_private(self):
        valid = b'REAL_PARSER_FAIL format_valid_rgb_inspect cleanup=passed\n'
        for code, out, err in (
            (0, b'', valid), (-9, b'', valid), (1, b'private-stdout', valid),
            (1, b'', b'private-password /private/path'),
            (1, b'', valid + b'private-stderr'),
            (1, b'', b'REAL_PARSER_FAIL format_private_payload_inspect cleanup=passed\n'),
            (1, b'', b'REAL_PARSER_FAIL resource_memory_elapsed cleanup=passed\n'),
            (1, b'', b'REAL_PARSER_FAIL format_valid_rgb_inspect cleanup=private_payload\n'),
            (1, b'', b'x' * 65536),
        ):
            with self.subTest(code=code, size=len(err)):
                self.assertEqual(self.failure(code, out, err), 'real_parser_failed')

    def test_real_fixture_reports_argument_failure_without_docker(self):
        code, out, err = run.php('tests/real-parser.php')
        self.assertEqual((code, out, err),
                         (1, b'', b'REAL_PARSER_FAIL setup_arguments cleanup=not_started\n'))
        self.assertEqual(self.failure(code, out, err), 'real_parser_setup_arguments_cleanup_not_started')

    def test_real_fixture_reports_definition_failure_without_disclosing_input(self):
        code, out, err = run.php('tests/real-parser.php', 'private-image-sentinel', 'private-fault-sentinel')
        self.assertEqual((code, out, err),
                         (1, b'', b'REAL_PARSER_FAIL setup_definition cleanup=not_started\n'))
        self.assertEqual(self.failure(code, out, err), 'real_parser_setup_definition_cleanup_not_started')


if __name__ == '__main__':
    if len(sys.argv) == 2:
        run.PHP = sys.argv[1]
    output = io.StringIO()
    result = unittest.TextTestRunner(stream=output).run(
        unittest.defaultTestLoader.loadTestsFromTestCase(ParserDiagnosticsTests))
    if not result.wasSuccessful():
        sys.stderr.write(output.getvalue())
        raise SystemExit(1)
    print('PASS PNG parser diagnostic controls')
