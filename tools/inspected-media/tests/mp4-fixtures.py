"""Regenerate synthetic MP4 seeds with FFmpeg 8.1.2/libx264; not run by composer check."""
import base64, hashlib, json, shutil, subprocess, tempfile
from pathlib import Path
FFMPEG = shutil.which('ffmpeg')
if not FFMPEG or not subprocess.run([FFMPEG, '-version'], capture_output=True, check=True).stdout.startswith(b'ffmpeg version 8.1.2 '):
    raise SystemExit('FFmpeg 8.1.2 with libx264 required')
def encode(name, width=320, height=240, fps=30, frames=30, profile='baseline', extra=None):
    file=HERE/(name+'.mp4')
    cmd=[FFMPEG,'-hide_banner','-loglevel','error','-f','lavfi','-i',f'testsrc2=size={width}x{height}:rate={fps}',
         '-frames:v',str(frames),'-an','-c:v','libx264','-profile:v',profile,'-level:v','3.0','-pix_fmt','yuv420p',
         '-bf','0','-refs','1','-x264-params','keyint=30:min-keyint=30:scenecut=0',
         '-map_metadata','-1','-fflags','+bitexact','-flags:v','+bitexact','-use_editlist','0',
         '-movflags','+faststart','-video_track_timescale',str(fps),'-bsf:v','filter_units=remove_types=6']
    if extra:cmd+=extra
    result=subprocess.run(cmd+['-y',str(file)],capture_output=True,timeout=20)
    if result.returncode:raise RuntimeError('Fixture encoding failed: '+name)
    return file.read_bytes()

with tempfile.TemporaryDirectory(prefix='phpthis-mp4-seeds-') as directory:
    HERE=Path(directory)
    values={
        'valid-320x240':encode('valid-320x240'),
        'minimum-frame':encode('minimum-frame',2,2,1,1),
        'max-dimensions-duration-frames':encode('max-dimensions-duration-frames',640,480,30,300,extra=['-crf','30']),
        '24fps-single-frame':encode('24fps-single-frame',fps=24,frames=1),
        '25fps':encode('25fps',fps=25,frames=25),
        'duration-excess':encode('duration-excess',fps=1,frames=11),
        'frame-rate-excess':encode('frame-rate-excess',fps=31,frames=31),
        'width-excess':encode('width-excess',width=642),
        'height-excess':encode('height-excess',height=482),
        'high-profile':encode('high-profile',profile='high'),
        'fragmented':encode('fragmented',extra=['-movflags','+frag_keyframe+empty_moov']),
        'title-metadata':encode('title-metadata',extra=['-metadata','title=synthetic']),
        'b-frames':encode('b-frames',profile='main',extra=['-bf','2']),
        'seed':encode('seed',extra=['-bsf:v','null']),
    }
    rows={name:{'sha256':hashlib.sha256(data).hexdigest(),'base64':base64.b64encode(data).decode()} for name,data in values.items()}
    Path(__file__).with_name('mp4-fixtures.json').write_text(json.dumps(rows,indent=2)+'\n')
