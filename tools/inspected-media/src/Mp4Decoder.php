<?php

declare(strict_types=1);

namespace CatalogImageRecipe;

final class Mp4Decoder
{
    public static function verify(string $bytes, BoundedMp4 $mp4): void
    {
        if ($bytes === '' || strlen($bytes) > 1_048_576) { throw new CatalogUnavailable('Decoder input exceeds bounds.'); }
        $process = null; $pipes = [];
        try {
            $process = @proc_open([
                '/usr/local/bin/ffprobe', '-v', 'warning', '-max_alloc', '8388608', '-threads', '1', '-max_pixels', '307200',
                '-err_detect', 'crccheck+bitstream+buffer+explode', '-protocol_whitelist', 'pipe', '-format_whitelist', 'mov',
                '-codec_whitelist', 'h264', '-enable_drefs', '0', '-use_absolute_path', '0', '-f', 'mov',
                '-show_frames', '-show_streams', '-show_entries',
                'stream=index,codec_name,profile,codec_type,codec_tag_string,width,height,pix_fmt,level,has_b_frames,time_base,duration_ts,nb_frames:frame=stream_index,pts,width,height,pix_fmt,pict_type,interlaced_frame,repeat_pict:frame_side_data=side_data_type',
                '-of', 'json=compact=1', '-i', 'pipe:0',
            ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes,
                '/', ['PATH' => '/usr/local/bin:/usr/bin:/bin']);
            if (!is_resource($process)) { throw new CatalogUnavailable('Decoder unavailable.'); }
            stream_set_blocking($pipes[0], false); stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
            $offset = 0; $out = ''; $err = ''; $deadline = hrtime(true) + 8_000_000_000;
            do {
                if (isset($pipes[0])) {
                    if ($offset < strlen($bytes)) {
                        $written = @fwrite($pipes[0], substr($bytes, $offset, 8192));
                        if ($written === false) { throw new CatalogUnavailable('Decoder input failed.'); }
                        $offset += $written;
                    }
                    if ($offset === strlen($bytes)) { fclose($pipes[0]); unset($pipes[0]); }
                }
                $a = stream_get_contents($pipes[1], 65_537 - strlen($out));
                $b = stream_get_contents($pipes[2], 8193 - strlen($err));
                if ($a === false || $b === false) { throw new CatalogUnavailable('Decoder output failed.'); }
                $out .= $a; $err .= $b;
                if (strlen($out) > 65_536 || strlen($err) > 8192) { throw new CatalogUnavailable('Decoder output exceeds bounds.'); }
                $status = proc_get_status($process);
                if (!$status['running'] && feof($pipes[1]) && feof($pipes[2])) {
                    if ($status['exitcode'] !== 0 || $err !== '' || $offset !== strlen($bytes)) { throw new CatalogUnavailable('Decoder failed.'); }
                    $mp4->verifyReport($out);
                    return;
                }
                usleep(10_000);
            } while (hrtime(true) < $deadline);
            throw new CatalogUnavailable('Decoder deadline exceeded.');
        } finally {
            if (is_resource($process)) {
                $status = proc_get_status($process);
                if ($status['running']) {
                    proc_terminate($process, 9); $stop = hrtime(true) + 1_000_000_000;
                    do { usleep(10_000); $status = proc_get_status($process); }
                    while ($status['running'] && hrtime(true) < $stop);
                }
                foreach ($pipes as $pipe) { fclose($pipe); }
                if (!$status['running']) { proc_close($process); }
                // The outer supervisor removes the whole container before any promotion.
            }
        }
    }
}
