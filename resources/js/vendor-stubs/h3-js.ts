import { unavailable } from './unavailable';

// Sustituye a 'h3-js' (ver unavailable.ts).
const fail = unavailable('h3-js', 'hexBin and hexPolygons layers');

export const latLngToCell = fail;
export const cellToLatLng = fail;
export const cellToBoundary = fail;
export const polygonToCells = fail;
