<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AnswerBody from './AnswerBody.js';
import { readEvents } from './stream.js';
import { load, save } from './storage.js';

/**
 * Inside the workspace the host page passes handlers: sources open beside the
 * chat, and the panel can be hidden. On a customer's site none of them are set.
 */
const props = defineProps({
    config: { type: Object, required: true },
    onOpenSource: { type: Function, default: null },
    onHide: { type: Function, default: null },
    onBusyChange: { type: Function, default: null },
});

const GENERIC_ERROR = 'Sorry, something went wrong. Please try again.';

const stored = load(props.config.storageKey);
const messages = ref(stored?.messages ?? []);
const conversationToken = ref(stored?.conversationToken ?? null);
const draft = ref('');
const isBusy = ref(false);
const log = ref(null);
const input = ref(null);
const isEmbedded = window.parent !== window;

const canSend = computed(() => draft.value.trim() !== '' && !isBusy.value);
const remaining = computed(() => props.config.maxQuestionLength - draft.value.length);

function persist() {
    save(props.config.storageKey, {
        conversationToken: conversationToken.value,
        messages: messages.value.filter((message) => !message.isStreaming),
    });
}

async function scrollToEnd() {
    await nextTick();

    if (log.value) {
        log.value.scrollTop = log.value.scrollHeight;
    }
}

function applyEvent(answer, event, data) {
    if (event === 'started') {
        conversationToken.value = data.conversation_token;
    }

    if (event === 'delta') {
        answer.text += data.text;
        scrollToEnd();
    }

    if (event === 'completed') {
        answer.id = data.message_id;
        answer.sources = data.sources;
        answer.isAnswered = data.answered;
    }
}

async function failureText(response) {
    try {
        const body = await response.json();

        return response.status === 429 ? body.message : (body.errors?.question?.[0] ?? GENERIC_ERROR);
    } catch {
        return GENERIC_ERROR;
    }
}

async function ask(question) {
    const text = question.trim();

    if (text === '' || isBusy.value) {
        return;
    }

    isBusy.value = true;
    draft.value = '';
    messages.value.push({ role: 'user', text });
    messages.value.push({ role: 'assistant', text: '', sources: [], id: null, feedback: null, isStreaming: true });

    const answer = messages.value[messages.value.length - 1];

    scrollToEnd();

    try {
        const response = await fetch(props.config.messagesUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ question: text, conversation_token: conversationToken.value }),
        });

        if (response.ok) {
            await readEvents(response, (event, data) => applyEvent(answer, event, data));
        } else {
            answer.text = await failureText(response);
            answer.isFailed = true;
        }
    } catch {
        answer.isFailed = answer.text === '';
        answer.text = answer.text || GENERIC_ERROR;
    }

    answer.isStreaming = false;
    isBusy.value = false;

    persist();
    scrollToEnd();
    input.value?.focus();
}

async function rate(message, feedback) {
    const previous = message.feedback;

    message.feedback = feedback;

    try {
        const response = await fetch(props.config.feedbackUrl.replace('__MESSAGE__', message.id), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ feedback, conversation_token: conversationToken.value }),
        });

        if (!response.ok) {
            message.feedback = previous;
        }
    } catch {
        message.feedback = previous;
    }

    persist();
}

function close() {
    window.parent.postMessage({ type: 'docschat:close' }, '*');
}

function onKeydown(event) {
    if (event.key === 'Escape' && isEmbedded) {
        close();
    }
}

function onEnter(event) {
    if (!event.shiftKey) {
        event.preventDefault();
        ask(draft.value);
    }
}

function onParentMessage(event) {
    if (event.source === window.parent && event.data?.type === 'docschat:focus') {
        input.value?.focus();
    }
}

function sourceLabel(source) {
    return source.page ? `${source.title}, p. ${source.page}` : source.title;
}

/**
 * The attributes of a link to a source. A source without an address gives none.
 */
function sourceLink(source) {
    if (props.onOpenSource && source.reader_url) {
        return {
            href: source.reader_url,
            onClick: (event) => {
                event.preventDefault();
                props.onOpenSource(source.reader_url);
            },
        };
    }

    return source.url ? { href: source.url, target: '_blank', rel: 'noopener noreferrer' } : {};
}

/**
 * Puts a question in the box, for the visitor to edit and send.
 */
function prefill(question) {
    draft.value = question.slice(0, props.config.maxQuestionLength);
    nextTick(() => input.value?.focus());
}

function focus() {
    input.value?.focus();
}

defineExpose({ ask, prefill, focus });

watch(isBusy, (busy) => props.onBusyChange?.(busy));

watch(
    draft,
    () => {
        if (input.value) {
            input.value.style.height = 'auto';
            input.value.style.height = `${input.value.scrollHeight}px`;
        }
    },
    { flush: 'post' },
);

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    window.addEventListener('message', onParentMessage);

    if (!props.onHide) {
        input.value?.focus();
    }

    scrollToEnd();
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('message', onParentMessage);
});
</script>

<template>
    <div class="chat">
        <header class="chat__header">
            <span class="chat__mark" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
            <div class="chat__identity">
                <p class="chat__eyebrow">AI Assistant</p>
                <h2 class="chat__title">{{ config.botName }}</h2>
            </div>
            <button v-if="isEmbedded" type="button" class="chat__icon" aria-label="Close chat" @click="close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12" /></svg>
            </button>
            <button v-else-if="onHide" type="button" class="chat__icon" aria-label="Hide assistant" @click="onHide">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6" /></svg>
            </button>
        </header>

        <div ref="log" class="chat__log" role="log" aria-live="polite" aria-label="Conversation" tabindex="0">
            <p class="chat__greeting">{{ config.greeting }}</p>

            <div v-if="messages.length === 0 && config.suggestedQuestions.length > 0" class="chat__suggestions">
                <button v-for="question in config.suggestedQuestions" :key="question" type="button" class="chat__suggestion" @click="ask(question)">
                    {{ question }}
                </button>
            </div>

            <template v-for="(message, index) in messages" :key="index">
                <p v-if="message.role === 'user'" class="chat__bubble chat__bubble--user">{{ message.text }}</p>

                <div v-else class="chat__answer">
                    <div class="chat__bubble" :class="{ 'chat__bubble--failed': message.isFailed, 'chat__bubble--streaming': message.isStreaming }">
                        <AnswerBody v-if="message.text" :text="message.text" :sources="message.sources ?? []" :source-link="sourceLink" />
                        <span v-else class="chat__searching" role="status">
                            <span class="chat__scanner" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
                            Searching the documents
                        </span>
                    </div>

                    <p v-if="message.isAnswered === false && !message.sources?.length" class="chat__refusal">Not answered from the documents</p>

                    <ul v-if="message.sources?.length" class="chat__sources" aria-label="Sources">
                        <li v-for="source in message.sources" :key="source.number">
                            <a v-if="sourceLink(source).href" class="chat__source" v-bind="sourceLink(source)"><b>{{ source.number }}</b>{{ sourceLabel(source) }}</a>
                            <span v-else class="chat__source"><b>{{ source.number }}</b>{{ sourceLabel(source) }}</span>
                        </li>
                    </ul>

                    <div v-if="message.id" class="chat__feedback">
                        <button type="button" class="chat__icon chat__icon--small" aria-label="Good answer" :aria-pressed="message.feedback === 'up'" @click="rate(message, 'up')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 10v12M15 5.9 14 10h5.8a2 2 0 0 1 2 2.4l-1.6 8a2 2 0 0 1-2 1.6H7V10l4-8a3 3 0 0 1 4 3.9z" /></svg>
                        </button>
                        <button type="button" class="chat__icon chat__icon--small" aria-label="Bad answer" :aria-pressed="message.feedback === 'down'" @click="rate(message, 'down')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 14V2M9 18.1l1-4.1H4.2a2 2 0 0 1-2-2.4l1.6-8a2 2 0 0 1 2-1.6H17v12l-4 8a3 3 0 0 1-4-3.9z" /></svg>
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <div class="chat__dock">
            <form class="chat__composer" :class="{ 'chat__composer--busy': isBusy }" @submit.prevent="ask(draft)">
                <textarea
                    ref="input"
                    v-model="draft"
                    class="chat__input"
                    rows="1"
                    aria-label="Your question"
                    placeholder="Ask a question…"
                    :maxlength="config.maxQuestionLength"
                    @keydown.enter="onEnter"
                ></textarea>
                <button type="submit" class="chat__send" aria-label="Send" :disabled="!canSend">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7" /></svg>
                </button>
            </form>
            <p v-if="remaining <= 50" class="chat__counter">{{ remaining }} characters left</p>
            <p v-if="config.notice" class="chat__notice">{{ config.notice }}</p>
        </div>
    </div>
</template>
