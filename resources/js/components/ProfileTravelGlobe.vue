<script setup lang="ts">
import {
    onBeforeUnmount,
    onMounted,
    ref,
} from 'vue';

import Globe from 'three-globe';
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { normalizeGeoJsonIsoCode } from '../data/countryCodes';
import { loadWorldCountries } from '../data/worldCountries';

interface Country {
    name: string;
    iso_code: string;
}

const props = defineProps<{
    countries: Country[];
}>();

const container = ref<HTMLElement | null>(null);
const errorMessage = ref<string | null>(null);

let scene: THREE.Scene | null = null;
let camera: THREE.PerspectiveCamera | null = null;
let renderer: THREE.WebGLRenderer | null = null;
let globe: InstanceType<typeof Globe> | null = null;
let controls: OrbitControls | null = null;

let animationFrame: number | null = null;
let resizeObserver: ResizeObserver | null = null;
let visibilityObserver: IntersectionObserver | null = null;

/*
 * Render bajo demanda: la escena solo cambia al girar o hacer zoom, al
 * redimensionar y mientras three-globe construye y anima los polígonos.
 * Fuera de esos momentos el bucle se detiene (antes dibujaba el mismo
 * fotograma 60 veces por segundo), y también cuando el globo no se ve.
 */
const IDLE_MS = 1500; // > polygonsTransitionDuration (1000 ms por defecto)

// Unidades de la escena (globo de radio 100, cámara a 340-540): ~0,1 px en pantalla.
const MIN_CAMERA_MOVE = 0.1;
const lastCameraPosition = new THREE.Vector3();
let updatingControls = false;

let visible = true;
let interacting = false;
let polygonsBuilt = false;
let lastActivity = 0;

const getVisitedCodes = () => {
    return new Set(
        props.countries
            .map((country) => country.iso_code?.toUpperCase())
            .filter(Boolean),
    );
};

const getCountryCode = (feature: any): string | null => {
    const properties = feature?.properties ?? {};

    const code =
        properties.ISO_A2 ??
        properties.iso_a2 ??
        properties.ISO_A2_EH ??
        properties.iso_code ??
        null;

    return normalizeGeoJsonIsoCode(code);
};

const resize = () => {
    if (!container.value || !camera || !renderer) {
        return;
    }

    const width = container.value.clientWidth;
    const height = container.value.clientHeight;

    if (!width || !height) {
        return;
    }

    camera.aspect = width / height;
    camera.updateProjectionMatrix();

    renderer.setSize(
        width,
        height,
        false,
    );

    renderer.setPixelRatio(
        Math.min(
            window.devicePixelRatio,
            1.75,
        ),
    );

    requestRender();
};

// three-globe crea todos los polígonos a la vez en su propio ticker.
const hasPolygons = (): boolean => {
    let found = false;

    globe?.traverse((object) => {
        found ||= (object as { __globeObjType?: string }).__globeObjType === 'polygon';
    });

    return found;
};

const animate = () => {
    animationFrame = null;

    if (!scene || !camera || !renderer || !visible) {
        return;
    }

    const now = performance.now();

    // Giro, zoom e inercia. update() da true hasta movimientos de una milésima
    // de píxel, así que se mide el desplazamiento de la cámara.
    lastCameraPosition.copy(camera.position);
    updatingControls = true;
    controls?.update();
    updatingControls = false;

    if (camera.position.distanceTo(lastCameraPosition) > MIN_CAMERA_MOVE) {
        lastActivity = now;
    }

    if (!polygonsBuilt && hasPolygons()) {
        polygonsBuilt = true;
        lastActivity = now; // deja correr la transición de altura de los países
    }

    renderer.render(
        scene,
        camera,
    );

    if (!polygonsBuilt || interacting || now - lastActivity < IDLE_MS) {
        animationFrame ??=
            window.requestAnimationFrame(
                animate,
            );
    } else if (animationFrame === null) {
        // Quieto: también se paran los bucles internos de three-globe (tweens y tickers).
        globe?.pauseAnimation();
    }
};

function requestRender() {
    lastActivity = performance.now();

    if (!visible) {
        return;
    }

    globe?.resumeAnimation();

    if (animationFrame === null) {
        animationFrame =
            window.requestAnimationFrame(
                animate,
            );
    }
}

const initGlobe = async () => {
    if (!container.value) {
        return;
    }

    /*
     * WORLD DATA
     *
     * TopoJSON cuantizado, sin simplificar: una versión simplificada daba
     * problemas de geometría (ver data/worldCountries.ts).
     */

    const world = await loadWorldCountries();

    if (!container.value) {
        return;
    }

    /*
     * SCENE
     */

    scene = new THREE.Scene();

    scene.background = new THREE.Color(
        '#0E1525',
    );

    /*
     * CAMERA
     */

    const width =
        container.value.clientWidth || 900;

    const height =
        container.value.clientHeight || 560;

    camera = new THREE.PerspectiveCamera(
        32,
        width / height,
        1,
        2000,
    );

    /*
     * Keep the globe slightly smaller
     * so there is breathing room around it.
     */

    camera.position.set(
        0,
        0,
        400,
    );

    /*
     * RENDERER
     */

    renderer = new THREE.WebGLRenderer({
        antialias: false,
        alpha: false,
        powerPreference: 'high-performance',
    });

    renderer.setPixelRatio(
        Math.min(
            window.devicePixelRatio,
            1.75,
        ),
    );

    renderer.setSize(
        width,
        height,
        false,
    );

    container.value.appendChild(
        renderer.domElement,
    );

    /*
     * LIGHTING
     */

    const ambientLight =
        new THREE.AmbientLight(
            0xffffff,
            1.7,
        );

    scene.add(
        ambientLight,
    );

    const directionalLight =
        new THREE.DirectionalLight(
            0xffffff,
            2,
        );

    directionalLight.position.set(
        5,
        5,
        5,
    );

    scene.add(
        directionalLight,
    );

    /*
     * GLOBE
     */

    globe = new Globe({
        waitForGlobeReady: true,
        animateIn: false,
    });

    const visitedCodes =
        getVisitedCodes();

    const features =
        world.features ?? [];

    globe
        .showGlobe(true)
        .showAtmosphere(true)
        .atmosphereColor(
            '#22D3EE',
        )
        .atmosphereAltitude(
            0.075,
        )
        .polygonsData(
            features,
        )
        .polygonCapColor(
            (feature: any) => {
                const code =
                    getCountryCode(
                        feature,
                    );

                return code &&
                    visitedCodes.has(
                        code,
                    )
                    ? '#2BC4DA'
                    : '#1A2540';
            },
        )
        .polygonSideColor(
            () => '#0E1525',
        )
        .polygonStrokeColor(
            () => '#2E3E60',
        )
        .polygonAltitude(
            (feature: any) => {
                const code =
                    getCountryCode(
                        feature,
                    );

                return code &&
                    visitedCodes.has(
                        code,
                    )
                    ? 0.016
                    : 0.004;
            },
        );

    /*
     * Globe material
     *
     * Gives the ocean a dark graphite tone
     * instead of pure black.
     */

    const globeMaterial =
        (globe as any).globeMaterial();

    globeMaterial.color =
        new THREE.Color(
            '#0B1222',
        );

    globeMaterial.roughness = 1;
    globeMaterial.metalness = 0;

    /*
     * Add globe to scene
     */

    scene.add(
        globe,
    );

    /*
     * CAMERA CONTROLS
     */

    controls = new OrbitControls(
        camera,
        renderer.domElement,
    );

    controls.enablePan = false;

    controls.enableDamping = true;
    controls.dampingFactor = 0.07;

    controls.rotateSpeed = 0.45;
    controls.zoomSpeed = 0.65;

    controls.minDistance = 340;
    controls.maxDistance = 540;

    controls.target.set(
        0,
        0,
        0,
    );

    controls.addEventListener('start', () => {
        interacting = true;
        requestRender();
    });

    controls.addEventListener('end', () => {
        interacting = false;
        requestRender();
    });

    // Zoom con la rueda o teclas: OrbitControls se actualiza solo y avisa. Los
    // 'change' de nuestro propio controls.update() (inercia) no cuentan: los
    // mide animate() con MIN_CAMERA_MOVE.
    controls.addEventListener('change', () => {
        if (!updatingControls) {
            requestRender();
        }
    });

    /*
     * CENTER ON MADRID / SPAIN
     *
     * Spain should be the visual protagonist
     * without forcing Madrid to be mathematically
     * centered in the viewport.
     */

    const madridCoords = (
        globe as any
    ).getCoords(
        40.4168,
        -3.7038,
        0,
    );

    const madridDirection =
        new THREE.Vector3(
            madridCoords.x,
            madridCoords.y,
            madridCoords.z,
        ).normalize();

    const cameraDirection =
        new THREE.Vector3(
            0,
            0,
            1,
        );

    globe.quaternion.setFromUnitVectors(
        madridDirection,
        cameraDirection,
    );

    controls.update();

    /*
     * RESIZE
     */

    resize();

    resizeObserver =
        new ResizeObserver(() => {
            resize();
        });

    resizeObserver.observe(
        container.value,
    );

    /*
     * Pausa mientras el globo está fuera de pantalla.
     */

    visibilityObserver =
        new IntersectionObserver(([entry]) => {
            visible = entry.isIntersecting;

            if (visible) {
                requestRender();
            } else {
                if (animationFrame !== null) {
                    window.cancelAnimationFrame(animationFrame);
                    animationFrame = null;
                }

                globe?.pauseAnimation();
            }
        });

    visibilityObserver.observe(
        container.value,
    );

    /*
     * START RENDER LOOP
     */

    requestRender();
};

onMounted(async () => {
    try {
        await initGlobe();
    } catch (error) {
        console.error(
            'Error inicializando ProfileTravelGlobe:',
            error,
        );

        errorMessage.value =
            error instanceof Error
                ? `${error.name}: ${error.message}`
                : String(error);
    }
});

onBeforeUnmount(() => {
    /*
     * Stop animation
     */

    if (
        animationFrame !== null
    ) {
        window.cancelAnimationFrame(
            animationFrame,
        );
    }

    /*
     * Resize observer
     */

    resizeObserver?.disconnect();
    visibilityObserver?.disconnect();

    /*
     * Controls
     */

    controls?.dispose();

    /*
     * Globe
     */

    if (globe) {
        // Sin esto, los bucles de three-globe seguían corriendo en las demás páginas.
        globe.pauseAnimation();
        globe.polygonsData([]);

        globe = null;
    }

    /*
     * Renderer
     */

    if (renderer) {
        renderer.dispose();

        renderer.domElement.remove();

        renderer = null;
    }

    /*
     * Scene
     */

    camera = null;
    scene = null;
    controls = null;
});
</script>

<template>
    <div
        ref="container"
        class="profile-travel-globe"
    >
        <div
            v-if="errorMessage"
            class="flex h-full items-center justify-center p-8 text-center"
        >
            <div>
                <p class="text-sm font-semibold text-[var(--vyamap-danger)]">
                    The globe could not be loaded
                </p>

                <p class="mt-2 max-w-xl text-sm text-[var(--vyamap-text-muted)]">
                    {{ errorMessage }}
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.profile-travel-globe {
    width: 100%;
    height: 520px;
    min-height: 380px;
    overflow: hidden;
    cursor: grab;
}

.profile-travel-globe:active {
    cursor: grabbing;
}

.profile-travel-globe :deep(canvas) {
    display: block;
    width: 100%;
    height: 100%;
}

@media (max-width: 768px) {
    .profile-travel-globe {
        height: 380px;
        min-height: 320px;
    }
}
</style>