import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

import * as webgpu from '../../resources/js/vendor-stubs/three-webgpu';
import * as tsl from '../../resources/js/vendor-stubs/three-tsl';
import * as h3 from '../../resources/js/vendor-stubs/h3-js';

/*
 * vite.config.js sustituye three/webgpu, three/tsl y h3-js por estos módulos.
 * Si una versión nueva de three-globe importa otros nombres, el build seguiría
 * pasando y el globo fallaría en el navegador: aquí se detecta antes.
 */
const source = readFileSync(resolve(process.cwd(), 'node_modules/three-globe/dist/three-globe.mjs'), 'utf8');

const namedImports = (module: string): string[] => {
    const match = source.match(new RegExp(`import \\{([^}]*)\\} from '${module.replace('/', '\\/')}';`));

    return match ? match[1].split(',').map((name) => name.trim()).filter(Boolean) : [];
};

// `import * as tsl from 'three/tsl'` y luego `const { Fn, If, ... } = tsl;`.
const tslNames = (): string[] =>
    [...source.matchAll(/const \{([^}]*)\} = tsl;/g)]
        .flatMap((match) => match[1].split(','))
        .map((name) => name.trim())
        .filter(Boolean);

describe('stubs of modules three-globe imports but the globe does not use', () => {
    it('export everything three-globe imports from them', () => {
        const webgpuNames = namedImports('three/webgpu');
        const h3Names = namedImports('h3-js');

        expect(webgpuNames.length).toBeGreaterThan(0);
        expect(h3Names.length).toBeGreaterThan(0);
        expect(tslNames().length).toBeGreaterThan(0);

        expect(Object.keys(webgpu)).toEqual(expect.arrayContaining(webgpuNames));
        expect(Object.keys(h3)).toEqual(expect.arrayContaining(h3Names));
        expect(Object.keys(tsl)).toEqual(expect.arrayContaining(tslNames()));
    });

    it('is only reached through those imports', () => {
        // Cualquier otra forma de usarlos (tsl.algo, otro import) habría que revisarla a mano.
        expect(source.match(/from 'three\/(webgpu|tsl)'/g)).toHaveLength(2);
        expect(source.match(/from 'h3-js'/g)).toHaveLength(1);
        expect(source).not.toMatch(/\btsl\.[A-Za-z]/);
    });

    it('fail with a message that explains the stub', () => {
        expect(() => new webgpu.WebGPURenderer()).toThrow(/three\/webgpu .*empty stub/);
        expect(() => tsl.storage()).toThrow(/three\/tsl .*empty stub/);
        expect(() => h3.polygonToCells()).toThrow(/h3-js .*empty stub/);
    });
});
