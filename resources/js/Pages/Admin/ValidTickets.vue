<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const page = usePage();
const auth = computed(() => page.props.auth);
const canScan = computed(() => auth.value?.can?.['scan tickets'] ?? false);

const tickets  = ref([]);
const loading  = ref(true);
const loadError = ref('');
const filters  = ref({ status: '', search: '', ticket_id: '' });
const perPage  = ref(25);
const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
const stats    = ref({ total_issued: 0, total_admitted: 0, total_remaining: 0, admission_rate: 0 });
const availableTickets = ref([]);

onMounted(() => { loadStats(); loadTicketTypes(); });
watch([filters, perPage], () => { pagination.value.current_page = 1; load(1); }, { deep: true, immediate: true });

async function load(p = 1) {
    loading.value  = true;
    loadError.value = '';
    try {
        const res = await axios.get('/api/admin/scan/tickets', {
            params: { ...filters.value, page: p, per_page: perPage.value },
        });
        tickets.value    = res.data.data;
        pagination.value = res.data.meta;
    } catch (e) {
        loadError.value = e.response?.data?.message ?? 'Failed to load tickets.';
    } finally {
        loading.value = false;
    }
}

async function loadStats() {
    try {
        const res = await axios.get('/api/admin/scan/stats');
        stats.value = res.data;
    } catch {}
}

async function loadTicketTypes() {
    try {
        const res = await axios.get('/api/admin/tickets', { params: { per_page: 200 } });
        availableTickets.value = res.data.data ?? res.data;
    } catch {}
}

function statusColor(status) {
    return status === 'valid' ? 'success' : status === 'used' ? 'secondary' : 'danger';
}

function statusLabel(status) {
    return status === 'valid' ? 'Not Scanned' : status === 'used' ? 'Scanned / Admitted' : status;
}

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}
</script>

<template>
    <Head title="Valid Tickets" />
    <AppLayout>
        <div class="page-header">
            <h1 class="page-title">Valid Tickets</h1>
        </div>

        <CContainer fluid class="p-0">

            <!-- Stats Row -->
            <CRow class="g-3 mb-4">
                <CCol xs="6" md="3">
                    <CCard class="text-center h-100">
                        <CCardBody>
                            <div class="text-muted small mb-1">Total Issued</div>
                            <div class="fw-bold fs-3">{{ stats.total_issued }}</div>
                        </CCardBody>
                    </CCard>
                </CCol>
                <CCol xs="6" md="3">
                    <CCard class="text-center h-100 border-success">
                        <CCardBody>
                            <div class="text-muted small mb-1">Not Scanned</div>
                            <div class="fw-bold fs-3 text-success">{{ stats.total_remaining }}</div>
                        </CCardBody>
                    </CCard>
                </CCol>
                <CCol xs="6" md="3">
                    <CCard class="text-center h-100 border-secondary">
                        <CCardBody>
                            <div class="text-muted small mb-1">Admitted</div>
                            <div class="fw-bold fs-3 text-secondary">{{ stats.total_admitted }}</div>
                        </CCardBody>
                    </CCard>
                </CCol>
                <CCol xs="6" md="3">
                    <CCard class="text-center h-100 border-info">
                        <CCardBody>
                            <div class="text-muted small mb-1">Admission Rate</div>
                            <div class="fw-bold fs-3 text-info">{{ stats.admission_rate }}%</div>
                        </CCardBody>
                    </CCard>
                </CCol>
            </CRow>

            <!-- Filters -->
            <CRow class="mb-3 g-2">
                <CCol xs="12" md="4">
                    <CFormInput
                        v-model="filters.search"
                        placeholder="Search QR, reference or email…"
                    />
                </CCol>
                <CCol xs="6" md="3">
                    <CFormSelect v-model="filters.status">
                        <option value="">All statuses</option>
                        <option value="valid">Not Scanned</option>
                        <option value="used">Scanned / Admitted</option>
                    </CFormSelect>
                </CCol>
                <CCol xs="6" md="3">
                    <CFormSelect v-model="filters.ticket_id">
                        <option value="">All ticket types</option>
                        <option v-for="t in availableTickets" :key="t.id" :value="t.id">
                            {{ t.name }}
                        </option>
                    </CFormSelect>
                </CCol>
                <CCol xs="12" md="2">
                    <CFormSelect v-model="perPage">
                        <option :value="25">25 / page</option>
                        <option :value="50">50 / page</option>
                        <option :value="100">100 / page</option>
                    </CFormSelect>
                </CCol>
            </CRow>

            <!-- Table -->
            <CCard>
                <CCardHeader class="d-flex align-items-center justify-content-between">
                    <span class="fw-semibold">
                        <CBadge color="secondary" class="me-2">{{ pagination.total }}</CBadge>
                        Tickets
                    </span>
                    <CButton color="secondary" variant="outline" size="sm" @click="load(); loadStats()">
                        ↺ Refresh
                    </CButton>
                </CCardHeader>
                <CCardBody class="p-0">
                    <div v-if="loading" class="py-5 text-center text-muted">
                        <CSpinner color="primary" />
                        <p class="mt-2 mb-0">Loading…</p>
                    </div>
                    <CAlert v-else-if="loadError" color="danger" class="m-3">{{ loadError }}</CAlert>
                    <div v-else class="table-responsive">
                        <CTable striped hover class="mb-0">
                            <CTableHead>
                                <CTableRow>
                                    <CTableHeaderCell>Ticket Type</CTableHeaderCell>
                                    <CTableHeaderCell>Ticket No.</CTableHeaderCell>
                                    <CTableHeaderCell>Order Ref</CTableHeaderCell>
                                    <CTableHeaderCell>Email</CTableHeaderCell>
                                    <CTableHeaderCell>Status</CTableHeaderCell>
                                    <CTableHeaderCell>Scanned At</CTableHeaderCell>
                                </CTableRow>
                            </CTableHead>
                            <CTableBody>
                                <CTableRow
                                    v-for="t in tickets"
                                    :key="t.id"
                                    :class="t.status === 'used' ? 'table-secondary' : ''"
                                >
                                    <CTableDataCell>
                                        <div class="fw-semibold">{{ t.ticket?.name ?? '—' }}</div>
                                        <div class="text-muted small text-capitalize">{{ t.ticket?.type }}</div>
                                    </CTableDataCell>
                                    <CTableDataCell>
                                        <span class="font-monospace small">
                                            {{ t.qr_code.slice(-12).toUpperCase() }}
                                        </span>
                                    </CTableDataCell>
                                    <CTableDataCell class="font-monospace small text-primary">
                                        {{ t.order?.reference }}
                                    </CTableDataCell>
                                    <CTableDataCell class="text-muted small">
                                        {{ t.holder_email ?? t.order?.email }}
                                    </CTableDataCell>
                                    <CTableDataCell>
                                        <CBadge :color="statusColor(t.status)">
                                            {{ statusLabel(t.status) }}
                                        </CBadge>
                                    </CTableDataCell>
                                    <CTableDataCell class="text-muted small">
                                        {{ formatDate(t.used_at) }}
                                    </CTableDataCell>
                                </CTableRow>
                                <CTableRow v-if="!tickets.length">
                                    <CTableDataCell colspan="6" class="text-center text-muted py-5">
                                        No tickets found.
                                    </CTableDataCell>
                                </CTableRow>
                            </CTableBody>
                        </CTable>
                    </div>

                    <!-- Pagination -->
                    <div v-if="pagination.last_page > 1" class="d-flex justify-content-center gap-1 px-3 py-3 border-top flex-wrap">
                        <CButton
                            v-for="p in pagination.last_page"
                            :key="p"
                            size="sm"
                            :color="p === pagination.current_page ? 'primary' : 'secondary'"
                            :variant="p === pagination.current_page ? undefined : 'outline'"
                            @click="load(p)"
                        >
                            {{ p }}
                        </CButton>
                    </div>
                </CCardBody>
            </CCard>

        </CContainer>
    </AppLayout>
</template>
