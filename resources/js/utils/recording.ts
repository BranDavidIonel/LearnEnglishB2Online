// "Record yourself and listen back": works without any speech service.

import {
    claimMicrophone,
    isMobileDevice,
    microphoneInUse,
} from '@/utils/microphone';

// A recogniser that was just stopped needs a moment to give a phone's microphone back.
const HANDOVER_MS = 400;

/** True when this browser can record from the microphone. */
export function canRecord(): boolean {
    return (
        typeof navigator !== 'undefined' &&
        !!navigator.mediaDevices &&
        typeof window.MediaRecorder !== 'undefined'
    );
}

export interface Recording {
    /** Ends the recording: onDone() then receives the audio. */
    stop: () => void;
    /** Ends the recording and throws it away (the component is going away). */
    cancel: () => void;
}

/**
 * Opens the microphone and records until stop(). onDone(url) receives an object URL of the audio;
 * whoever keeps it must URL.revokeObjectURL() it when it is replaced or no longer shown.
 * Rejects when the microphone cannot be opened (blocked, missing). On a phone or tablet a
 * recogniser that is listening is stopped first (see utils/microphone.ts).
 */
export async function startRecording(
    onDone: (url: string) => void,
): Promise<Recording> {
    let stopNow = (): void => {};
    const phone = isMobileDevice();
    const handOver = phone && microphoneInUse();
    const release = claimMicrophone(() => stopNow());

    if (handOver) {
        await new Promise((resolve) => setTimeout(resolve, HANDOVER_MS));
    }

    let stream: MediaStream;

    try {
        stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    } catch (error) {
        release();
        throw error;
    }

    const recorder = new MediaRecorder(stream);
    const chunks: Blob[] = [];
    let keep = true;

    recorder.ondataavailable = (e) => chunks.push(e.data);
    recorder.onstop = () => {
        // Release the microphone: the browser stops showing it as in use.
        stream.getTracks().forEach((t) => t.stop());

        if (keep) {
            onDone(URL.createObjectURL(new Blob(chunks)));
        }
    };
    recorder.start();

    const stop = (): void => {
        if (recorder.state === 'recording') {
            recorder.stop();

            // A phone's microphone is given back at once, so a recogniser can start right away
            // (the recorded audio still arrives in onstop).
            if (phone) {
                stream.getTracks().forEach((t) => t.stop());
            }
        }

        release();
    };
    stopNow = stop;

    return {
        stop,
        cancel: () => {
            keep = false;
            stop();
        },
    };
}
