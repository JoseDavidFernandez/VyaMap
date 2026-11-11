import { feature } from 'topojson-client';
import type { Topology, GeometryCollection } from 'topojson-specification';
import type { FeatureCollection, Geometry } from 'geojson';

/**
 * Polígonos de los países (Natural Earth) para el mapa de países de Leaflet y
 * el globo del perfil.
 *
 * public/data/world.topo.json se genera desde resources/data/world.geo.json
 * con topojson-server (comando en el README, «Mapa de países»): las fronteras
 * compartidas se guardan una vez y las coordenadas se cuantizan a una rejilla
 * de 10 000 × 10 000 (unos 0,036°). En el mapa de países (zoom máximo 3) y en
 * el globo el desplazamiento no llega a medio píxel. Pesa unos 130 KB con
 * gzip frente a los 810 KB del GeoJSON.
 */
export interface WorldCountryProperties {
    ISO_A2: string;
    NAME: string;
}

export type WorldCountries = FeatureCollection<Geometry, WorldCountryProperties>;

type WorldTopology = Topology<{
    countries: GeometryCollection<WorldCountryProperties>;
}>;

let topology: Promise<WorldTopology> | null = null;

const fetchTopology = async (): Promise<WorldTopology> => {
    const response = await fetch('/data/world.topo.json');

    if (!response.ok) {
        throw new Error('Unable to load world map data.');
    }

    return response.json();
};

/**
 * Devuelve una FeatureCollection nueva en cada llamada (Leaflet y three-globe
 * guardan referencias en los objetos que reciben); el TopoJSON se descarga una
 * sola vez por carga de la aplicación.
 */
export const loadWorldCountries = async (): Promise<WorldCountries> => {
    topology ??= fetchTopology().catch((error) => {
        topology = null;
        throw error;
    });

    const world = await topology;

    return feature(world, world.objects.countries);
};
