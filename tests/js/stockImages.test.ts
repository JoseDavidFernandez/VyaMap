import { existsSync, readFileSync, statSync } from 'node:fs';
import { resolve } from 'node:path';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import StockImage from '../../resources/js/components/ui/StockImage.vue';
import { stockImageFor, stockImages, stockSrc } from '../../resources/js/data/stockImages';

/**
 * Imágenes genéricas (resources/js/data/stockImages.ts): se sirven desde
 * public/images/stock, optimizadas y acreditadas en CREDITS.md.
 */
const publicDir = resolve(__dirname, '../../public');
const credits = readFileSync(resolve(publicDir, 'images/stock/CREDITS.md'), 'utf8');

describe('catálogo de imágenes genéricas', () => {
    it.each(stockImages.map((image) => [image.name, image]))('%s existe en los dos tamaños, optimizada y acreditada', (_name, image) => {
        for (const width of [800, 1600] as const) {
            const file = resolve(publicDir, stockSrc(image, width).slice(1));

            expect(existsSync(file)).toBe(true);
            expect(readFileSync(file).subarray(8, 12).toString()).toBe('WEBP');
            expect(statSync(file).size).toBeLessThan(300 * 1024);
        }

        expect(credits).toContain(`${image.name}-1600.webp`);
        expect(credits).toContain(image.author);
        expect(image.alt.length).toBeGreaterThan(10);
    });

    it('elige siempre la misma imagen para el mismo id', () => {
        expect(stockImageFor(386)).toBe(stockImageFor(386));
        expect(stockImageFor(0)).toBe(stockImages[0]);
        expect(stockImageFor(stockImages.length + 1)).toBe(stockImages[1]);
    });
});

describe('StockImage', () => {
    it('muestra solo la imagen, sin etiqueta de texto', () => {
        const wrapper = mount(StockImage, { props: { image: stockImages[0] } });
        const img = wrapper.get('img');

        expect(wrapper.text()).toBe('');
        expect(wrapper.findAll('.vyamap-stock > *')).toHaveLength(1);
        expect(img.attributes('src')).toBe(stockSrc(stockImages[0]));
        expect(img.attributes('srcset')).toContain('-1600.webp 1600w');
        expect(img.attributes('alt')).toBe(stockImages[0].alt);
        expect(img.attributes('loading')).toBe('lazy');
    });
});
