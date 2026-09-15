<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '@/lib/api';

const issues = ref([]);
const currentIndex = ref(0);
const isOpen = ref(false);
const isDismissing = ref(false);

const current = computed(() => issues.value[currentIndex.value] ?? null);

const accent = computed(() => {
    const map = {
        high: { bar: 'bg-red-500', label: 'Penting', text: 'text-red-600' },
        medium: { bar: 'bg-amber-500', label: 'Info', text: 'text-amber-600' },
        low: { bar: 'bg-slate-400', label: 'Pengumuman', text: 'text-slate-500' },
    };
    return map[current.value?.priority] ?? map.low;
});

async function fetchActiveIssues() {
    try {
        const { data } = await api.get('/hot-issues/active');
        if (data.length) {
            issues.value = data;
            isOpen.value = true;
        }
    } catch (error) {
        console.error('Gagal memuat hot issue:', error);
    }
}

async function dismiss() {
    if (!current.value || isDismissing.value) return;
    isDismissing.value = true;

    try {
        await api.post(`/hot-issues/${current.value.id}/dismiss`);
    } catch (error) {
        console.error('Gagal menutup hot issue:', error);
    } finally {
        isDismissing.value = false;
    }

    if (currentIndex.value < issues.value.length - 1) {
        currentIndex.value += 1;
    } else {
        isOpen.value = false;
    }
}

onMounted(fetchActiveIssues);
</script>

<template>
    <div
        v-if="isOpen && current"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4"
    >
        <div class="flex w-full max-w-md overflow-hidden rounded-lg bg-white shadow-xl">
            <div class="w-1.5 shrink-0" :class="accent.bar"></div>

            <div class="flex-1 p-6">
                <p class="mb-2 text-sm font-medium" :class="accent.text">
                    {{ accent.label }}
                </p>

                <h2 class="mb-2 text-lg font-semibold text-slate-900">
                    {{ current.title }}
                </h2>

                <p class="mb-6 whitespace-pre-line text-sm leading-relaxed text-slate-600">
                    {{ current.content }}
                </p>

                <div class="flex items-center justify-between">
                    <span v-if="issues.length > 1" class="text-xs text-slate-400">
                        {{ currentIndex + 1 }} dari {{ issues.length }}
                    </span>
                    <span v-else></span>

                    <button
                        type="button"
                        :disabled="isDismissing"
                        @click="dismiss"
                        class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700 disabled:opacity-50"
                    >
                        Mengerti
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>