"""Bounded, private process capture for the disposable catalog proof."""
import os
import selectors
import signal
import subprocess
import time


def capture(command, *, cwd, env, timeout=20, limit=65536):
    child = subprocess.Popen(command, cwd=cwd, env=env, stdin=subprocess.DEVNULL,
                             stdout=subprocess.PIPE, stderr=subprocess.PIPE, start_new_session=True)
    output = {'out': bytearray(), 'err': bytearray()}
    deadline = time.monotonic() + timeout
    try:
        with selectors.DefaultSelector() as selector:
            selector.register(child.stdout, selectors.EVENT_READ, 'out')
            selector.register(child.stderr, selectors.EVENT_READ, 'err')
            while selector.get_map():
                if time.monotonic() >= deadline:
                    raise RuntimeError('proof_process_timeout')
                for key, _ in selector.select(0.05):
                    data = os.read(key.fd, 4096)
                    if not data:
                        selector.unregister(key.fileobj)
                    else:
                        output[key.data] += data
                        if len(output[key.data]) > limit:
                            raise RuntimeError('proof_process_output_limit')
        code = child.wait(timeout=max(0.001, deadline - time.monotonic()))
        return code, bytes(output['out']), bytes(output['err'])
    finally:
        stop_group(child)
        child.stdout.close()
        child.stderr.close()


def stop_group(child):
    # Own process-group identity; never search by executable or kill another fixture.
    if child.poll() is not None:
        return
    try:
        os.killpg(child.pid, signal.SIGTERM)
        child.wait(timeout=1)
    except subprocess.TimeoutExpired:
        os.killpg(child.pid, signal.SIGKILL)
    except ProcessLookupError:
        pass
    child.wait(timeout=3)
