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
| `vyamap_airport_city_mapping.csv` | Aeropuertos y la ciudad a la que pertenecen | Preparado para VyaMap |
| `airports.csv` | Coordenadas de los aeropuertos | [OurAirports](https://ourairports.com/data/) (dominio público), reducido a los 3244 códigos IATA del mapeo; las filas no se modifican |
| `airport_timezones.csv` | Zona horaria IANA de cada aeropuerto | Preparado para VyaMap |

No contienen datos personales.
