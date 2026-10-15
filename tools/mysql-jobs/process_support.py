"""Bounded capture for this disposable fixture's owned subprocesses."""
import os
import selectors
import signal
import subprocess
import time

IMAGE = 'mysql:8.4@sha256:0744ee5ef89ce6ccfa13de3e579fe6b9e27f93dd70da9c06d2c908b1b193fb8d'


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
