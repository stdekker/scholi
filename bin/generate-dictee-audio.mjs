#!/usr/bin/env node
/**
 * Makes the recordings that dictee speaks: one mp3 per word of src/Game/dictee_words.json, in
 * assets/audio/taal/dictee/<word>.mp3, using ElevenLabs text-to-speech.
 *
 * Dev tooling only. The recordings are committed and served by this site, so the browser never
 * contacts ElevenLabs and production needs no API key. Run it after adding words:
 *
 *   ddev npm run audio:dictee            only the words without a recording yet
 *   ddev npm run audio:dictee -- --force every word again (e.g. after changing the voice)
 *
 * The API key is read from ELEVENLABS_API_KEY (npm loads it from .env.local). Optional:
 * ELEVENLABS_VOICE_ID, ELEVENLABS_MODEL_ID, ELEVENLABS_SPEED (not for Eleven v4) and
 * ELEVENLABS_STABILITY.
 *
 * Every word is asked in Dutch (language_code "nl") with the highest stability, so all words
 * sound the same. The API has no volume setting, so the script asks for raw audio, brings every
 * word to the same loudness without clipping and encodes the mp3 itself (no ffmpeg needed).
 */
import { ElevenLabsClient } from '@elevenlabs/elevenlabs-js';
import { Mp3Encoder } from '@breezystack/lamejs';
import { existsSync } from 'node:fs';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const wordsFile = path.join(root, 'src/Game/dictee_words.json');
const outputDir = path.join(root, 'assets/audio/taal/dictee');

const voiceId = process.env.ELEVENLABS_VOICE_ID ?? '7qdUFMklKPaaAVMsBTBt';
// Eleven v4 turbo takes a language code (Dutch) but no speed. Flash v2.5 takes both, multilingual
// v2 takes no language code.
const modelId = process.env.ELEVENLABS_MODEL_ID ?? 'eleven_v4_turbo';
// Models before v4 only: 0.7 is the slowest the API allows, 1 is normal speed.
const speed = Number(process.env.ELEVENLABS_SPEED ?? 0.7);
const isV4 = modelId.startsWith('eleven_v4');
// 1 is the steadiest delivery: a word sounds like the same voice every time.
const stability = Number(process.env.ELEVENLABS_STABILITY ?? 1);

const SAMPLE_RATE = 24_000;
const MP3_KBPS = 64;
// Loudness of the speech itself (RMS of everything above the noise floor), in dB below full scale.
const TARGET_RMS_DB = -16;
// Never louder than this peak, so nothing clips.
const PEAK_LIMIT = 0.97;
// Samples below this (about -40 dB) count as silence, not as speech, when measuring loudness.
const SILENCE = 0.01;
// Never amplify more than 20 dB: that would mostly raise noise.
const MAX_GAIN = 10;

const force = process.argv.includes('--force');

if (!process.env.ELEVENLABS_API_KEY) {
    console.error('ELEVENLABS_API_KEY is not set. Run this with `ddev npm run audio:dictee`.');
    process.exit(1);
}

/**
 * Brings speech to TARGET_RMS_DB, as far as the peak limit allows.
 *
 * @param {Int16Array} samples 16-bit mono audio
 * @returns {{samples: Int16Array, gainDb: number}}
 */
function louder(samples) {
    let peak = 0;
    let sum = 0;
    let count = 0;
    for (const sample of samples) {
        const value = Math.abs(sample) / 32768;
        peak = Math.max(peak, value);
        if (value > SILENCE) {
            sum += value * value;
            count++;
        }
    }
    if (count === 0) {
        return { samples, gainDb: 0 };
    }

    const wanted = 10 ** (TARGET_RMS_DB / 20) / Math.sqrt(sum / count);
    const gain = Math.min(wanted, PEAK_LIMIT / peak, MAX_GAIN);

    return {
        samples: samples.map((sample) =>
            Math.max(-32768, Math.min(32767, Math.round(sample * gain))),
        ),
        gainDb: 20 * Math.log10(gain),
    };
}

/**
 * @param {Int16Array} samples 16-bit mono audio at SAMPLE_RATE
 * @returns {Buffer}
 */
function toMp3(samples) {
    const encoder = new Mp3Encoder(1, SAMPLE_RATE, MP3_KBPS);
    const parts = [];
    for (let start = 0; start < samples.length; start += 1152) {
        parts.push(encoder.encodeBuffer(samples.subarray(start, start + 1152)));
    }
    parts.push(encoder.flush());

    return Buffer.concat(parts.map((part) => Buffer.from(part)));
}

const lists = JSON.parse(await readFile(wordsFile, 'utf8'));
const words = [...new Set(Object.values(lists).flat())];
const invalid = words.filter((word) => !/^[a-z]+$/.test(word));
if (invalid.length > 0) {
    console.error(`Words must be lowercase letters a-z only: ${invalid.join(', ')}`);
    process.exit(1);
}

await mkdir(outputDir, { recursive: true });

const client = new ElevenLabsClient();
let made = 0;

for (const word of words) {
    const file = path.join(outputDir, `${word}.mp3`);
    if (!force && existsSync(file)) {
        continue;
    }

    try {
        const audio = await client.textToSpeech.convert(voiceId, {
            text: word,
            modelId,
            // Without it the model has to guess the language of a single word.
            ...(modelId === 'eleven_multilingual_v2' ? {} : { languageCode: 'nl' }),
            // Raw 16-bit mono audio, so it is only compressed once (below).
            outputFormat: 'pcm_24000',
            // Eleven v4 only knows stability and similarity boost.
            voiceSettings: isV4
                ? { stability, similarityBoost: 0.75 }
                : { stability, similarityBoost: 0.75, useSpeakerBoost: true, speed },
        });
        // convert() returns a web ReadableStream.
        const bytes = await new Response(audio).arrayBuffer();
        const { samples, gainDb } = louder(
            new Int16Array(bytes, 0, Math.floor(bytes.byteLength / 2)),
        );
        await writeFile(file, toMp3(samples));
        made++;
        console.log(`made ${word}.mp3 (${gainDb >= 0 ? '+' : ''}${gainDb.toFixed(1)} dB)`);
    } catch (error) {
        // Stop at the first failure (wrong key, quota, bad voice): a rerun continues where this stopped.
        console.error(
            `Could not make ${word}.mp3: ${error instanceof Error ? error.message : error}`,
        );
        process.exit(1);
    }
}

console.log(`${made} recording(s) made, ${words.length - made} already there.`);
