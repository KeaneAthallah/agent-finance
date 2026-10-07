export type SseEvent = {
    event: string;
    data: string;
};

type SseParser = {
    push: (chunk: string) => SseEvent[];
    flush: () => SseEvent[];
};

function parseBlock(block: string): SseEvent | null {
    const lines = block.replace(/\r\n/g, '\n').split('\n');

    let event = 'message';
    let data = '';

    for (const line of lines) {
        if (line.startsWith('event:')) {
            event = line.slice(6).trim();
        } else if (line.startsWith('data:')) {
            data += line.slice(5).trim();
        }
    }

    if (data === '' && event === 'message') {
        return null;
    }

    return { event, data };
}

/**
 * Incremental server-sent-events parser: feed decoded chunks, get back the
 * complete events found so far. Partial events stay buffered until their
 * blank-line terminator arrives, so chunks may split anywhere.
 */
export function createSseParser(): SseParser {
    let buffer = '';

    function drain(): SseEvent[] {
        const events: SseEvent[] = [];
        let separator = buffer.indexOf('\n\n');

        while (separator !== -1) {
            const block = buffer.slice(0, separator);

            buffer = buffer.slice(separator + 2);

            const parsed = parseBlock(block);

            if (parsed !== null) {
                events.push(parsed);
            }

            separator = buffer.indexOf('\n\n');
        }

        return events;
    }

    return {
        push: (chunk: string) => {
            buffer += chunk;

            return drain();
        },
        flush: () => {
            if (buffer.trim() === '') {
                buffer = '';

                return [];
            }

            const trailing = buffer;

            buffer = '';

            const parsed = parseBlock(trailing);

            return parsed === null ? [] : [parsed];
        },
    };
}