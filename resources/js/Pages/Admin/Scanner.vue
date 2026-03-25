<script setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const qrInput   = ref('');
const scanning  = ref(false);
const result    = ref(null);
const stats     = ref(null);
const history   = ref([]);
const inputRef  = ref(null);

onMounted(() => {
    loadStats();
    inputRef.value?.focus();
});

async function loadStats() {
    try {
        const res = await axios.get('/api/admin/scan/stats');
        stats.value = res.data;
    } catch {}
}

async function scan() {
    const code = qrInput.value.trim();
    if (!code || scanning.value) return;

    scanning.value = true;
    result.value = null;

    try {
        const res = await axios.post('/api/admin/scan', { qr_code: code });
        result.value = { ...res.data, ts: new Date() };
        history.value.unshift({ code: code.slice(-12), ...res.data, ts: new Date() });
        if (history.value.length > 20) history.value.pop();
        await loadStats();
    } catch (e) {
        const data = e.response?.data ?? {};
        result.value = { ...data, valid: false, ts: new Date() };
        history.value.unshift({ code: code.slice(-12), ...data, valid: false, ts: new Date() });
        if (history.value.length > 20) history.value.pop();
    } finally {
        scanning.value = false;
        qrInput.value = '';
        inputRef.value?.focus();
    }
}

function handleEnter(e) {
    if (e.key === 'Enter') scan();
}

const resultStyle = (r) => {
    if (!r) return '';
    if (r.status === 'admitted')   return 'bg-green-50 border-green-300';
    if (r.status === 'already_used') return 'bg-amber-50 border-amber-300';
    return 'bg-red-50 border-red-300';
};

const resultIcon = (r) => {
    if (!r) return '';
    if (r.status === 'admitted')     return '✅';
    if (r.status === 'already_used') return '⚠️';
    return '❌';
};

const historyBadge = (r) => {
    if (r.status === 'admitted')     return 'bg-green-100 text-green-700';
    if (r.status === 'already_used') return 'bg-amber-100 text-amber-700';
    return 'bg-red-100 text-red-600';
};

// CoreUI color helper for CBadge / CAlert (does not change historyBadge logic above)
const historyBadgeColor = (r) => {
    if (r.status === 'admitted')     return 'success';
    if (r.status === 'already_used') return 'warning';
    return 'danger';
};

const resultAlertColor = (r) => {
    if (!r) return 'secondary';
    if (r.status === 'admitted')     return 'success';
    if (r.status === 'already_used') return 'warning';
    return 'danger';
};
</script>

<template>
    <Head title="Ticket Scanner" />
    <AppLayout>
        <div class="page-header">
            <h1 class="page-title">Ticket Scanner</h1>
        </div>

        <CContainer fluid class="p-0">
            <CRow class="g-4">

                <!-- Left: Scanner Input -->
                <CCol xs="12" lg="6">

                    <!-- Stats Cards -->
                    <CRow v-if="stats" class="g-3 mb-4">
                        <CCol xs="4">
                            <CCard class="text-center h-100">
                                <CCardBody class="py-3">
                                    <p class="h3 fw-bold mb-0">{{ stats.total_admitted }}</p>
                                    <p class="text-muted small mb-0">Admitted</p>
                                </CCardBody>
                            </CCard>
                        </CCol>
                        <CCol xs="4">
                            <CCard class="text-center h-100">
                                <CCardBody class="py-3">
                                    <p class="h3 fw-bold mb-0">{{ stats.total_remaining }}</p>
                                    <p class="text-muted small mb-0">Remaining</p>
                                </CCardBody>
                            </CCard>
                        </CCol>
                        <CCol xs="4">
                            <CCard class="text-center h-100">
                                <CCardBody class="py-3">
                                    <p class="h3 fw-bold text-primary mb-0">{{ stats.admission_rate }}%</p>
                                    <p class="text-muted small mb-0">Rate</p>
                                </CCardBody>
                            </CCard>
                        </CCol>
                    </CRow>

                    <!-- QR Input Card -->
                    <CCard class="mb-4 border-primary border-2 border-dashed">
                        <CCardBody class="text-center py-4">
                            <div class="display-4 mb-3">📷</div>
                            <p class="fw-semibold text-muted mb-3">Scan QR code or enter manually</p>
                            <CFormInput
                                ref="inputRef"
                                v-model="qrInput"
                                @keydown="handleEnter"
                                type="text"
                                placeholder="Scan or paste QR code here…"
                                :disabled="scanning"
                                class="text-center font-monospace mb-3"
                                autofocus
                            />
                            <CButton
                                color="primary"
                                class="w-100"
                                :disabled="scanning || !qrInput.trim()"
                                @click="scan"
                            >
                                <CSpinner v-if="scanning" size="sm" class="me-1" />
                                {{ scanning ? 'Checking…' : 'Validate Ticket' }}
                            </CButton>
                            <p class="text-muted small mt-2 mb-0">Press Enter after scanning</p>
                        </CCardBody>
                    </CCard>

                    <!-- Scan Result -->
                    <transition name="slide">
                        <CAlert
                            v-if="result"
                            :color="resultAlertColor(result)"
                            class="d-flex align-items-start gap-3"
                        >
                            <span class="fs-3 flex-shrink-0">{{ resultIcon(result) }}</span>
                            <div>
                                <p class="fw-bold fs-5 mb-1">{{ result.message }}</p>
                                <template v-if="result.ticket">
                                    <p class="mb-0 small">
                                        <span class="fw-semibold">Ticket:</span> {{ result.ticket.name }}
                                    </p>
                                    <p class="mb-0 small">
                                        <span class="fw-semibold">Email:</span> {{ result.ticket.email }}
                                    </p>
                                    <p class="mb-0 small">
                                        <span class="fw-semibold">Order:</span>
                                        <span class="font-monospace">{{ result.ticket.order }}</span>
                                    </p>
                                </template>
                                <p v-if="result.used_at" class="mb-0 small mt-1">
                                    Used at: {{ new Date(result.used_at).toLocaleString() }}
                                </p>
                            </div>
                        </CAlert>
                    </transition>
                </CCol>

                <!-- Right: Scan History -->
                <CCol xs="12" lg="6">
                    <CCard class="h-100">
                        <CCardHeader class="fw-semibold">Recent Scans</CCardHeader>
                        <CCardBody class="p-0 overflow-auto" style="max-height: 520px;">
                            <div
                                v-for="(entry, i) in history"
                                :key="i"
                                class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom"
                            >
                                <div>
                                    <p class="font-monospace small text-muted mb-0">…{{ entry.code }}</p>
                                    <p class="small text-muted mb-0">{{ entry.ts.toLocaleTimeString() }}</p>
                                </div>
                                <CBadge
                                    :color="historyBadgeColor(entry)"
                                    class="text-capitalize"
                                >
                                    {{ entry.status?.replace('_', ' ') ?? 'error' }}
                                </CBadge>
                            </div>
                            <div v-if="!history.length" class="text-center text-muted py-5 small">
                                No scans yet. Start scanning tickets.
                            </div>
                        </CCardBody>
                    </CCard>
                </CCol>

            </CRow>
        </CContainer>
    </AppLayout>
</template>

<style scoped>
.slide-enter-active, .slide-leave-active { transition: all 0.25s; }
.slide-enter-from, .slide-leave-to { opacity: 0; transform: translateY(-8px); }

.border-dashed {
    border-style: dashed !important;
}
</style>
