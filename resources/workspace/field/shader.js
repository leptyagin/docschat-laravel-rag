export const VERTEX_SHADER = `
attribute vec2 aPosition;

void main() {
    gl_Position = vec4(aPosition, 0.0, 1.0);
}
`;

/**
 * Paints the whole field in one pass. The color composition is a function of a
 * point, so a rib of fluted glass can sample it somewhere else: each rib shows a
 * mirrored, compressed slice of what lies behind it, which is what refraction is.
 */
export function fragmentShader({ blobs, dispersion }) {
    return `
precision highp float;

#define BLOBS ${blobs}

uniform vec2 uResolution;
uniform float uTime;
uniform float uSoftness;
uniform vec4 uRibs;
uniform vec2 uBand;
uniform vec4 uBlobShape[BLOBS];
uniform vec4 uBlobColor[BLOBS];

const vec3 SKY = vec3(0.043, 0.541, 0.780);
const vec3 DEEP = vec3(0.016, 0.145, 0.345);

float hash(float n) {
    return fract(sin(n * 127.1) * 43758.5453);
}

float grain(vec2 point) {
    return fract(sin(dot(point, vec2(12.9898, 78.233))) * 43758.5453);
}

vec3 composition(vec2 point, float aspect) {
    vec2 warped = point;

    warped.x += 0.07 * sin(point.y * 3.1 + uTime * 0.11) + 0.03 * sin(point.y * 7.3 - uTime * 0.07);
    warped.y += 0.06 * sin(point.x * 2.3 / aspect - uTime * 0.09);

    vec3 color = mix(DEEP, SKY, smoothstep(-0.1, 1.05, warped.y + 0.18 * sin(warped.x * 1.7 / aspect)));

    for (int index = 0; index < BLOBS; index++) {
        vec4 shape = uBlobShape[index];
        vec2 offset = (warped - shape.xy) / shape.zw;
        float weight = exp(-dot(offset, offset) / uSoftness) * uBlobColor[index].a;

        color = mix(color, uBlobColor[index].rgb, clamp(weight, 0.0, 1.0));
    }

    return color;
}

void main() {
    float aspect = uResolution.x / uResolution.y;
    vec2 uv = gl_FragCoord.xy / uResolution;
    vec2 point = vec2(uv.x * aspect, uv.y);

    float ribPosition = uv.x * uRibs.x;
    float rib = floor(ribPosition);
    float across = fract(ribPosition) - 0.5;
    float seed = hash(rib + 3.0);

    float band = 0.5 + 0.5 * sin(uv.x * 3.4 + uv.y * 1.3 + uBand.x);
    float glass = uRibs.w * mix(1.0 - uBand.y, 1.0, band);

    float lens = across * (1.0 + 1.6 * across * across);
    float width = aspect / uRibs.x;
    vec2 refracted = vec2(
        (rib + 0.5) * width - lens * width * uRibs.y + (seed - 0.5) * width * 0.9,
        point.y + (seed - 0.5) * uRibs.z + sin(rib * 0.61 + uTime * 0.13) * uRibs.z * 0.45
    );
    vec2 lookup = mix(point, refracted, glass);

    ${dispersion
        ? `vec2 spread = vec2(width * 0.16 * glass * across, 0.0);
    vec3 color = vec3(composition(lookup + spread, aspect).r, composition(lookup - spread, aspect).gb);`
        : 'vec3 color = composition(lookup, aspect);'}

    float edge = abs(across) * 2.0;
    float crest = 1.0 - edge * edge;

    color *= 1.0 + 0.07 * crest * glass;
    color *= 1.0 - 0.17 * smoothstep(0.72, 1.0, edge) * glass;
    color += 0.05 * smoothstep(0.42, 0.5, across) * glass;

    color = mix(color, DEEP, 0.2 * smoothstep(0.5, 0.0, uv.y));
    color += (grain(gl_FragCoord.xy + uTime) - 0.5) * 0.022;

    gl_FragColor = vec4(color, 1.0);
}
`;
}
