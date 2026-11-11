/**
 * Sustitutos vacíos de módulos que three-globe importa pero que el globo del
 * perfil no usa (alias en vite.config.js). Así no entran en el bundle:
 *
 * - three/webgpu y three/tsl: el renderer WebGPU, solo para la capa de heatmaps.
 * - h3-js: índices hexagonales, solo para las capas hexBin y hexPolygons.
 *
 * Si alguna vez se usan, fallan con un error que explica por qué. Al actualizar
 * three-globe hay que revisarlo (docs/architecture.md, «Globo del perfil»);
 * tests/js/vendorStubs.test.ts comprueba que exportan todo lo que importa.
 */
export const unavailable = (module: string, layers: string) => (): never => {
    throw new Error(
        `VyaMap: three-globe tried to use ${module} (${layers}), which vite.config.js replaces with an empty stub `
        + 'to keep it out of the bundle. Remove the alias to enable those layers (see docs/architecture.md, "Globo del perfil").',
    );
};

/** Clase que lanza el error al instanciarla. */
export const unavailableClass = (module: string, layers: string) => {
    const fail = unavailable(module, layers);

    return class {
        constructor() {
            fail();
        }
    };
};
