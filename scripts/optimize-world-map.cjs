const fs = require('fs');

const inputPath = './resources/js/data/world.json';
const outputPath = './resources/js/data/world-optimized.json';

const geoJson = JSON.parse(fs.readFileSync(inputPath, 'utf8'));

const optimized = {
    type: 'FeatureCollection',
    features: geoJson.features.map((feature) => ({
        type: 'Feature',
        properties: {
            ISO_A2: feature.properties.ISO_A2,
            NAME: feature.properties.NAME,
        },
        geometry: feature.geometry,
    })),
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