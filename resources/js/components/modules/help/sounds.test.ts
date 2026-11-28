import { describe, expect, it } from 'vitest';
import { markSound } from './sounds';

describe('markSound', () => {
    it('puts the sign in bold wherever it is in the transcription', () => {
        expect(markSound('/kæt/', 'æ')).toBe('/k<b>æ</b>t/');
        expect(markSound('/ˈtiː.tʃər/', 'tʃ')).toBe('/ˈtiː.<b>tʃ</b>ər/');
        expect(markSound('/ˈsɪstə/', 's')).toBe('/ˈ<b>s</b>ɪ<b>s</b>tə/');
    });

    it('marks the stress and length signs too', () => {
        expect(markSound('/həʊˈtel/', 'ˈ')).toBe('/həʊ<b>ˈ</b>tel/');
        expect(markSound('/tiː/', 'ː')).toBe('/ti<b>ː</b>/');
    });

    it('leaves the transcription plain when the row explains several signs or a way of writing', () => {
        expect(markSound('/pɑːk/', 'p t k')).toBe('/pɑːk/');
        expect(markSound('/həˈləʊ/', '/ /')).toBe('/həˈləʊ/');
        expect(markSound('/ˈwɜː.kər/', '…r')).toBe('/ˈwɜː.kər/');
    });

    it('leaves it plain when the sign is not there, and escapes what it shows', () => {
        expect(markSound('/kæt/', 'θ')).toBe('/kæt/');
        expect(markSound('/<i>/', 'i')).toBe('/&lt;<b>i</b>&gt;/');
    });
});
