/**
 * Reads server-sent events from a POST response, which EventSource cannot do.
 */
export async function readEvents(response, onEvent) {
    const reader = response.body.pipeThrough(new TextDecoderStream()).getReader();
    let buffer = '';

    for (;;) {
        const { value, done } = await reader.read();

        if (done) {
            return;
        }

        buffer += value;

        const frames = buffer.split('\n\n');
        buffer = frames.pop();

        for (const frame of frames) {
            const event = /^event: (.+)$/m.exec(frame)?.[1];
            const data = /^data: (.+)$/m.exec(frame)?.[1];

            if (event && data) {
                onEvent(event, JSON.parse(data));
            }
        }
    }
}
