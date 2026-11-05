"""Lector mínimo de la capa de husos horarios de Natural Earth (shapefile + dbf).

Sin dependencias: lee los polígonos y devuelve el desfase estándar (campo
`zone`, en horas) de los husos que contienen un punto.
"""
import struct


def read_dbf(path):
    with open(path, 'rb') as handle:
        header = handle.read(32)
        count, header_length, record_length = struct.unpack('<xxxxIHH', header[:12])
        fields = []
        while True:
            descriptor = handle.read(32)
            if descriptor[0] == 0x0D:
                break
            fields.append((descriptor[:11].split(b'\0')[0].decode(), descriptor[16]))

        handle.seek(header_length)
        rows = []
        for _ in range(count):
            record = handle.read(record_length)[1:]
            row, position = {}, 0
            for name, length in fields:
                row[name] = record[position:position + length].decode('utf-8', 'replace').strip()
                position += length
            rows.append(row)
    return rows


def read_shp(path):
    """Polígonos: [(bbox, [anillo, ...]) o None], en el orden de los registros."""
    shapes = []
    with open(path, 'rb') as handle:
        handle.seek(100)
        while True:
            header = handle.read(8)
            if len(header) < 8:
                break
            content = handle.read(struct.unpack('>2i', header)[1] * 2)
            if struct.unpack('<i', content[:4])[0] == 0:
                shapes.append(None)
                continue
            bbox = struct.unpack('<4d', content[4:36])
            part_count, point_count = struct.unpack('<2i', content[36:44])
            parts = list(struct.unpack(f'<{part_count}i', content[44:44 + 4 * part_count])) + [point_count]
            start = 44 + 4 * part_count
            points = struct.unpack(f'<{2 * point_count}d', content[start:start + 16 * point_count])
            rings = [
                [(points[2 * i], points[2 * i + 1]) for i in range(parts[k], parts[k + 1])]
                for k in range(part_count)
            ]
            shapes.append((bbox, rings))
    return shapes


def inside(x, y, rings):
    """Par-impar sobre todos los anillos (los huecos quedan fuera)."""
    result = False
    for ring in rings:
        j = len(ring) - 1
        for i in range(len(ring)):
            xi, yi = ring[i]
            xj, yj = ring[j]
            if (yi > y) != (yj > y) and x < (xj - xi) * (y - yi) / (yj - yi) + xi:
                result = not result
            j = i
    return result


class NE:
    def __init__(self, base):
        self.rows = read_dbf(f'{base}.dbf')
        self.shapes = read_shp(f'{base}.shp')

    def offsets_at(self, lat, lon):
        offsets = []
        for row, shape in zip(self.rows, self.shapes):
            if not shape:
                continue
            (x0, y0, x1, y1), rings = shape
            if x0 <= lon <= x1 and y0 <= lat <= y1 and inside(lon, lat, rings):
                offsets.append(float(row['zone']))
        return offsets
