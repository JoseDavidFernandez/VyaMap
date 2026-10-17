# VyaMap — Pruebas manuales

Lista práctica para comprobar VyaMap en el navegador antes de introducir viajes y vuelos reales. Está ordenada por prioridad: empieza por P1.

**Ninguna de estas pruebas necesita migraciones:** F3 y F4 no cambian el esquema.

## Entorno: dónde hacer las pruebas

Las pruebas crean, editan y borran datos a través de la aplicación, así que **escriben en la base de datos a la que apunte el servidor**. Elige uno de estos dos modos.

### Modo A, recomendado para estas pruebas: `vyamap_testing`, sin tocar `vyamap`

El servidor carga `.env.testing` (base `vyamap_testing`) y guarda la sesión en una cookie, sin dejar archivos en `storage/framework/sessions`.

```bash
# 1. Comprobar a qué base apunta el entorno de testing (debe decir vyamap_testing)
php artisan tinker --env=testing --execute='echo DB::connection()->getDatabaseName();'

# 2. Catálogo, en este orden (lee los CSV locales de storage/app/data; solo escribe en vyamap_testing)
php artisan countries:import --apply --env=testing
php artisan cities:import --env=testing
php artisan app:import-airports --env=testing
php artisan airports:map-cities --apply --env=testing
php artisan airports:enrich-coordinates --apply --env=testing
php artisan airports:import-timezones --apply --env=testing

# 3. Usuario de pruebas (pide la contraseña)
php artisan users:create "Demo" demo@example.test --env=testing

# 4. Servidores (en dos terminales)
npm run dev
SESSION_DRIVER=cookie CACHE_STORE=array php artisan serve --env=testing --port=8010
```

Abre `http://127.0.0.1:8010` e inicia sesión con el usuario de pruebas. En este modo no hay worker de cola, así que las fotos se quedan pendientes; las pruebas de fotos (P3) van mejor en el modo B.

**Buscador del itinerario y Geoapify:** el catálogo importado solo trae ciudades con aeropuerto. Para encontrar cualquier otra localidad (Ksamil, Bled, Sinaia…), `.env.testing` necesita `GEOAPIFY_API_KEY`: Laravel carga ese archivo en lugar de `.env` y no hereda su clave. Sin ella, el buscador solo devuelve ciudades del catálogo local y no avisa de ningún error. Comprobación (sin mostrar la clave):

```bash
php artisan tinker --env=testing --execute='echo blank(config("services.geoapify.key")) ? "geoapify: no configurada" : "geoapify: configurada";'
```

**Atención:** `php artisan test` vacía `vyamap_testing` (`RefreshDatabase`). Después de ejecutar la suite hay que repetir los pasos 2 y 3.

### Modo B: la aplicación normal (`vyamap`)

Sirve para introducir tus viajes reales. **Escribe en `vyamap`:** usa una cuenta de pruebas o anota lo que crees para borrarlo después. No hace falta ejecutar migraciones. Para las fotos, deja un worker en marcha (`composer run queue`).

**En los dos modos**
- Usa `npm run dev`, o `npm run build` si sirves los assets compilados (regenera `public/build`). Recarga con la caché vacía.
- Las reglas de referencia están en [`architecture.md`](architecture.md) (secciones 9, 15 y «Estadísticas de viaje»).

Leyenda del resultado: ✅ esperado · ⚠️ caso límite.

## P1 — Viajes

| # | Pasos | Datos de ejemplo | Resultado esperado |
|---|---|---|---|
| V1 | Crear un viaje pasado | «Lisboa», 01-05-2024 → 05-05-2024 | ✅ Se crea como **completado** (su fin ya pasó). Trips: 1 viaje, **5 días**. |
| V2 | Crear un viaje futuro | «Marrakech», 14-11-2026 → 18-11-2026 | ✅ Se crea como **planificado** (lima). No suma días, países ni viajes; Home muestra «+1 upcoming». |
| V3 | ⚠️ Viaje que cruza de año | 30-12-2025 → 02-01-2026 | ✅ 4 días: en Trips, **2 en 2025 y 2 en 2026**. El viaje cuenta y se lista en **2025** (año de inicio); al filtrar 2026 no aparece, pero sus 2 días sí suman. |
| V4 | ⚠️ Viaje de un día | 10-03-2026 → 10-03-2026 | ✅ 1 día. |
| V5 | ⚠️ Viajes solapados | 01-05-2026 → 10-05-2026 y 05-05-2026 → 12-05-2026, ambos completados | ✅ «Days» suma **12**, no 18: los días comunes cuentan una vez. |
| V6 | Fin anterior al inicio | 10-05-2026 → 01-05-2026 | ✅ Error de validación; no se guarda. |
| V7 | Transiciones de estado | En la ficha de un viaje planificado que ya empezó: «Mark as completed», «Cancel trip», «Restore trip» y «Mark as planned» | ✅ planificado → completado solo si ya empezó; planificado ↔ cancelado; completado → planificado. Ninguna otra transición se ofrece. |
| V8 | ⚠️ Completar antes de empezar | Viaje planificado que empieza mañana | ✅ No se puede marcar como completado. |
| V9 | Editar fechas dejando visitas fuera | Acortar un viaje con una visita en su último día | ✅ Error «visit falls outside the new dates»; no se guarda. |

## P1 — Visitas (ciudades y países)

| # | Pasos | Datos de ejemplo | Resultado esperado |
|---|---|---|---|
| C1 | Añadir una visita a un viaje completado | Lisboa, 01-05-2024 → 05-05-2024 | ✅ Portugal cuenta como país, en Home, Trips, Passport y Countries. |
| C2 | Visita fuera de las fechas del viaje | Lisboa, 06-05-2024 | ✅ Error de validación. |
| C3 | Visita en un viaje planificado | Marrakech en el viaje de V2 | ✅ Marruecos aparece en lima como planificado y **no** cuenta como visitado. |
| C4 | ⚠️ Territorio | Una ciudad de Gibraltar | ✅ Cuenta como «+1 territory», no entre los 193 países. |
| C5 | ⚠️ Primera visita | Visitar España en 2024 y otra vez en 2026 | ✅ Passport, «Countries discovered by year»: España solo en 2024. Al filtrar 2026, España cuenta entre los países de ese año, pero no como nuevo. |
| C6 | ⚠️ Vuelo sin visita | Solo un vuelo a Marrakech, sin visitas | ✅ Marruecos **no** cuenta como visitado. |
| C7 | Localidad que no está en el catálogo (necesita Geoapify) | Bled, Ksamil, Como, Sinaia | ✅ Aparece en el buscador; al añadirla se crea la ciudad con su país y coordenadas, y la visita. |
| C8 | ⚠️ Nombre que devuelve Geoapify | Ciudad del Vaticano | ✅ Aparece y se guarda como **«Vatican City»**. Geoapify manda `city = "yes"` y el buscador usa el siguiente nombre válido; un resultado sin ningún nombre válido no se ofrece. Una ciudad guardada antes de la corrección con un nombre así no se arregla volviéndola a añadir (se reutiliza por su identificador): hay que renombrarla. |
| C9 | ⚠️ Homónimos | Buscar «Peleș» o «Bran» | ⚠️ Puede haber localidades con el mismo nombre en otra región. Antes de añadir, comprueba el país, la región y, si aparecen, las coordenadas del resultado. |
| C10 | Lugar de interés (fuera de V1) | Castillo de Peleș, Castillo de Bran | ✅ No se busca como visita: añade su localidad (Sinaia, Bran) y menciona el castillo en las notas de la visita. |

## P1 — Vuelos

| # | Pasos | Datos de ejemplo | Resultado esperado |
|---|---|---|---|
| F1 | Vuelo con horas, dentro de un viaje | MAD → LIS, 01-05-2024, 08:00 → 08:25 | ✅ Duración **1 h 25 min** (Lisboa va una hora por detrás). Estado realizado si el viaje está completado. |
| F2 | ⚠️ Llegada al día siguiente | JFK → MAD el 19-06-2026, salida 19:00, llegada 08:00 con «+1 day» | ✅ Duración **7 h** (horario de verano en las dos ciudades). Al editarlo se ven las mismas horas y el «+1 day». |
| F3 | ⚠️ Vuelo sin horas | MAD → BCN, solo la fecha | ✅ Se guarda sin duración. En Flight History, «Time in air» indica «N of M flights with times». |
| F4 | ⚠️ Vuelo sin horas, hoy | Fecha de hoy, sin horas | ✅ **Upcoming** durante todo el día local; pasa a pendiente al día siguiente. |
| F5 | Vuelo independiente pasado | Sin viaje, fecha pasada | ✅ Queda **Unconfirmed**. No cuenta en las cifras hasta pulsar «Confirm flown». |
| F6 | Deshacer la confirmación | «Undo confirmation» en F5 | ✅ Vuelve a pendiente y deja de contar. |
| F7 | ⚠️ Mover al futuro un vuelo confirmado | Editar la fecha de F5 a una futura | ✅ Pierde la confirmación y pasa a próximo. |
| F8 | Escritura de horas | Escribir `0730`, `7:30` y `07.30` | ✅ Al salir del campo se normaliza a `07:30`. |
| F9 | ⚠️ Horas inválidas | `24:00`, `12:60`, `7:3`, `730` | ✅ Aviso «Use the 24-hour format HH:MM…»; ningún botón guarda. |
| F10 | Llegada sin salida | Solo la hora de llegada | ✅ Aviso, y al guardar, error en la llegada. |
| F11 | ⚠️ Hora inexistente por el cambio de horario | MAD, 29-03-2026 a las 02:30 | ✅ Error: esa hora no existe en Madrid. |
| F12 | ⚠️ Vuelo fuera de las fechas del viaje | Vuelo del viaje V1 el 06-05-2024 | ✅ Error en la fecha de salida. |
| F13 | Cambiar la salida varias veces | Editar F2 cambiando la salida tres veces | ✅ La llegada y su «+1 day» no cambian. |
| F14 | Origen igual a destino | MAD → MAD | ✅ Error. |
| F15 | Vuelo en un viaje cancelado | Cancelar el viaje de F1 | ✅ El vuelo sale en gris y deja de contar en las cifras. |

## P2 — Estadísticas (comprobar tras cada cambio)

| # | Comprobación | Resultado esperado |
|---|---|---|
| E1 | Home, franja «This year» | Días del año en curso solo de viajes completados (sin solapes) y países soberanos visitados por primera vez este año. |
| E2 | Trips, filtro por año | Viajes que empiezan ese año; días, países y ciudades de ese año natural (incluidos los días de un viaje empezado el año anterior). |
| E3 | Passport, «At a glance» con «All-Time» y con un año | Mismas cifras que Trips para ese periodo; continentes «N / 7»; km en Flights («Distance unknown» si ningún vuelo tiene coordenadas). |
| E4 | Flight History: año + «In trips» / «Standalone» | Cifras solo de vuelos realizados del filtro. Distancia y tiempo con «—» cuando no hay datos, nunca 0. «Longest» y «Most visited» coherentes con la lista. |
| E5 | Crear, editar y borrar un viaje o vuelo, y volver a las páginas | Todas las cifras se actualizan; nada queda duplicado. |
| E6 | Cuenta sin datos | Ceros donde corresponde, «—» en distancia y tiempo, y sin errores. |

## P2 — Borrados

| # | Pasos | Resultado esperado |
|---|---|---|
| B1 | Borrar un viaje completado con vuelos | Sus visitas y fotos se borran; los vuelos quedan **independientes y confirmados**. |
| B2 | Borrar un viaje cancelado con vuelos | Los vuelos quedan independientes **sin** confirmar. |
| B3 | Borrar un vuelo | Desaparece de la lista, del mapa y de las cifras. |

## P3 — Fotos y álbumes

| # | Pasos | Resultado esperado |
|---|---|---|
| P1 | Subir una foto con el worker en marcha | Pasa de pendiente a lista; la fecha y la ubicación EXIF se leen en el servidor. |
| P2 | Crear un álbum, añadir fotos, quitar la de portada | El álbum elige otra portada; las fotos no se borran. |
| P3 | Borrar un álbum | Las fotos siguen existiendo. |
| P4 | Borrar una foto que está en un álbum | Desaparece del álbum; si era la portada, el álbum se queda sin ella. |

## P3 — Navegación y estados vacíos

- Recorrer Home, Trips, ficha de viaje, Countries, Passport, Flight History, Photos y Profile sin datos y con datos: ningún error.
- Ancho de móvil (≈390 px): las cifras y los formularios se pueden leer y usar (horas y «+1 day» incluidos).
- Safari: repetir F2, F8 y F9 (los campos de hora no se han probado allí de forma automática).
