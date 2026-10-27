/**
 * Imágenes genéricas de paisajes para tarjetas y estados vacíos. No son fotos
 * de los viajes del usuario: siempre se muestran con la etiqueta
 * «Illustrative image» (components/ui/StockImage.vue).
 *
 * Todas son CC0 (dominio público): fotos publicadas en Unsplash bajo CC0 y
 * conservadas en Wikimedia Commons. Se sirven desde public/images/stock en
 * WebP de 800 y 1600 px, sin metadatos. Créditos: public/images/stock/CREDITS.md.
 */
export interface StockImage {
    name: string;
    alt: string;
    author: string;
}

export const stockImages: StockImage[] = [
    { name: 'mountain-lake', alt: 'Turquoise lake between forested mountains', author: 'Sergei Akulich' },
    { name: 'seaside-cliffs', alt: 'Cliffs above a deep blue sea', author: 'Josh Aarons' },
    { name: 'shoreline-sunset', alt: 'Rocky shoreline at sunset', author: 'Ganapathy Kumar' },
    { name: 'desert-dunes', alt: 'Curving sand dunes', author: 'Lee Miller' },
    { name: 'conifer-forest', alt: 'Edge of a conifer forest under a grey sky', author: 'Ales Krivec' },
    { name: 'wing-above-clouds', alt: 'Airplane wing above the clouds', author: 'Brian Gaid' },
    { name: 'snowy-peaks', alt: 'Snow-capped peaks in the fog', author: 'Tj Holowaychuk' },
    { name: 'open-road', alt: 'Straight road towards distant mountains', author: 'Pablo Garcia Saldaña' },
];

export const stockImage = (name: string): StockImage =>
    stockImages.find((image) => image.name === name) ?? stockImages[0];

// Imagen estable para un registro: el mismo viaje siempre muestra la misma.
export const stockImageFor = (id: number): StockImage => stockImages[Math.abs(id) % stockImages.length];

export const stockSrc = (image: StockImage, width: 800 | 1600 = 800): string => `/images/stock/${image.name}-${width}.webp`;

export const stockSrcSet = (image: StockImage): string =>
    `${stockSrc(image, 800)} 800w, ${stockSrc(image, 1600)} 1600w`;

export const stockCredit = (image: StockImage): string =>
    `Illustrative image, not from your trips. Photo: ${image.author}, CC0 (Unsplash via Wikimedia Commons).`;
