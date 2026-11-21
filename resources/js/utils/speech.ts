// Speech synthesis: reads English aloud with the voice and rate chosen in the settings.
// One utterance queue for the whole app: anything new cancels what is being read.

import type { ScriptLine } from '@/types';
import { learnerState } from '@/utils/learner';

export interface SpeakOptions {
    /** Reads at 60% of the normal rate (the 🐢 button of the listening exercises). */
    slow?: boolean;
    /** Called when the last part of the text has been read. */
    onend?: () => void;
}

// Chrome cuts off long utterances after about 15 seconds, so longer texts are read sentence by sentence.
const LONG_TEXT = 180;

// Voices we like best, in order: the natural (neural) American voices first.
const PREFERRED = [
    /natural.*(en-US|United States)/i,
    /Google US English/i,
    /natural/i,
    /Aria|Jenny|Guy/i,
    /Google UK English/i,
    /Zira|David|Mark/i,
];

let voices: SpeechSynthesisVoice[] = [];

// Changes whenever speech is started or stopped, so a script still being read knows it was cut off.
let scriptRun = 0;
// The pause before the next line of a script.
let scriptPause: ReturnType<typeof setTimeout> | undefined;

const synth = (): SpeechSynthesis | null =>
    typeof window !== 'undefined' && window.speechSynthesis
        ? window.speechSynthesis
        : null;

function loadVoices(): void {
    voices =
        synth()
            ?.getVoices()
            .filter((v) => /^en[-_]/i.test(v.lang)) ?? [];
}

// The browser fills the voice list asynchronously; keep it up to date.
const engine = synth();

if (engine) {
    loadVoices();
    engine.onvoiceschanged = loadVoices;
}

/** The English voices of this browser (for the settings page). */
export function englishVoices(): SpeechSynthesisVoice[] {
    return voices;
}

const rate = (): number => learnerState.settings.rate;

/** The voice chosen in the settings, else the best English voice available. */
function pickVoice(): SpeechSynthesisVoice | null {
    if (!voices.length) {
        loadVoices();
    }

    const chosen = learnerState.settings.voice;

    if (chosen) {
        const voice = voices.find((v) => v.name === chosen);

        if (voice) {
            return voice;
        }
    }

    for (const pattern of PREFERRED) {
        const voice = voices.find((v) => pattern.test(`${v.name} ${v.lang}`));

        if (voice) {
            return voice;
        }
    }

    return voices.find((v) => /en[-_]US/i.test(v.lang)) || voices[0] || null;
}

function useVoice(
    utterance: SpeechSynthesisUtterance,
    voice: SpeechSynthesisVoice | null,
): void {
    if (voice) {
        utterance.voice = voice;
        utterance.lang = voice.lang;
    } else {
        utterance.lang = 'en-US';
    }
}

/** Splits a long text into sentences (see LONG_TEXT). */
function parts(text: string): string[] {
    if (text.length <= LONG_TEXT) {
        return [text];
    }

    const sentences = text.match(/[^.!?\n]+[.!?]*["”')]*\s*/g) || [text];

    return sentences.map((s) => s.trim()).filter(Boolean);
}

/** Reads a text aloud (gaps "___" and markdown stars are skipped). Stops anything being read. */
export function speak(text: string, opts: SpeakOptions = {}): void {
    const speech = synth();

    if (!speech) {
        return;
    }

    scriptRun++;
    speech.cancel();

    const clean = String(text).replace(/_{3,}/g, ' ').replace(/\*\*/g, '');
    const pieces = parts(clean);
    const voice = pickVoice();

    pieces.forEach((piece, i) => {
        const utterance = new SpeechSynthesisUtterance(piece);
        useVoice(utterance, voice);
        utterance.rate = (opts.slow ? 0.6 : 1) * rate();

        if (opts.onend && i === pieces.length - 1) {
            utterance.onend = opts.onend;
        }

        speech.speak(utterance);
    });
}

/** Stops reading, a script included. The layout calls it on every page change. */
export function stopSpeak(): void {
    scriptRun++;
    clearTimeout(scriptPause);
    synth()?.cancel();
}

/**
 * Reads a script [{ s, en }] aloud line by line, giving each speaker a different voice (or, with
 * a single voice installed, a lower pitch). `onend` runs after the last line, unless stopped.
 */
export function speakScript(lines: ScriptLine[], onend?: () => void): void {
    const speech = synth();

    if (!speech) {
        onend?.();

        return;
    }

    stopSpeak();

    const run = ++scriptRun;
    const main = pickVoice();
    const others = voices.filter(
        (v) => v !== main && /en[-_](US|GB)/i.test(v.lang),
    );
    const speakers = [...new Set(lines.map((l) => l.s))];
    let i = 0;

    const next = (): void => {
        if (run !== scriptRun) {
            return;
        }

        if (i >= lines.length) {
            onend?.();

            return;
        }

        const line = lines[i++];
        const idx = speakers.indexOf(line.s);
        const utterance = new SpeechSynthesisUtterance(line.en);
        const voice =
            idx === 0
                ? main
                : others[(idx - 1) % Math.max(1, others.length)] || main;

        useVoice(utterance, voice);
        utterance.pitch = voice === main && idx > 0 ? 0.7 : 1;
        utterance.rate = rate();
        // A short pause between lines, like people taking turns.
        utterance.onend = () => {
            scriptPause = setTimeout(next, 280);
        };
        utterance.onerror = () => {
            scriptPause = setTimeout(next, 50);
        };
        speech.speak(utterance);
    };

    next();
}
