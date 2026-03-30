<script setup>
import { defineProps } from 'vue';

const props = defineProps({
    loading: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <button
        :disabled="disabled || loading"
        class="inline-flex items-center justify-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:bg-gray-900 disabled:cursor-not-allowed disabled:opacity-50"
    >
        <span v-if="loading" class="mr-2 inline-flex h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
        <span><slot /></span>
    </button>
</template>
