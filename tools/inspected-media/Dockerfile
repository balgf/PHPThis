FROM php:8.4.26-cli-bookworm@sha256:836ac6c672d1372a47c8fd61b625015bd07760f493fb2eaab2087636498a2b4b AS build
ADD --checksum=sha256:bdf16a7a2d94aa5b8f479d3d6651e93700adda2461b27680fd226161602ffc81 https://codeload.github.com/pnggroup/pngcheck/tar.gz/861c978c3b1bac93659202fd25b9b76bc852eee1 /build/pngcheck.tar.gz
ADD --checksum=sha256:bb329a0a2cd0274d05519d61c667c062e06990d72e125ee2dfa8de64f0119d16 https://github.com/madler/zlib/releases/download/v1.3.2/zlib-1.3.2.tar.gz /build/zlib.tar.gz
ADD --checksum=sha256:6f30092cef9fb839779646608f4ee14ae3cbac989c47fa05e841b0841f09878e https://github.com/libjpeg-turbo/libjpeg-turbo/releases/download/3.2.0/libjpeg-turbo-3.2.0.tar.gz /build/jpeg.tar.gz
RUN apt-get update && apt-get install -y --no-install-recommends cmake=3.25.1-1 && rm -rf /var/lib/apt/lists/*
WORKDIR /build
RUN --network=none tar -xzf zlib.tar.gz && tar -xzf pngcheck.tar.gz \
 && cd zlib-1.3.2 && ./configure --prefix=/opt/png-zlib && make -j2 && make install \
 && cd .. && cc -O2 -fstack-protector-strong -D_FORTIFY_SOURCE=2 -Wl,-z,relro,-z,now \
    -I/build/zlib-1.3.2 pngcheck-861c978c3b1bac93659202fd25b9b76bc852eee1/pngcheck.c \
    /build/zlib-1.3.2/libz.a -o /build/pngcheck
RUN --network=none tar -xzf jpeg.tar.gz \
 && cmake -S libjpeg-turbo-3.2.0 -B jpeg-build -DCMAKE_BUILD_TYPE=Release \
    -DENABLE_SHARED=OFF -DENABLE_STATIC=ON -DWITH_SIMD=OFF -DWITH_TURBOJPEG=OFF \
    -DWITH_JNA=OFF -DWITH_ARITH_DEC=OFF -DWITH_ARITH_ENC=OFF \
 && cmake --build jpeg-build --parallel 2 \
 && cd jpeg-build && ctest --output-on-failure

FROM php:8.4.26-cli-bookworm@sha256:836ac6c672d1372a47c8fd61b625015bd07760f493fb2eaab2087636498a2b4b AS mp4-build
ADD --checksum=sha256:8c3850283eb25fa026482078a04051e0be17347b09ef81a0849bec15a96e002e https://ffmpeg.org/releases/ffmpeg-9.0.2.tar.xz /build/ffmpeg.tar.xz
WORKDIR /build
RUN --network=none tar -xJf ffmpeg.tar.xz && cd ffmpeg-9.0.2 \
 && ./configure --disable-everything --disable-autodetect --disable-network --disable-doc --disable-debug --disable-asm \
    --disable-programs --enable-ffprobe --disable-avdevice --disable-avfilter --disable-swscale --disable-swresample \
    --disable-pthreads --disable-w32threads --disable-os2threads \
    --enable-decoder=h264 --enable-parser=h264 --enable-demuxer=mov --enable-protocol=pipe \
 && make -j2 ffprobe

FROM php:8.4.26-cli-bookworm@sha256:836ac6c672d1372a47c8fd61b625015bd07760f493fb2eaab2087636498a2b4b
COPY --from=mp4-build /build/ffmpeg-9.0.2/ffprobe /usr/local/bin/ffprobe
COPY --from=mp4-build /build/ffmpeg-9.0.2/COPYING.LGPLv2.1 /usr/local/share/ffmpeg/COPYING.LGPLv2.1
COPY --from=build /build/pngcheck /usr/local/bin/pngcheck
COPY --from=build /build/jpeg-build/djpeg-static /usr/local/bin/djpeg
COPY --from=build /opt/png-zlib/lib/libz.so.1.3.2 /opt/png-zlib/lib/libz.so.1
ENV LD_LIBRARY_PATH=/opt/png-zlib/lib
COPY src/Failures.php src/PngHeader.php src/BoundedPng.php /inspector/src/
COPY src/BoundedJpeg.php src/CatalogImageFormat.php /inspector/src/
COPY src/Mp4Structure.php src/BoundedMp4.php src/Mp4Decoder.php /inspector/src/
COPY src/BoundedWav.php /inspector/src/
COPY inspector.php /inspector/inspector.php
USER 65534:65534
ENTRYPOINT ["php", "-n", "-d", "memory_limit=32M", "-d", "display_errors=0", "-d", "log_errors=0", "/inspector/inspector.php"]
