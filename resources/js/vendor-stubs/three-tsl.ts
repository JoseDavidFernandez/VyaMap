import { unavailable } from './unavailable';

// Sustituye a 'three/tsl' (ver unavailable.ts). Son los nombres que three-globe
// desestructura de `import * as tsl from 'three/tsl'`.
const fail = unavailable('three/tsl', 'heatmaps layer');

export const Fn = fail;
export const If = fail;
export const uniform = fail;
export const storage = fail;
export const float = fail;
export const instanceIndex = fail;
export const Loop = fail;
export const sqrt = fail;
export const sin = fail;
export const cos = fail;
export const asin = fail;
export const exp = fail;
export const negate = fail;
