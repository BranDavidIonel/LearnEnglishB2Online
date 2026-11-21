// Short sound effects, synthesised on the fly (no audio files). Muted when the settings say so.

import { learnerState } from '@/utils/learner';

export type SoundName = 'ok' | 'bad' | 'win' | 'flip';

declare global {
    interface Window {
        /** Older Safari. */
        webkitAudioContext?: typeof AudioContext;
    }
}

/** A new audio context (the caller closes it when done). */
export function newAudioContext(): AudioContext {
    const Context = window.AudioContext || window.webkitAudioContext;

    return new Context();
}

// One context for all the effects of the page: browsers limit how many can be open.
let context: AudioContext | null = null;

function tone(
    audio: AudioContext,
    freq: number,
    start: number,
    duration: number,
    type: OscillatorType = 'sine',
    volume = 0.12,
): void {
    const oscillator = audio.createOscillator();
    const gain = audio.createGain();
    const at = audio.currentTime + start;

    oscillator.type = type;
    oscillator.frequency.value = freq;
    gain.gain.setValueAtTime(volume, at);
    gain.gain.exponentialRampToValueAtTime(0.0001, at + duration);
    oscillator.connect(gain).connect(audio.destination);
    // The oscillator stops itself: nothing is left playing.
    oscillator.start(at);
    oscillator.stop(at + duration + 0.02);
}

/** Plays a sound effect: 'ok' (right answer), 'bad' (wrong), 'win' (finished), 'flip' (card). */
export function sfx(name: SoundName): void {
    if (!learnerState.settings.sound) {
        return;
    }

    try {
        const audio = (context ??= newAudioContext());

        if (name === 'ok') {
            tone(audio, 660, 0, 0.12);
            tone(audio, 990, 0.1, 0.18);
        } else if (name === 'bad') {
            tone(audio, 220, 0, 0.22, 'triangle', 0.14);
        } else if (name === 'win') {
            [523, 659, 784, 1047].forEach((f, i) =>
                tone(audio, f, i * 0.11, 0.25),
            );
        } else {
            tone(audio, 440, 0, 0.05, 'sine', 0.05);
        }
    } catch {
        // No audio in this browser: the app works silently.
    }
}
