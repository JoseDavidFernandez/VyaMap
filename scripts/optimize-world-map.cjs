const fs = require('fs');

const inputPath = './resources/js/data/world.json';
const outputPath = './resources/js/data/world-optimized.json';

const isoOverrides = {
    France: 'FR',
    Norway: 'NO',
    Kosovo: 'XK',
};

const geoJson = JSON.parse(fs.readFileSync(inputPath, 'utf8'));

const optimized = {
    type: 'FeatureCollection',
    features: geoJson.features.map((feature) => {
        const name = feature.properties.NAME;

        return {
            type: 'Feature',
            properties: {
                ISO_A2: isoOverrides[name] ?? feature.properties.ISO_A2,
                NAME: name,
            },
            geometry: feature.geometry,
        };
    }),
};

fs.writeFileSync(
    outputPath,
    JSON.stringify(optimized),
);

console.log(
    `Optimized ${optimized.features.length} countries.`,
);

console.log(
    `Original: ${fs.statSync(inputPath).size} bytes`,
);

console.log(
    `Optimized: ${fs.statSync(outputPath).size} bytes`,
);