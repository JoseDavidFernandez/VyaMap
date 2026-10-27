# Catálogo de países, ciudades y aeropuertos

Datos para crear el catálogo en una instalación nueva (desarrollo o producción). Los comandos de importación los leen de `storage/app/data/`, así que primero se copian allí:

```bash
mkdir -p storage/app/data
cp database/data/catalog/*.csv storage/app/data/

php artisan countries:import --apply
php artisan cities:import
php artisan app:import-airports
php artisan airports:map-cities --apply
php artisan airports:enrich-coordinates --apply
php artisan airports:import-timezones --apply
```

El orden importa: cada ciudad pertenece a un país, y los tres últimos comandos trabajan sobre aeropuertos y ciudades que ya existen. `tests/Feature/CatalogDataImportTest.php` comprueba que estos archivos crean el catálogo completo: 251 países, más de 3000 ciudades y 3244 aeropuertos con ciudad, coordenadas y zona horaria.

| Archivo | Contenido | Origen |
|---|---|---|
| `countries.csv` | Países y continentes | [OurAirports](https://ourairports.com/data/) (dominio público) |
| `countries_overrides.csv` | Dos territorios que faltan en `countries.csv` (AX, SJ) | Preparado para VyaMap |
| `vyamap_airport_city_mapping.csv` | Aeropuertos y la ciudad a la que pertenecen | Preparado para VyaMap. **Procedencia sin verificar** (ver abajo) |
| `airports.csv` | Coordenadas de los aeropuertos | [OurAirports](https://ourairports.com/data/) (dominio público), reducido a los 3244 códigos IATA del mapeo; las filas no se modifican |
| `airport_timezones.csv` | Zona horaria IANA de cada aeropuerto | Preparado para VyaMap (`timezone_status=calculated`). **Procedencia sin verificar** (ver abajo) |

No contienen datos personales.

**Procedencia de los dos archivos «Preparado para VyaMap» (revisión del 26-10-2026).** No hay ningún script, documento ni commit que explique cómo se generaron, así que su origen **no está acreditado**. Comparados con OurAirports (dominio público):

- Coinciden con OurAirports el nombre del aeropuerto (3244 de 3244), el ICAO (3239), el país (3238) y, en `airport_timezones.csv`, las coordenadas del aeropuerto (3244).
- **Sin fuente identificada:** la ciudad asignada a cada aeropuerto (en 822 no coincide con el `municipality` de OurAirports: es la ciudad a la que sirve, como NRT → Tokyo o BVA → Paris), las coordenadas de esas ciudades y el cálculo de las zonas horarias (`timezone_status=calculated`).

Se probó a regenerarlos desde OurAirports y GeoNames (CC BY 4.0): las zonas horarias coinciden en 3215 de 3244 aeropuertos, pero la ciudad pasa a ser la del emplazamiento (Narita, Beauvais, Sepang…) y la búsqueda de aeropuertos por ciudad empeora. **No se han sustituido.** Hasta resolverlo (confirmar cómo se prepararon o regenerarlos con una fuente de «ciudad servida» con licencia conocida, por ejemplo Wikidata, CC0), no se puede declarar una licencia que los cubra.
