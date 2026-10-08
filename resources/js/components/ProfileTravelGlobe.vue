<script setup lang="ts">
import {
    onBeforeUnmount,
    onMounted,
    ref,
} from 'vue';

import Globe from 'three-globe';
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

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

    if (!code || code === '-99') {
        return null;
    }

    return String(code).toUpperCase();
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
};

const animate = () => {
    if (!scene || !camera || !renderer) {
        return;
    }

    controls?.update();

    renderer.render(
        scene,
        camera,
    );

    animationFrame =
        window.requestAnimationFrame(
            animate,
        );
};

const initGlobe = async () => {
    if (!container.value) {
        return;
    }

    /*
     * WORLD DATA
     *
     * Use the original GeoJSON.
     * The simplified version caused geometry/rendering issues.
     */

    const response = await fetch(
        '/data/world.json',
        {
            cache: 'force-cache',
        },
    );

    if (!response.ok) {
        throw new Error(
            'No se ha podido cargar /data/world.json',
        );
    }

    const world = await response.json();

    /*
     * SCENE
     */

    scene = new THREE.Scene();

    scene.background = new THREE.Color(
        '#111719',
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
            '#6b8589',
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
                    ? '#789a96'
                    : '#263337';
            },
        )
        .polygonSideColor(
            () => '#151d1f',
        )
        .polygonStrokeColor(
            () => '#4a5b5f',
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
            '#172124',
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
     * START RENDER LOOP
     */

    animate();
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

    /*
     * Controls
     */

    controls?.dispose();

    /*
     * Globe
     */

    if (globe) {
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
                <p class="text-sm font-semibold text-red-400">
                    Error inicializando el globo
                </p>

                <p class="mt-3 max-w-xl font-mono text-xs leading-6 text-slate-400">
                    {{ errorMessage }}
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.profile-travel-globe {
    width: 100%;
    height: 560px;
    min-height: 420px;
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
        height: 460px;
        min-height: 380px;
    }
}
</style>