// Speech recognition (Chrome / Edge): one sentence with listen(), a continuous stream with listenStream().
// Chrome sends the audio to Google's online service; where it cannot be reached, both functions
// switch to Chrome's on-device recognition and remember that choice in this browser.

import { trans } from '@/i18n';
import { claimMicrophone, isMobileDevice } from '@/utils/microphone';
import { newAudioContext } from '@/utils/sound';

// ---------- the parts of the Web Speech API we use (not in TypeScript's DOM types) ----------

interface RecognitionResultEvent {
    readonly results: SpeechRecognitionResultList;
}

interface RecognitionErrorEvent {
    readonly error: string;
}

interface Recognizer {
    lang: string;
    interimResults: boolean;
    maxAlternatives: number;
    continuous: boolean;
    /** Newer Chrome: recognise on this computer instead of the online service. */
    processLocally?: boolean;
    onresult: ((event: RecognitionResultEvent) => void) | null;
    onerror: ((event: RecognitionErrorEvent) => void) | null;
    /** The recogniser has opened the microphone and listens. */
    onstart: (() => void) | null;
    onend: (() => void) | null;
    start(): void;
    stop(): void;
    abort(): void;
}

interface LocalOptions {
    langs: string[];
    processLocally: boolean;
}

interface RecognizerClass {
    new (): Recognizer;
    /** Newer Chrome: is the on-device language pack 'available', 'downloadable' … or 'unavailable'? */
    available?: (options: LocalOptions) => Promise<string>;
    /** Newer Chrome: downloads the on-device language pack. */
    install?: (options: LocalOptions) => Promise<boolean>;
}

declare global {
    interface Window {
        SpeechRecognition?: RecognizerClass;
        webkitSpeechRecognition?: RecognizerClass;
    }
}

const recognizerClass = (): RecognizerClass | undefined =>
    window.SpeechRecognition || window.webkitSpeechRecognition;

/** True when this browser can recognise speech. */
export function canListen(): boolean {
    return typeof window !== 'undefined' && !!recognizerClass();
}

// ---------- errors ----------

/** A failed recognition: `code` is the browser's error code, `text` explains it to the learner. */
export interface MicError extends Error {
    code: string;
    text: string;
}

export function isMicError(error: unknown): error is MicError {
    return error instanceof Error && 'code' in error && 'text' in error;
}

/** Codes after which trying again will not help: offer "record yourself" instead. */
export const MIC_FATAL = [
    'service-not-allowed',
    'network',
    'language-not-supported',
];

/** What the microphone heard, used to explain a "no-speech" error. */
interface NoSpeechInfo {
    peak: number;
    label: string;
    metered: boolean;
}

/** Where the browser keeps the microphone permission of this page. */
function micHelp(): string {
    const host = { host: window.location.host };

    return isMobileDevice()
        ? trans(
              'Tap the icon next to the address (":host") → Permissions → Microphone',
              host,
          )
        : trans(
              'Click the icon to the left of the address (next to ":host") → Microphone',
              host,
          );
}

/** The explanations that differ on a phone or tablet (no chrome:// pages, no VPN advice). */
function phoneErrorText(code: string): string | undefined {
    switch (code) {
        case 'audio-capture':
            return trans(
                "The phone's microphone could not be used. Close the apps that may be using it (a call, a voice recorder, another browser tab) and try again.",
            );
        case 'network':
            return trans(
                "The phone couldn't reach Google's speech recognition service. Check the internet connection and that the Google app is installed, enabled and up to date — or use the recording below.",
            );
        case 'service-not-allowed':
            return trans(
                "This browser doesn't allow speech recognition here. On a phone, use Google Chrome, with the Google app installed and up to date.",
            );
        case 'no-speech':
            return trans(
                'No English words were heard. Speak right after the beep, clearly and close to the phone.',
            );
        default:
            return undefined;
    }
}

function errorText(code: string): string | undefined {
    const phone = isMobileDevice() ? phoneErrorText(code) : undefined;

    if (phone) {
        return phone;
    }

    switch (code) {
        case 'not-allowed':
            return trans(
                'The microphone is blocked for this page. :help → Allow, then reload the page.',
                { help: micHelp() },
            );
        case 'service-not-allowed':
            return trans(
                "The browser doesn't allow speech recognition on this page. Use Google Chrome or Microsoft Edge.",
            );
        case 'audio-capture':
            return trans(
                "The browser can't find a microphone. Check which microphone is selected in chrome://settings/content/microphone.",
            );
        case 'network':
            return trans(
                "Your microphone works, but the browser couldn't reach the online speech recognition service (Chrome sends the audio to Google's servers), and on-device recognition isn't available in this browser. Common causes: a VPN, firewall or antivirus, an extension that blocks connections, or a Chromium-based browser without Google services (Brave, Opera, Vivaldi). Try Microsoft Edge, or turn off the VPN / extensions — or use the recording below.",
            );
        case 'aborted':
            return trans(
                'Listening was interrupted. Tap the microphone again.',
            );
        case 'language-not-supported':
            return trans(
                "This browser doesn't have speech recognition for English.",
            );
        case 'start-failed':
            return trans(
                "Speech recognition couldn't start. Reload the page and try again.",
            );
        default:
            return undefined;
    }
}

/** Nothing understood: tell a silent (or wrong) microphone apart from unclear speech. */
function noSpeechText(info: NoSpeechInfo | undefined): string {
    const device = info?.label ? ` („${info.label}”)` : '';

    if (info?.metered && info.peak < 0.02) {
        return trans(
            "The microphone the browser is listening to:device isn't picking up any sound. The browser is probably using a different microphone from the one you usually speak into: :help and choose the right one (or open chrome://settings/content/microphone).",
            { device, help: micHelp().toLowerCase() },
        );
    }

    return trans(
        'The microphone works:device, but no English words were heard. Speak right after you tap, clearly and close to the microphone.',
        { device },
    );
}

/** The error for a code, with the explanation for the learner in the interface language. */
export function micError(code: string, info?: NoSpeechInfo): MicError {
    const text =
        code === 'no-speech' && !isMobileDevice()
            ? noSpeechText(info)
            : errorText(code);

    return Object.assign(new Error(code), {
        code,
        text:
            text ||
            trans('Speech recognition returned an error (:code).', { code }),
    });
}

// ---------- online or on-device recognition ----------

type EngineMode = 'cloud' | 'local';

const STORAGE_KEY = 'el-stt';

/** Remembers whether speech is recognised by the online service ('cloud') or on this computer ('local'). */
function sttMode(set?: EngineMode): EngineMode {
    try {
        if (set) {
            localStorage.setItem(STORAGE_KEY, set);
        }

        return localStorage.getItem(STORAGE_KEY) === 'local'
            ? 'local'
            : 'cloud';
    } catch {
        return 'cloud';
    }
}

/** Which engine recognises speech here: 'cloud' (the browser's online service) or 'local' (this computer). */
export function speechEngine(): EngineMode {
    return sttMode();
}

/** True when on-device English recognition can be used; downloads the language pack the first time. */
async function localSpeechReady(
    Recognition: RecognizerClass,
    onInfo: (text: string) => void,
): Promise<boolean> {
    if (
        typeof Recognition.available !== 'function' ||
        typeof Recognition.install !== 'function'
    ) {
        return false;
    }

    const options: LocalOptions = { langs: ['en-US'], processLocally: true };

    try {
        const state = await Recognition.available(options);

        if (state === 'available') {
            return true;
        }

        if (state === 'unavailable') {
            return false;
        }

        onInfo(
            trans(
                "The online recognition service isn't responding. Downloading the English speech pack so it works directly on this computer (only once, it may take a minute)…",
            ),
        );

        return !!(await Recognition.install(options));
    } catch {
        return false;
    }
}

// ---------- one sentence ----------

/**
 * What listen() reports while it works:
 * 'ready' (the microphone's name), 'level' (loudness 0..1, many times a second),
 * 'interim' (the text heard so far), 'info' (a message while the on-device language pack downloads),
 * 'retry' (switched to on-device recognition: the learner must speak again).
 */
export type ListenUpdate =
    | [kind: 'ready', label: string]
    | [kind: 'level', loudness: number]
    | [kind: 'interim', text: string]
    | [kind: 'info', message: string]
    | [kind: 'retry', label: string];

export interface Listening {
    /** The alternatives understood, best first. Rejects with a MicError. */
    done: Promise<string[]>;
    /**
     * "I've finished speaking": recognition ends and `done` settles with what was heard so far.
     * Called before the microphone is ready, it cancels (`done` rejects with code 'aborted').
     */
    stop: () => void;
}

const LISTEN_LIMIT = 15000;

// getUserMedia() failures, mapped to the recognition error codes.
function openError(error: unknown): MicError {
    const name = error instanceof Error ? error.name : '';

    if (name === 'NotAllowedError' || name === 'SecurityError') {
        return micError('not-allowed');
    }

    if (name === 'NotFoundError' || name === 'NotReadableError') {
        return micError('audio-capture');
    }

    return micError('start-failed');
}

/**
 * Listens to one spoken sentence. The microphone, the level meter and the recogniser are all
 * released when `done` settles. Always call stop() when the component that started it unmounts.
 *
 * On a desktop the microphone is opened here first (clear permission errors, the level meter),
 * then the recogniser listens. On a phone or tablet the recogniser must have the microphone to
 * itself (see utils/microphone.ts): no level meter, errors come from the recogniser, and 'ready'
 * is reported when the recogniser has really started (after the permission prompt, if any).
 */
export function listen(
    onstate: (...update: ListenUpdate) => void = () => {},
): Listening {
    const phone = isMobileDevice();
    let release = (): void => {};
    let rec: Recognizer | null = null;
    let stream: MediaStream | null = null;
    let audio: AudioContext | null = null;
    let frame = 0;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let stopped = false;
    let peak = 0;
    let label = '';
    let metered = false;

    const cleanup = (): void => {
        release();
        cancelAnimationFrame(frame);
        clearTimeout(timer);
        stream?.getTracks().forEach((t) => t.stop());
        audio?.close().catch(() => {});
    };

    // A stop() that came while the microphone was still opening: do not start recognising.
    const checkStopped = (): void => {
        if (stopped) {
            throw micError('aborted');
        }
    };

    // Live loudness from the microphone, so a silent or wrong microphone can be told apart from
    // "nothing was understood".
    const meter = (source: MediaStream): void => {
        try {
            audio = newAudioContext();
            const analyser = audio.createAnalyser();
            analyser.fftSize = 512;
            audio.createMediaStreamSource(source).connect(analyser);
            const buffer = new Uint8Array(analyser.fftSize);
            const tick = (): void => {
                analyser.getByteTimeDomainData(buffer);
                let max = 0;

                for (const v of buffer) {
                    max = Math.max(max, Math.abs(v - 128));
                }

                peak = Math.max(peak, max / 128);
                onstate('level', max / 128);
                frame = requestAnimationFrame(tick);
            };
            metered = true;
            tick();
        } catch {
            // No meter: recognition still works.
        }
    };

    // Nobody speaks for ever: end the sentence after 15 seconds.
    const armLimit = (r: Recognizer): void => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            try {
                r.stop();
            } catch {
                // already stopped
            }
        }, LISTEN_LIMIT);
    };

    const recognise = (
        Recognition: RecognizerClass,
        local: boolean,
    ): Promise<string[]> =>
        new Promise((resolve, reject) => {
            const r = new Recognition();
            rec = r;
            r.lang = 'en-US';
            r.interimResults = true;
            r.maxAlternatives = 3;
            r.continuous = false;

            if (local) {
                r.processLocally = true;
            }

            let finals: string[] | null = null;
            let interim = '';
            let err: string | null = null;

            r.onresult = (e) => {
                const last = e.results[e.results.length - 1];

                if (last.isFinal) {
                    finals = Array.from(last, (a) => a.transcript);
                } else {
                    interim = Array.from(
                        e.results,
                        (x) => x[0].transcript,
                    ).join(' ');
                    onstate('interim', interim);
                }
            };
            r.onerror = (e) => {
                err = e.error;
            };

            // The 15 seconds count from the moment the phone listens, not from the permission prompt.
            if (phone) {
                r.onstart = () => {
                    armLimit(r);
                    onstate('ready', label);
                };
            }

            r.onend = () => {
                clearTimeout(timer);

                if (finals && finals.some((t) => t.trim())) {
                    resolve(finals);
                } else if (interim.trim()) {
                    resolve([interim]);
                } else {
                    reject(
                        micError(err || 'no-speech', { peak, label, metered }),
                    );
                }
            };

            try {
                r.start();
            } catch {
                reject(micError('start-failed'));
            }

            armLimit(r);
        });

    const run = async (): Promise<string[]> => {
        // Desktop: open the microphone ourselves first, for clear permission errors and the level
        // meter. Never on a phone: the recogniser would then hear nothing.
        if (!phone && navigator.mediaDevices?.getUserMedia) {
            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    audio: true,
                });
            } catch (error) {
                throw openError(error);
            }

            label = stream.getAudioTracks()[0]?.label || '';
            checkStopped();
            meter(stream);
        }

        const Recognition = recognizerClass();

        if (!Recognition) {
            throw micError('service-not-allowed');
        }

        const onInfo = (text: string): void => onstate('info', text);
        let local = sttMode() === 'local';

        if (local && !(await localSpeechReady(Recognition, onInfo))) {
            local = false;
        }

        checkStopped();

        if (phone) {
            // A recording in progress gives the microphone up first.
            release = claimMicrophone(stop);
        } else {
            onstate('ready', label);
        }

        try {
            return await recognise(Recognition, local);
        } catch (error) {
            const code = isMicError(error) ? error.code : '';

            if (local && code === 'language-not-supported') {
                sttMode('cloud');
            }

            if (
                local ||
                code !== 'network' ||
                !(await localSpeechReady(Recognition, onInfo))
            ) {
                throw error;
            }

            sttMode('local');
            checkStopped();
            onstate('retry', label);

            return recognise(Recognition, true);
        }
    };

    function stop(): void {
        stopped = true;

        try {
            rec?.stop();
        } catch {
            // already stopped
        }
    }

    const done = run();
    done.then(cleanup, cleanup);

    return { done, stop };
}

// ---------- continuous listening (games) ----------

/**
 * What listenStream() reports: 'ready' (listening), 'info' (a message while the on-device language
 * pack downloads), 'error' (listening stopped for good).
 */
export type StreamUpdate =
    | [kind: 'ready']
    | [kind: 'info', message: string]
    | [kind: 'error', error: MicError];

export interface ListeningStream {
    /** Stops listening for good. Call it when the component unmounts. */
    stop: () => void;
    /** Starts a fresh segment: onText() then receives only what is said from now on. */
    reset: () => void;
}

// Restarting a recogniser that keeps dying at once would loop for ever.
const QUICK_END_MS = 700;
const MAX_QUICK_ENDS = 6;

const flatText = (text: string): string =>
    text.trim().replace(/s+/g, ' ').toLowerCase();

/**
 * The text of a continuous recognition, from the transcripts of its results. Chrome for Android
 * delivers each result as final while holding the whole phrase so far ("one", "one two",
 * "one two three"): a result that repeats and extends the one before replaces it, and one that
 * only repeats (part of) it is dropped. Separate phrases are joined, as on a desktop.
 */
export function streamTranscript(transcripts: string[]): string {
    const kept: string[] = [];

    for (const transcript of transcripts) {
        const text = transcript.trim();
        const last = kept.length ? flatText(kept[kept.length - 1]) : '';
        const now = flatText(text);

        if (!now) {
            continue;
        }

        if (last && now.startsWith(last)) {
            kept[kept.length - 1] = text;
        } else if (!last || !last.startsWith(now)) {
            kept.push(text);
        }
    }

    return kept.join(' ');
}

/**
 * Keeps listening until stop() is called. onText(text) receives everything heard since the last
 * reset(); call reset() after using it. On a phone or tablet the growing results of Android are
 * merged (streamTranscript) and a recording in progress gives the microphone up first.
 */
export function listenStream(
    onText: (text: string) => void,
    onstate: (...update: StreamUpdate) => void = () => {},
): ListeningStream {
    const Recognition = recognizerClass();
    const phone = isMobileDevice();
    let rec: Recognizer | null = null;
    let active = true;
    let local = sttMode() === 'local';
    let triedLocal = false;
    let quickEnds = 0;
    let restart: ReturnType<typeof setTimeout> | undefined;
    const onInfo = (text: string): void => onstate('info', text);

    const fail = (code: string): void => {
        active = false;
        release();
        onstate('error', micError(code));
    };

    const later = (ms: number): void => {
        restart = setTimeout(start, ms);
    };

    // Chrome ends a "continuous" recognition after a pause anyway: start a new one each time.
    function start(): void {
        if (!active || !Recognition) {
            return;
        }

        const r = new Recognition();
        rec = r;
        r.lang = 'en-US';
        r.interimResults = true;
        r.continuous = true;
        r.maxAlternatives = 1;

        if (local) {
            r.processLocally = true;
        }

        let err: string | null = null;
        const began = Date.now();

        r.onresult = (e) => {
            quickEnds = 0;
            const transcripts = Array.from(e.results, (x) => x[0].transcript);
            onText(
                phone ? streamTranscript(transcripts) : transcripts.join(' '),
            );
        };
        r.onerror = (e) => {
            err = e.error;
        };
        r.onend = async () => {
            if (!active) {
                return;
            }

            if (err === 'network' && !local && !triedLocal) {
                triedLocal = true;

                if (await localSpeechReady(Recognition, onInfo)) {
                    local = true;
                    sttMode('local');

                    if (active) {
                        onstate('ready');
                    }
                }
            }

            if (!active) {
                return;
            }

            if (err === 'network' && !local) {
                return fail('network');
            }

            if (err && !['no-speech', 'aborted', 'network'].includes(err)) {
                return fail(err);
            }

            if (
                err !== 'aborted' &&
                Date.now() - began < QUICK_END_MS &&
                ++quickEnds > MAX_QUICK_ENDS
            ) {
                return fail(err || 'start-failed');
            }

            later(120);
        };

        try {
            r.start();
        } catch {
            later(400);
        }
    }

    const abort = (): void => {
        try {
            rec?.abort();
        } catch {
            // not running
        }
    };

    function stop(): void {
        active = false;
        release();
        clearTimeout(restart);
        abort();
    }

    const release = claimMicrophone(stop);

    void (async () => {
        if (!Recognition) {
            return fail('service-not-allowed');
        }

        if (local && !(await localSpeechReady(Recognition, onInfo))) {
            local = false;
        }

        if (!active) {
            return;
        }

        onstate('ready');
        start();
    })();

    return { stop, reset: abort };
}
