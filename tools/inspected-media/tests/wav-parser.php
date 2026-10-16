<?php

declare(strict_types=1);

use CatalogImageRecipe\BoundedWav;
use CatalogImageRecipe\RejectedWav;
use CatalogImageRecipe\InspectionOutcome;

function catalogWav(string $samples = "\0\0"): string
{
    return 'RIFF' . pack('V', strlen($samples) + 36) . 'WAVEfmt '
        . pack('VvvVVvv', 16, 1, 1, 16_000, 32_000, 2, 16)
        . 'data' . pack('V', strlen($samples)) . $samples;
}

function wavBody(string $chunks): string
{
    return 'RIFF' . pack('V', strlen($chunks) + 4) . 'WAVE' . $chunks;
}

/** @return list<array{string, string, InspectionOutcome}> */
function catalogWavCases(): array
{
    $one = catalogWav();
    $maximum = catalogWav(str_repeat("\x01\0", 160_000));
    $patterns = '';
    for ($value = 0; $value <= 65_535; $value++) { $patterns .= pack('v', $value); }
    $accepted = InspectionOutcome::Accepted;
    $rejected = InspectionOutcome::Rejected;
    $cases = [
        ['one_sample_silence', $one, $accepted],
        ['signed_extremes', catalogWav("\0\x80\xff\xff\0\0\xff\x7f"), $accepted],
        ['all_sample_bit_patterns', catalogWav($patterns), $accepted],
        ['one_second', catalogWav(str_repeat("\xff\x7f", 16_000)), $accepted],
        ['just_below_duration', catalogWav(str_repeat("\xff\xff", 159_999)), $accepted],
        ['ten_seconds', $maximum, $accepted],
        ['empty_input', '', $rejected], ['empty_data', catalogWav(''), $rejected],
        ['one_byte_sample', catalogWav('x'), $rejected], ['partial_final_sample', catalogWav('abc'), $rejected],
        ['one_extra_sample', catalogWav(str_repeat("\0\0", 160_001)), $rejected],
        ['one_mebibyte', $maximum . str_repeat("\0", 1_048_576 - strlen($maximum)), $rejected],
        ['over_shared_input_cap', str_repeat("\0", 1_048_577), $rejected],
        ['truncated_header', substr($one, 0, 43), $rejected], ['truncated_sample', substr($one, 0, -1), $rejected],
        ['truncated_maximum', substr($maximum, 0, -2), $rejected],
        ['trailing_byte', $one . "\0", $rejected], ['trailing_aligned_bytes', $one . "\0\0", $rejected],
        ['concatenated_files', $one . $one, $rejected],
    ];
    foreach ([
        ['rifx', 0, 'RIFX'], ['rf64', 0, 'RF64'], ['bw64', 0, 'BW64'], ['wave64', 0, 'riff'], ['avi_form', 8, 'AVI '],
        ['riff_zero', 4, pack('V', 0)], ['riff_maximum_claim', 4, str_repeat("\xff", 4)],
        ['riff_underreported', 4, pack('V', strlen($one) - 10)], ['riff_overreported', 4, pack('V', strlen($one) - 6)],
        ['fmt_zero', 16, pack('V', 0)], ['fmt_maximum_claim', 16, str_repeat("\xff", 4)],
        ['adpcm', 20, pack('v', 2)], ['float', 20, pack('v', 3)], ['alaw', 20, pack('v', 6)],
        ['mulaw', 20, pack('v', 7)], ['extensible', 20, pack('v', 65534)],
        ['zero_channels', 22, pack('v', 0)], ['stereo', 22, pack('v', 2)], ['excess_channels', 22, "\xff\xff"],
        ['zero_rate', 24, pack('V', 0)], ['rate_8k', 24, pack('V', 8000)], ['rate_48k', 24, pack('V', 48000)],
        ['excess_rate', 24, str_repeat("\xff", 4)], ['wrong_byte_rate', 28, pack('V', 16000)], ['excess_byte_rate', 28, str_repeat("\xff", 4)],
        ['zero_alignment', 32, pack('v', 0)], ['wrong_alignment', 32, pack('v', 4)],
        ['bits_8', 34, pack('v', 8)], ['bits_24', 34, pack('v', 24)], ['unknown_data_tag', 36, 'JUNK'],
        ['data_zero_claim', 40, pack('V', 0)], ['data_underreported', 40, pack('V', 1)],
        ['data_overreported', 40, pack('V', 4)], ['data_maximum_claim', 40, str_repeat("\xff", 4)],
    ] as [$name, $offset, $replacement]) {
        $cases[] = [$name, substr_replace($one, $replacement, $offset, strlen($replacement)), $rejected];
    }
    $fmt = substr($one, 12, 24); $data = substr($one, 36);
    $cases[] = ['data_before_fmt', wavBody($data . $fmt), $rejected];
    $cases[] = ['duplicate_fmt', wavBody($fmt . $fmt . $data), $rejected];
    $cases[] = ['duplicate_data', wavBody($fmt . $data . $data), $rejected];
    $cases[] = ['fmt_18_with_cbsize_zero', wavBody(substr_replace($fmt, pack('V', 18), 4, 4) . "\0\0" . $data), $rejected];
    $cases[] = ['info_metadata', wavBody($fmt . 'LIST' . pack('V', 4) . 'INFO' . $data), $rejected];
    $cases[] = ['bext_metadata', wavBody($fmt . 'bext' . pack('V', 2) . "\0\0" . $data), $rejected];
    $cases[] = ['fact_chunk', wavBody($fmt . 'fact' . pack('VV', 4, 1) . $data), $rejected];
    $cases[] = ['padded_odd_junk', wavBody($fmt . 'JUNK' . pack('V', 1) . "x\0" . $data), $rejected];
    $cases[] = ['nested_list', wavBody($fmt . 'LIST' . pack('V', strlen($data) + 4) . 'wavl' . $data), $rejected];
    $cases[] = ['extra_chunk_after_data', wavBody($fmt . $data . 'JUNK' . pack('V', 0)), $rejected];
    // Python wave writer: mono/16 kHz/16-bit, samples -1000, 0, 1000.
    $python = hex2bin('524946462a00000057415645666d74201000000001000100803e0000007d000002001000646174610600000018fc0000e803');
    if ($python === false) { throw new RuntimeException('WAV fixture unavailable.'); }
    $cases[] = ['python_wave_writer', $python, $accepted];
    return $cases;
}

function verifyWavParser(): void
{
    foreach (catalogWavCases() as [$name, $bytes, $expected]) {
        try {
            $wav = BoundedWav::fromBytes($bytes);
            requireCatalogProof($expected === InspectionOutcome::Accepted && $wav->sampleFrames === intdiv(strlen($bytes) - 44, 2));
        } catch (RejectedWav) {
            requireCatalogProof($expected === InspectionOutcome::Rejected);
        }
    }
}
