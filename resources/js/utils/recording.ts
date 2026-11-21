// "Record yourself and listen back": works without any speech service.

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
 * Rejects when the microphone cannot be opened (blocked, missing).
 */
export async function startRecording(
    onDone: (url: string) => void,
): Promise<Recording> {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
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
        }
    };

    return {
        stop,
        cancel: () => {
            keep = false;
            stop();
        },
    };
}
