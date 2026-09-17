# Frontend

## Stack

VyaMap utiliza la siguiente arquitectura frontend:

```text
                 VyaMap
                   │
        ┌──────────┴──────────┐
        │                     │
     Backend              Frontend
        │                     │
     Laravel              Vue 3
        │                     │
     Inertia  ────────────────┘
                              │
                         TypeScript
                              │
                            Vite
```

## Laravel

Laravel continúa siendo el backend principal de la aplicación.

Se encarga de:

- lógica de negocio
- autenticación y autorización
- validación
- acceso a base de datos
- modelos Eloquent
- controladores
- API e integraciones externas

## Inertia

Inertia actúa como puente entre Laravel y Vue.

Permite utilizar Laravel como backend y Vue para construir interfaces interactivas sin tener que convertir VyaMap en una SPA completamente separada del backend.

Laravel sigue siendo la fuente de verdad de los datos.

## Vue 3

Vue se utiliza para construir la interfaz de usuario.

Se ha elegido porque VyaMap tendrá una interfaz con bastante interacción:

- mapas
- viajes
- ciudades
- vuelos
- fotografías
- diarios
- filtros
- estadísticas
- futuras funcionalidades de descubrimiento e IA

Vue permite gestionar este estado y estas interacciones de forma reactiva.

## TypeScript

TypeScript se utiliza para añadir tipado al código JavaScript del frontend.

Esto permitirá detectar errores antes de ejecutar la aplicación y mantener el código más fácil de mantener a medida que VyaMap crezca.

## Vite

Vite es el sistema utilizado para desarrollar y compilar los assets del frontend.

Durante el desarrollo proporciona:

- servidor de desarrollo
- Hot Module Replacement (HMR)
- compilación de Vue y TypeScript

Para producción genera los assets optimizados que utilizará Laravel.

## Estructura inicial

```text
resources/
└── js/
    ├── app.ts
    └── pages/
        └── Home.vue
```

`app.ts` es el punto de entrada del frontend.

Las páginas Vue se encuentran inicialmente dentro de `resources/js/pages/`.

## Flujo de una página

Una petición a Laravel sigue inicialmente este flujo:

```text
Navegador
    ↓
Laravel Route
    ↓
Inertia
    ↓
Vue Page
    ↓
HTML renderizado
```

Por ejemplo:

```text
GET /
 ↓
routes/web.php
 ↓
Inertia::render('Home')
 ↓
resources/js/pages/Home.vue
```

## Decisión arquitectónica

VyaMap utiliza una arquitectura monolítica con Laravel como backend e Inertia + Vue como capa de interfaz.

No se crea inicialmente un backend REST independiente ni una SPA completamente separada.

Esto mantiene el proyecto más sencillo mientras permite construir una interfaz moderna y altamente interactiva.

La arquitectura podrá evolucionar si las necesidades del proyecto lo requieren.
