import { ATMOSPHERE, LAYERS, sample } from './choreography.js';
import { fragmentShader, VERTEX_SHADER } from './shader.js';

const AMBIENT_FRAME_MS = 33;
const SLOW_FRAME_MS = 42;
const SLOW_FRAME_RUN = 45;
const MIN_SCALE = 0.34;
const SETTLED = 0.0004;

function compile(gl, type, source) {
    const shader = gl.createShader(type);

    gl.shaderSource(shader, source);
    gl.compileShader(shader);

    if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
        throw new Error(gl.getShaderInfoLog(shader) ?? 'The shader did not compile.');
    }

    return shader;
}

function createProgram(gl, blobs, dispersion) {
    const program = gl.createProgram();

    gl.attachShader(program, compile(gl, gl.VERTEX_SHADER, VERTEX_SHADER));
    gl.attachShader(program, compile(gl, gl.FRAGMENT_SHADER, fragmentShader({ blobs, dispersion })));
    gl.linkProgram(program);

    if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
        throw new Error(gl.getProgramInfoLog(program) ?? 'The program did not link.');
    }

    gl.useProgram(program);
    gl.bindBuffer(gl.ARRAY_BUFFER, gl.createBuffer());
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 3, -1, -1, 3]), gl.STATIC_DRAW);

    const position = gl.getAttribLocation(program, 'aPosition');

    gl.enableVertexAttribArray(position);
    gl.vertexAttribPointer(position, 2, gl.FLOAT, false, 0, 0);

    return Object.fromEntries(
        ['uResolution', 'uTime', 'uSoftness', 'uRibs', 'uBand', 'uBlobShape', 'uBlobColor'].map((name) => [name, gl.getUniformLocation(program, name)]),
    );
}

/**
 * Runs the background on one canvas. The canvas is drawn below screen resolution,
 * because the picture is blurred anyway. A phone draws fewer layers and stops
 * between scrolls; with reduced motion the picture is drawn once and stays.
 *
 * @returns {object|null} Null when the browser cannot run it, which leaves the CSS backdrop.
 */
export function createField(canvas, { isCompact, isStill }) {
    const gl = canvas.getContext('webgl', { alpha: false, antialias: false, depth: false, stencil: false, powerPreference: 'low-power' });

    if (!gl) {
        return null;
    }

    const layers = isCompact ? LAYERS.slice(0, 5) : LAYERS;
    const shapes = new Float32Array(layers.length * 4);
    const colors = new Float32Array(layers.length * 4);
    const progressByLayer = new Float32Array(layers.length);
    const keyframe = [0, 0, 0, 0, 0];
    const atmosphere = [0, 0, 0, 0, 0];

    let uniforms;
    let scale = isCompact ? 0.5 : 0.72;
    let target = 0;
    let ribProgress = 0;
    let velocity = 0;
    let energy = 0;
    let energyTarget = 0;
    let seed = 0;
    let seedTarget = 0;
    let pointerX = 0;
    let pointerY = 0;
    let pointerTargetX = 0;
    let pointerTargetY = 0;
    let time = 40;
    let lastTick = 0;
    let lastDraw = 0;
    let slowFrames = 0;
    let frame = null;

    try {
        uniforms = createProgram(gl, layers.length, !isCompact);
    } catch (error) {
        console.warn(error);

        return null;
    }

    function resize() {
        const width = Math.max(1, Math.round(window.innerWidth * scale));
        const height = Math.max(1, Math.round(window.innerHeight * scale));

        if (canvas.width !== width || canvas.height !== height) {
            canvas.width = width;
            canvas.height = height;
            gl.viewport(0, 0, width, height);
        }
    }

    function draw() {
        const aspect = canvas.width / canvas.height;
        const stretch = Math.min(1.35, Math.max(0.62, aspect));

        sample(ATMOSPHERE, ribProgress, atmosphere);

        layers.forEach((layer, index) => {
            const [amplitudeX, amplitudeY, speedX, speedY, phase] = layer.orbit;
            const depth = (index + 1) / layers.length;
            const seedAngle = seed * 2.399 + phase;

            sample(layer.keys, progressByLayer[index], keyframe);

            const x = keyframe[0] + amplitudeX * Math.sin(time * speedX + phase) + 0.1 * Math.sin(seedAngle) + pointerX * 0.035 * depth;
            const y = keyframe[1] + amplitudeY * Math.cos(time * speedY + phase) + 0.08 * Math.cos(seedAngle) + pointerY * 0.03 * depth;
            const glow = index === 0 || index === 5 ? 1 + energy * 0.28 : 1;

            shapes.set([x * aspect, y, keyframe[2] * stretch * glow, keyframe[3] * glow], index * 4);
            colors.set([...layer.color, Math.min(1, keyframe[4] * (1 + energy * 0.15))], index * 4);
        });

        const ribWidth = isCompact ? 22 : 30;
        const ribs = Math.max(14, Math.round(window.innerWidth / ribWidth));
        const refraction = atmosphere[1] + Math.min(1.6, Math.abs(velocity) * 0.5) + energy * 0.5;

        gl.uniform2f(uniforms.uResolution, canvas.width, canvas.height);
        gl.uniform1f(uniforms.uTime, time);
        gl.uniform1f(uniforms.uSoftness, atmosphere[0]);
        gl.uniform4f(uniforms.uRibs, ribs, refraction, atmosphere[2], atmosphere[3]);
        gl.uniform2f(uniforms.uBand, ribProgress * 9 + time * 0.05, atmosphere[4]);
        gl.uniform4fv(uniforms.uBlobShape, shapes);
        gl.uniform4fv(uniforms.uBlobColor, colors);
        gl.drawArrays(gl.TRIANGLES, 0, 3);

        canvas.classList.add('is-live');
    }

    function adapt(delta) {
        slowFrames = delta > SLOW_FRAME_MS ? slowFrames + 1 : 0;

        if (slowFrames >= SLOW_FRAME_RUN && scale > MIN_SCALE) {
            scale = Math.max(MIN_SCALE, scale * 0.8);
            slowFrames = 0;
            resize();
        }
    }

    function tick(now) {
        const delta = Math.min(100, now - (lastTick || now));
        const seconds = delta / 1000;
        let isMoving = false;

        lastTick = now;

        for (let index = 0; index < layers.length; index++) {
            const distance = target - progressByLayer[index];

            progressByLayer[index] += distance * (1 - Math.exp(-seconds * layers[index].lag));
            isMoving ||= Math.abs(distance) > SETTLED;
        }

        const ribDistance = target - ribProgress;
        const follow = (rate) => 1 - Math.exp(-seconds * rate);

        ribProgress += ribDistance * follow(4);
        velocity += (ribDistance * 26 - velocity) * follow(8);
        energy += (energyTarget - energy) * follow(energyTarget > energy ? 3 : 1.4);
        seed += (seedTarget - seed) * follow(1.6);
        pointerX += (pointerTargetX - pointerX) * follow(2.2);
        pointerY += (pointerTargetY - pointerY) * follow(2.2);

        isMoving ||= Math.abs(ribDistance) > SETTLED || Math.abs(energyTarget - energy) > 0.002 || Math.abs(seedTarget - seed) > 0.002 || Math.abs(velocity) > 0.002;

        if (!isCompact) {
            time += seconds * (1 + energy * 2.5);
        }

        adapt(delta);

        if (isMoving || (!isCompact && now - lastDraw >= AMBIENT_FRAME_MS)) {
            draw();
            lastDraw = now;
        }

        frame = isMoving || !isCompact ? requestAnimationFrame(tick) : null;

        if (frame === null) {
            lastTick = 0;
        }
    }

    function wake() {
        if (isStill) {
            return;
        }

        if (frame === null && !document.hidden) {
            frame = requestAnimationFrame(tick);
        }
    }

    function sleep() {
        if (frame !== null) {
            cancelAnimationFrame(frame);
            frame = null;
            lastTick = 0;
        }
    }

    function redrawAfterResize() {
        resize();

        if (isStill || frame === null) {
            draw();
        }
    }

    canvas.addEventListener('webglcontextlost', (event) => {
        event.preventDefault();
        sleep();
        canvas.classList.remove('is-live');
    });

    canvas.addEventListener('webglcontextrestored', () => {
        uniforms = createProgram(gl, layers.length, !isCompact);
        redrawAfterResize();
        wake();
    });

    document.addEventListener('visibilitychange', () => (document.hidden ? sleep() : wake()));
    window.addEventListener('resize', redrawAfterResize, { passive: true });

    resize();
    draw();
    wake();

    return {
        /** @param {number} progress From 0 at the top of the page to 1 at its end. */
        setProgress(progress) {
            if (isStill) {
                return;
            }

            target = progress;
            wake();
        },

        /** Moves the whole composition, so that each document has its own place in it. */
        setSeed(value) {
            seedTarget = value;

            if (isStill) {
                seed = value;
                draw();
            } else {
                wake();
            }
        },

        /** Makes the field more intense while an answer is being prepared. */
        setEnergy(value) {
            if (!isStill) {
                energyTarget = value;
                wake();
            }
        },

        setPointer(x, y) {
            if (!isStill && !isCompact) {
                pointerTargetX = x;
                pointerTargetY = y;
            }
        },
    };
}
