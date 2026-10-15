"""Bounded capture for this disposable fixture's owned subprocesses."""
import os
import re
import selectors
import signal
import subprocess
import time

IMAGE = 'mysql:8.4@sha256:0744ee5ef89ce6ccfa13de3e579fe6b9e27f93dd70da9c06d2c908b1b193fb8d'


def wait_for_mysql(name, *, timeout, phase):
    deadline = time.monotonic() + timeout
    last = 'probe=not_started'
    while time.monotonic() < deadline:
        try:
            # The image's initialization server has a socket but no TCP listener.
            # The service-local shell receives no command-line credential.
            child = subprocess.Popen(
                ['docker', 'exec', name, 'sh', '-c',
                 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql --protocol=TCP '
                 '--host=127.0.0.1 --port=3306 --connect-timeout=2 -uroot -Nse "SELECT 1"'],
                stdin=subprocess.DEVNULL, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
            )
            code, out, err = capture(child, min(5, max(0.001, deadline - time.monotonic())), limit=4096)
            if code == 0 and out == b'1\n' and not err and time.monotonic() < deadline:
                return
            last = failure_details(code, err)
        except (RuntimeError, subprocess.TimeoutExpired) as failure:
            if str(failure) == 'fixture_process_output_limit':
                raise RuntimeError('mysql_fixture_' + phase + '_failed probe=output_limit') from None
            if not isinstance(failure, subprocess.TimeoutExpired) and str(failure) != 'fixture_process_timeout':
                raise
            # A short final probe must not erase an earlier useful server code.
            if last == 'probe=not_started':
                last = 'probe=timeout'
        except OSError:
            raise RuntimeError('mysql_fixture_' + phase + '_failed probe=unavailable') from None
        time.sleep(min(0.2, max(0, deadline - time.monotonic())))
    raise RuntimeError('mysql_fixture_' + phase + '_timeout ' + last)


def failure_details(code, stderr):
    """Expose only numeric exit/MySQL codes and a fixed-width SQLSTATE."""
    match = re.search(rb'(?m)^ERROR ([0-9]{4}) \(([A-Z0-9]{5})\)', stderr[:4096])
    detail = 'exit=' + str(code)
    if match:
        detail += ' mysql_error=' + match[1].decode('ascii') + ' sqlstate=' + match[2].decode('ascii')
    return detail


def capture(child, timeout, limit=65536):
    """Reap a child with finite stream bytes and elapsed time; kill on failure."""
    streams = {'out': bytearray(), 'err': bytearray()}
    deadline = time.monotonic() + timeout
    try:
        if child.stdin and not child.stdin.closed:
            child.stdin.close()
        with selectors.DefaultSelector() as selector:
            selector.register(child.stdout, selectors.EVENT_READ, 'out')
            selector.register(child.stderr, selectors.EVENT_READ, 'err')
            while selector.get_map():
                remaining = deadline - time.monotonic()
                if remaining <= 0:
                    raise RuntimeError('fixture_process_timeout')
                for key, _ in selector.select(min(0.1, remaining)):
                    data = os.read(key.fd, 4096)
                    if not data:
                        selector.unregister(key.fileobj)
                    else:
                        streams[key.data] += data
                        if len(streams[key.data]) > limit:
                            raise RuntimeError('fixture_process_output_limit')
        child.wait(timeout=max(0.001, deadline - time.monotonic()))
        return child.returncode, bytes(streams['out']), bytes(streams['err'])
    except BaseException:
        if child.poll() is None:
            child.kill()
            child.wait(timeout=1)
        raise
    finally:
        child.stdout.close()
        child.stderr.close()


def owned_group(command, *, cwd, env, timeout):
    child = subprocess.Popen(command, cwd=cwd, env=env, stdin=subprocess.DEVNULL,
                             stdout=subprocess.PIPE, stderr=subprocess.PIPE, start_new_session=True)
    try:
        return capture(child, timeout)
    finally:
        # The Composer process may exit while a PHP/probe descendant is alive.
        # Descendants inherit this group; stop only the group this runner created.
        try:
            os.killpg(child.pid, signal.SIGTERM)
            time.sleep(0.25)
            os.killpg(child.pid, signal.SIGKILL)
        except ProcessLookupError:
            pass
