<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { readEvents } from './stream.js';
import { load, save } from './storage.js';

const props = defineProps({ config: { type: Object, required: true } });

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
        }
    } catch {
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
    input.value?.focus();
    scrollToEnd();
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('message', onParentMessage);
});
</script>

<template>
    <div class="chat">
        <header class="header">
            <h1 class="title">{{ config.botName }}</h1>
            <button v-if="isEmbedded" type="button" class="icon-button" aria-label="Close chat" @click="close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12" /></svg>
            </button>
        </header>

        <div ref="log" class="log" role="log" aria-live="polite" aria-label="Conversation" tabindex="0">
            <p class="bubble assistant">{{ config.greeting }}</p>

            <div v-if="messages.length === 0 && config.suggestedQuestions.length > 0" class="suggestions">
                <button v-for="question in config.suggestedQuestions" :key="question" type="button" class="suggestion" @click="ask(question)">
                    {{ question }}
                </button>
            </div>

            <template v-for="(message, index) in messages" :key="index">
                <p v-if="message.role === 'user'" class="bubble user">{{ message.text }}</p>

                <div v-else class="answer">
                    <p class="bubble assistant">
                        <span v-if="message.text">{{ message.text }}</span>
                        <span v-else class="typing" role="status" aria-label="Thinking"><i></i><i></i><i></i></span>
                    </p>

                    <ul v-if="message.sources?.length" class="sources" aria-label="Sources">
                        <li v-for="source in message.sources" :key="source.number">
                            <a v-if="source.url" class="source" :href="source.url" target="_blank" rel="noopener noreferrer">[{{ source.number }}] {{ sourceLabel(source) }}</a>
                            <span v-else class="source">[{{ source.number }}] {{ sourceLabel(source) }}</span>
                        </li>
                    </ul>

                    <div v-if="message.id" class="feedback">
                        <button type="button" class="icon-button small" aria-label="Good answer" :aria-pressed="message.feedback === 'up'" @click="rate(message, 'up')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 10v12M15 5.9 14 10h5.8a2 2 0 0 1 2 2.4l-1.6 8a2 2 0 0 1-2 1.6H7V10l4-8a3 3 0 0 1 4 3.9z" /></svg>
                        </button>
                        <button type="button" class="icon-button small" aria-label="Bad answer" :aria-pressed="message.feedback === 'down'" @click="rate(message, 'down')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 14V2M9 18.1l1-4.1H4.2a2 2 0 0 1-2-2.4l1.6-8a2 2 0 0 1 2-1.6H17v12l-4 8a3 3 0 0 1-4-3.9z" /></svg>
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <form class="composer" @submit.prevent="ask(draft)">
            <textarea
                ref="input"
                v-model="draft"
                class="input"
                rows="1"
                aria-label="Your question"
                placeholder="Ask a question…"
                :maxlength="config.maxQuestionLength"
                @keydown.enter="onEnter"
            ></textarea>
            <button type="submit" class="send" aria-label="Send" :disabled="!canSend">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4zM22 2 11 13" /></svg>
            </button>
        </form>
        <p v-if="remaining <= 50" class="counter">{{ remaining }} characters left</p>
        <p v-if="config.notice" class="notice">{{ config.notice }}</p>
    </div>
</template>
