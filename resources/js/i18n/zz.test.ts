import { expect, it } from 'vite-plus/test';
it('x', () => { const f = import.meta.glob('./ro/*.json', { eager: true }); expect(Object.keys(f)).toEqual([]); });
