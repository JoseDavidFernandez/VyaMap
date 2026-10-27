# VyaMap — Sistema visual

Neofuturismo nocturno con vidrio usado con moderación. Pantallas de trabajo (registrar viajes, vuelos y fotos, consultar estadísticas): la claridad y la consistencia mandan; la identidad vive en la paleta, la tipografía y una sola firma, la **ruta eléctrica** de cian a violeta.

La implementación está en `resources/css/app.css` (tokens y componentes) y en `resources/js/components/ui/` (`Icon`, `EmptyState`, `AuthShell`).

## Color

| Token | Valor | Uso |
|---|---|---|
| `--vyamap-background` | `#070B14` | Fondo; con dos resplandores fijos (cian arriba a la izquierda, violeta arriba a la derecha) y una retícula de 64 px casi imperceptible |
| `--vyamap-surface` | `#0E1525` | Paneles y tarjetas |
| `--vyamap-surface-muted` | `#0B1120` | Franjas secundarias, estados vacíos, filas |
| `--vyamap-surface-strong` / `-raised` | `#131C30` / `#1A2540` | Botones secundarios, hover, miniaturas |
| `--vyamap-text` | `#EAF0FA` | Texto principal (15:1 sobre superficies) |
| `--vyamap-text-muted` | `#A6B3CA` | Texto secundario (>8:1) |
| `--vyamap-text-subtle` | `#8090AA` | Etiquetas y ayudas (>5:1) |
| `--vyamap-cyan` / `-soft` | `#22D3EE` / `#5EE7F7` | Acción principal, visitado, realizado, enlaces |
| `--vyamap-violet` | `#A78BFA` | Planificado, próximo, destino de una ruta |
| `--vyamap-warning` | `#FBBF24` | Pendiente de confirmar |
| `--vyamap-danger` | `#FB7185` | Destructivo y errores |
| `--vyamap-route` | degradado cian → `#7C9BFF` → violeta | La firma: líneas de ruta, barras de progreso, indicador de navegación activa |

Estados de viaje y vuelo (`utils/tripPhase.ts`): completado y en curso en cian, planificado y próximo en violeta, pendiente en ámbar, cancelado en gris. Mapas: países visitados en cian, planificados en violeta; rutas realizadas en cian, próximas en violeta y sin confirmar en gris.

## Tipografía

**Geist** (variable, autoalojada con `@fontsource-variable/geist`, OFL-1.1). Cifras siempre tabulares en estadísticas, fechas y códigos (`vyamap-number`, `vyamap-code`).

| Rol | Clase | Tamaño |
|---|---|---|
| Título de página | `vyamap-page-title` | 30–48 px fluido, peso 650, tracking −0.035em |
| Sección | `vyamap-h2` | 18–22 px, 620 |
| Subsección | `vyamap-h3` | 16 px, 600 |
| Cifra | `vyamap-stat-value` | 24–32 px, 640, tabular |
| Texto | cuerpo | 15 px, interlineado 1.55 |
| Etiqueta de dato | `vyamap-label` / `vyamap-stat-label` | 13 px; nunca por debajo de 12 px |

Sin etiquetas pequeñas en mayúsculas encima de los títulos, sin texto con degradado.

## Superficies y profundidad

- **Sólidas** (`vyamap-card`, `vyamap-panel`) para el contenido: borde de 1 px, radio de 20 px (28 px en mapas), sombra con desplazamiento y un reflejo interior de 1 px.
- **Vidrio** (`vyamap-glass`, 86 % de opacidad con blur 18 px) solo en lo que flota sobre algo: navegación, barra de selección, hojas y diálogos. **Superposiciones sobre mapas** (`vyamap-map-overlay`) con vidrio más ligero.
- **Desplegables** (`vyamap-popover`) opacos: el texto de debajo nunca se cuela.

## Componentes

- Botones: `vyamap-button` (principal, cian), `-secondary`, `-ghost`, `-danger`, `-sm`; `vyamap-icon-button` (36 px). Altura mínima de 40 px (44 px en formularios de acceso).
- Campos: `vyamap-input` / `vyamap-textarea` (44 px de alto, foco cian con halo), `select.vyamap-input` con flecha propia, `aria-invalid` en rosa, `vyamap-error`, `vyamap-help`.
- Filtros: `vyamap-segmented` + `vyamap-segment` con `aria-pressed`.
- Insignias: `vyamap-badge` + `-cyan`, `-violet`, `-amber`, `-danger`, `-success`.
- Datos: `vyamap-stat` (dl con valor, etiqueta y pista), `vyamap-progress`, `vyamap-route-line`, `vyamap-flight`.
- Iconos: `components/ui/Icon.vue`, SVG propios en rejilla de 24 y trazo 1.75. Nunca caracteres Unicode como iconos.
- Estados vacíos: `components/ui/EmptyState.vue` (icono, qué falta, por qué importa y la acción que lo resuelve; `compact` dentro de paneles). Cada lista, mapa o panel que puede quedar vacío tiene el suyo.

## Imágenes genéricas

Paisajes CC0 (Unsplash vía Wikimedia Commons) en `public/images/stock/`, en WebP de 800 y 1600 px sin metadatos; créditos en `public/images/stock/CREDITS.md`. Se usan en las tarjetas de viaje sin foto propia, en la tarjeta del próximo viaje y en los estados vacíos grandes (`EmptyState` con `image`). Siempre a través de `components/ui/StockImage.vue`: velo de la paleta, saturación algo reducida y la etiqueta visible «Illustrative image», con el crédito en el tooltip; nunca pasan por fotos de los viajes del usuario. Las miniaturas pequeñas (menos de 64 px) usan un icono en lugar de imagen. Un mismo viaje muestra siempre la misma (`stockImageFor(id)`).

## Pasaporte

Librillo abierto en `pages/Passport.vue`: papel de seguridad azul tinta con guilloché cian y violeta muy tenue y un grano fino, lomo con sombra hacia el centro y, en móvil, páginas apiladas. Página de datos con emblema holográfico, foto con sello, campos en mayúsculas espaciadas, anillo del mundo explorado y zona MRZ generada con datos reales. Página de sellos con tinta cian o violeta, doble anillo, rotación ligera y nombre en hasta dos líneas. Las microetiquetas del documento son la única excepción al mínimo de 12 px: 11 px, siempre con contraste ≥5:1.

## Disposición y navegación

- Contenedor `vyamap-container` (máx. 1280 px, márgenes fluidos de 16–40 px) y `vyamap-stack` para el ritmo vertical.
- Cabecera de página: título, descripción y acción principal a la derecha (`vyamap-page-header`). Las páginas de detalle llevan enlace de vuelta.
- Navegación (`components/Navbar.vue`): barra lateral con etiquetas desde 1280 px; rail de iconos de 1024 a 1279 px; en móvil, barra superior (marca, «New trip», perfil) y barra inferior con Home, Trips, Flights, Passport y More. Zonas seguras con `env(safe-area-inset-*)`.
- Cuadrículas de sección con `grid-cols-1` en móvil (`minmax(0, 1fr)`), para que el texto truncado no ensanche la columna.

## Movimiento

Solo transiciones de color, borde y fondo de 150 ms con salida exponencial, y la apertura de la hoja «More». Sin desplazamientos ni zooms al pasar el ratón. `prefers-reduced-motion` las anula.

## Detalles del navegador

Selección de texto en cian, cursor de texto cian, anillo de foco cian de 2 px, barras de desplazamiento finas y tintadas, `color-scheme: dark`, `theme-color` del fondo y título de pestaña `Página · VyaMap`. Las páginas de error (`resources/views/errors/minimal.blade.php`) usan la misma paleta con estilos en línea.
