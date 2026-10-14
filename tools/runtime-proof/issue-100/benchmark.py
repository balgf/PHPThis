"""Matched, closed-loop HTTP/1.1 benchmark. Run in a separate limited client container."""
import asyncio
import json
import math
import resource
import statistics
import time

async def response(reader):
    head = await reader.readuntil(b'\r\n\r\n')
    assert len(head) <= 16384
    lines = head.split(b'\r\n')
    assert lines[0].split()[1] == b'200', head
    headers = dict(line.split(b':', 1) for line in lines[1:] if b':' in line)
    headers = {key.lower(): value.strip().lower() for key, value in headers.items()}
    if headers.get(b'transfer-encoding') == b'chunked':
        parts = []
        while True:
            size = int((await reader.readline()).split(b';')[0], 16)
            if size == 0:
                assert await reader.readline() == b'\r\n'
                break
            parts.append(await reader.readexactly(size))
            assert await reader.readexactly(2) == b'\r\n'
        body = b''.join(parts)
    else:
        size = int(headers[b'content-length'])
        assert size <= 65536
        body = await reader.readexactly(size)
    return json.loads(body), headers.get(b'connection') == b'close'

async def sample(port, limit, concurrency, requests):
    samples = []
    cpu_start = time.process_time()
    began = time.perf_counter()
    async def worker(index):
        reader, writer = await asyncio.open_connection('127.0.0.1', port, limit=65536)
        try:
            for _ in range(index, requests, concurrency):
                start = time.perf_counter()
                writer.write(f'GET /documents?tenant=1&limit={limit} HTTP/1.1\r\nHost: proof\r\nConnection: keep-alive\r\n\r\n'.encode())
                await writer.drain()
                data, closed = await asyncio.wait_for(response(reader), 5)
                assert data['marker'] == 'default' and len(data['data']) == limit
                assert [row['id'] for row in data['data']] == list(range(1001, 1001 + limit))
                assert all(row['events'] == 3 for row in data['data'])
                samples.append((time.perf_counter() - start) * 1000)
                if closed:
                    writer.close()
                    await writer.wait_closed()
                    reader, writer = await asyncio.open_connection('127.0.0.1', port, limit=65536)
        finally:
            writer.close()
            await writer.wait_closed()
    await asyncio.gather(*(worker(index) for index in range(concurrency)))
    elapsed = time.perf_counter() - began
    ordered = sorted(samples)
    def percentile(fraction):
        return ordered[max(0, math.ceil(fraction * len(ordered)) - 1)]
    return {
        'requests': len(samples), 'elapsed_seconds': elapsed,
        'requests_per_second': len(samples) / elapsed,
        'median_ms': statistics.median(samples), 'p95_ms': percentile(.95), 'p99_ms': percentile(.99),
        'client_cpu_fraction': (time.process_time() - cpu_start) / elapsed,
        'latency_ms': samples,
    }

async def main():
    results = []
    print(json.dumps({'kind': 'configuration', 'closed_loop': True, 'requests_per_sample': 1500, 'warmup_requests': 128}), flush=True)
    for limit in (2, 50):
        for concurrency in (1, 16):
            for repetition in range(3):
                # Alternate order to reduce consistent warmup/thermal ordering bias.
                ports = (8080, 8081) if repetition % 2 == 0 else (8081, 8080)
                for port in ports:
                    await sample(port, limit, concurrency, 128)
                    result = await sample(port, limit, concurrency, 1500)
                    result.update(runtime='fpm' if port == 8080 else 'swoole', limit=limit,
                                  concurrency=concurrency, repetition=repetition + 1)
                    result['kind'] = 'sample'
                    results.append(result)
                    print(json.dumps(result), flush=True)
    print(json.dumps({'kind': 'complete', 'samples': len(results), 'requests': sum(row['requests'] for row in results), 'client_max_rss_kib': resource.getrusage(resource.RUSAGE_SELF).ru_maxrss}), flush=True)

asyncio.run(main())
