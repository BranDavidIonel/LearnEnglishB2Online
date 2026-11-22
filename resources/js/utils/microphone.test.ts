import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { claimMicrophone, isMobileDevice, microphoneInUse } from './microphone';

const ANDROID_PHONE =
    'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36';
const ANDROID_TABLET =
    'Mozilla/5.0 (Linux; Android 13; SM-X710) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
const IPHONE =
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';
const MAC =
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15';
const WINDOWS =
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('isMobileDevice', () => {
    it('knows Android phones and tablets, iPhones and iPads', () => {
        expect(isMobileDevice({ userAgent: ANDROID_PHONE })).toBe(true);
        expect(isMobileDevice({ userAgent: ANDROID_TABLET })).toBe(true);
        expect(isMobileDevice({ userAgent: IPHONE })).toBe(true);
    });

    it('finds an iPad that calls itself a Mac by its touch screen', () => {
        expect(
            isMobileDevice({
                userAgent: MAC,
                platform: 'MacIntel',
                maxTouchPoints: 5,
            }),
        ).toBe(true);
        expect(
            isMobileDevice({
                userAgent: MAC,
                platform: 'MacIntel',
                maxTouchPoints: 0,
            }),
        ).toBe(false);
    });

    it('trusts the client hints of Chromium', () => {
        expect(
            isMobileDevice({
                userAgent: WINDOWS,
                userAgentData: { mobile: false, platform: 'Android' },
            }),
        ).toBe(true);
        expect(
            isMobileDevice({
                userAgent: 'Mozilla/5.0 (Linux; K)',
                userAgentData: { mobile: true, platform: 'Android' },
            }),
        ).toBe(true);
    });

    it('is false on desktops and without a navigator', () => {
        expect(
            isMobileDevice({
                userAgent: WINDOWS,
                userAgentData: { mobile: false, platform: 'Windows' },
            }),
        ).toBe(false);
        expect(isMobileDevice({ userAgent: MAC, platform: 'MacIntel' })).toBe(
            false,
        );
        expect(isMobileDevice(undefined)).toBe(false);
    });
});

describe('claimMicrophone', () => {
    it('stops the previous user on a phone', () => {
        vi.stubGlobal('navigator', { userAgent: ANDROID_PHONE });
        const first = vi.fn();
        const releaseFirst = claimMicrophone(first);
        expect(microphoneInUse()).toBe(true);

        const release = claimMicrophone(vi.fn());
        expect(first).toHaveBeenCalledTimes(1);

        // A late release of the first user does not free the second one's microphone.
        releaseFirst();
        expect(microphoneInUse()).toBe(true);
        release();
        expect(microphoneInUse()).toBe(false);
    });

    it('does nothing on a desktop', () => {
        vi.stubGlobal('navigator', { userAgent: WINDOWS });
        const first = vi.fn();
        claimMicrophone(first)();
        claimMicrophone(vi.fn());

        expect(first).not.toHaveBeenCalled();
        expect(microphoneInUse()).toBe(false);
    });
});
