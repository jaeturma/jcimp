<script setup>
import { ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const verifications = ref([]);
const loading       = ref(true);
const loadError     = ref('');
const selected      = ref(null);
const detailLoading = ref(false);
const rejectPanel   = ref(false);
const rejectReason  = ref('');
const submitting    = ref(false);

const filters    = ref({ status: '', search: '' });
const perPage    = ref(10);
const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
const statusOpts = ['', 'pending', 'approved', 'rejected'];

watch([filters, perPage], () => { pagination.value.current_page = 1; load(1); }, { deep: true, immediate: true });

async function load(page = 1) {
    loading.value = true;
    loadError.value = '';
    try {
        const res = await axios.get('/api/admin/student-verifications', {
            params: { ...filters.value, page, per_page: perPage.value },
        });
        verifications.value = res.data.data;
        pagination.value    = res.data.meta ?? { current_page: 1, last_page: 1, total: 0 };
    } catch (e) {
        loadError.value = e.response?.data?.message ?? e.response?.statusText ?? 'Failed to load verifications.';
        console.error('Verifications load error:', e.response?.status, e.response?.data);
    } finally {
        loading.value = false;
    }
}

async function viewVerification(v) {
    selected.value    = null;
    rejectPanel.value = false;
    rejectReason.value = '';
    detailLoading.value = true;
    try {
        const res = await axios.get(`/api/admin/student-verifications/${v.id}`);
        selected.value = res.data.data;
    } finally {
        detailLoading.value = false;
    }
}

async function approve() {
    if (!selected.value || submitting.value) return;
    submitting.value = true;
    try {
        await axios.post(`/api/admin/student-verifications/${selected.value.id}/review`, { action: 'approve' });
        selected.value = null;
        load();
    } catch (e) {
        alert(e.response?.data?.message ?? 'Error approving.');
    } finally {
        submitting.value = false;
    }
}

async function reject() {
    if (!selected.value || !rejectReason.value.trim() || submitting.value) return;
    submitting.value = true;
    try {
        await axios.post(`/api/admin/student-verifications/${selected.value.id}/review`, {
            action: 'reject',
            reason: rejectReason.value,
        });
        selected.value     = null;
        rejectPanel.value  = false;
        rejectReason.value = '';
        load();
    } catch (e) {
        alert(e.response?.data?.message ?? 'Error rejecting.');
    } finally {
        submitting.value = false;
    }
}

const statusColor = (s) => ({ pending: 'warning', approved: 'success', rejected: 'danger' }[s] ?? 'secondary');
const typeColor   = (t) => t === 'college' ? 'info' : 'primary';
</script>

<template>
    <Head title="Student Verifications" />
    <AppLayout>

        <div class="page-header">
            <h1 class="page-title">Student Verifications</h1>
        </div>

        <!-- ── Filters ─────────────────────────────────────────────────────── -->
        <CCard class="mb-3">
            <CCardBody>
                <CRow class="g-2 align-items-center">
                    <CCol xs="12" md="5">
                        <CFormInput
                            v-model="filters.search"
                            placeholder="Search name, email, or LRN…"
                        />
                    </CCol>
                    <CCol xs="6" md="3">
                        <CFormSelect v-model="filters.status">
                            <option v-for="s in statusOpts" :key="s" :value="s">
                                {{ s ? s.charAt(0).toUpperCase() + s.slice(1) : 'All statuses' }}
                            </option>
                        </CFormSelect>
                    </CCol>
                    <CCol xs="6" md="2">
                        <CFormSelect v-model="perPage">
                            <option :value="5">5 per page</option>
                            <option :value="10">10 per page</option>
                            <option :value="25">25 per page</option>
                            <option :value="50">50 per page</option>
                        </CFormSelect>
                    </CCol>
                    <CCol xs="12" md="2" class="text-end">
                        <span class="text-muted small">{{ pagination.total ?? 0 }} total</span>
                    </CCol>
                </CRow>
            </CCardBody>
        </CCard>

        <!-- ── Table ──────────────────────────────────────────────────────── -->
        <CCard>
            <CCardBody class="p-0">
                <div v-if="loading" class="py-5 text-center text-muted">
                    <CSpinner color="primary" />
                    <p class="mt-2 mb-0">Loading verifications…</p>
                </div>
                <CAlert v-else-if="loadError" color="danger" class="m-3">
                    <strong>Error loading verifications:</strong> {{ loadError }}
                </CAlert>
                <div v-else class="table-responsive">
                    <CTable hover striped class="mb-0 align-middle">
                        <CTableHead class="table-light">
                            <CTableRow>
                                <CTableHeaderCell>Name</CTableHeaderCell>
                                <CTableHeaderCell>Account Email</CTableHeaderCell>
                                <CTableHeaderCell>Type</CTableHeaderCell>
                                <CTableHeaderCell>LRN</CTableHeaderCell>
                                <CTableHeaderCell>Status</CTableHeaderCell>
                                <CTableHeaderCell>Submitted</CTableHeaderCell>
                                <CTableHeaderCell></CTableHeaderCell>
                            </CTableRow>
                        </CTableHead>
                        <CTableBody>
                            <CTableRow v-for="v in verifications" :key="v.id">
                                <CTableDataCell class="fw-medium">{{ v.user_name }}</CTableDataCell>
                                <CTableDataCell class="text-muted">{{ v.user_email }}</CTableDataCell>
                                <CTableDataCell>
                                    <CBadge :color="typeColor(v.student_type)" shape="rounded-pill" class="text-capitalize">
                                        {{ v.student_type }}
                                    </CBadge>
                                </CTableDataCell>
                                <CTableDataCell class="font-monospace small text-muted">
                                    {{ v.lrn_number ?? '—' }}
                                </CTableDataCell>
                                <CTableDataCell>
                                    <CBadge :color="statusColor(v.status)" shape="rounded-pill" class="text-capitalize">
                                        {{ v.status }}
                                    </CBadge>
                                </CTableDataCell>
                                <CTableDataCell class="text-muted text-nowrap">
                                    {{ new Date(v.created_at).toLocaleDateString() }}
                                </CTableDataCell>
                                <CTableDataCell>
                                    <CButton size="sm" color="primary" variant="outline" @click="viewVerification(v)">
                                        Review
                                    </CButton>
                                </CTableDataCell>
                            </CTableRow>
                            <CTableRow v-if="!verifications.length">
                                <CTableDataCell colspan="7" class="text-center text-muted py-5">
                                    No verification requests found.
                                </CTableDataCell>
                            </CTableRow>
                        </CTableBody>
                    </CTable>
                </div>

                <!-- Pagination -->
                <div v-if="pagination.last_page > 1" class="d-flex justify-content-center gap-1 px-3 py-3 border-top">
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

        <!-- ── Detail Modal ───────────────────────────────────────────────── -->
        <CModal
            :visible="!!(selected || detailLoading)"
            @hide="selected = null; rejectPanel = false; rejectReason = ''"
            size="lg"
            scrollable
            alignment="center"
        >
            <CModalHeader>
                <CModalTitle>Verification Detail</CModalTitle>
            </CModalHeader>

            <CModalBody>
                <!-- Loading -->
                <div v-if="detailLoading" class="py-5 text-center text-muted">
                    <CSpinner color="primary" />
                    <p class="mt-2 mb-0">Loading…</p>
                </div>

                <template v-else-if="selected">
                    <!-- Status + Type badges -->
                    <div class="d-flex gap-2 mb-4">
                        <CBadge :color="statusColor(selected.status)" class="text-capitalize">
                            {{ selected.status }}
                        </CBadge>
                        <CBadge :color="typeColor(selected.student_type)" class="text-capitalize">
                            {{ selected.student_type }}
                        </CBadge>
                    </div>

                    <!-- Info grid -->
                    <CRow class="g-3 bg-body-secondary rounded p-3 mb-4">
                        <CCol xs="6">
                            <p class="text-muted small mb-1">Name</p>
                            <p class="fw-medium mb-0">{{ selected.user_name }}</p>
                        </CCol>
                        <CCol xs="6">
                            <p class="text-muted small mb-1">Account Email</p>
                            <p class="fw-medium mb-0">{{ selected.user_email }}</p>
                        </CCol>
                        <CCol xs="6">
                            <p class="text-muted small mb-1">School Email</p>
                            <p class="fw-medium mb-0">{{ selected.school_email }}</p>
                        </CCol>
                        <CCol v-if="selected.lrn_number" xs="6">
                            <p class="text-muted small mb-1">LRN</p>
                            <p class="font-monospace fw-medium mb-0">{{ selected.lrn_number }}</p>
                        </CCol>
                        <CCol v-if="selected.reviewed_at" xs="6">
                            <p class="text-muted small mb-1">Reviewed by</p>
                            <p class="fw-medium mb-0">{{ selected.reviewer_name ?? '—' }}</p>
                        </CCol>
                        <CCol v-if="selected.rejection_reason" xs="12">
                            <p class="text-muted small mb-1">Rejection Reason</p>
                            <p class="text-danger mb-0">{{ selected.rejection_reason }}</p>
                        </CCol>
                    </CRow>

                    <!-- Student ID image -->
                    <template v-if="selected.id_image_url">
                        <h6 class="fw-semibold mb-2">Student ID</h6>
                        <div class="border rounded bg-body-secondary mb-1 overflow-auto text-center"
                            style="max-height:480px;">
                            <img :src="selected.id_image_url" alt="Student ID"
                                style="width:100%;height:auto;display:block;" />
                        </div>
                        <p class="text-muted small">Link expires in 10 minutes.
                            <a :href="selected.id_image_url" target="_blank">Open full size</a>
                        </p>
                    </template>
                    <p v-else-if="selected.student_type === 'college'" class="text-muted small">
                        College email — no ID required (auto-verified via .edu.ph).
                    </p>

                    <!-- Reject form (inline toggle) -->
                    <template v-if="selected.status === 'pending' && rejectPanel">
                        <hr>
                        <CFormLabel class="fw-semibold text-danger">Rejection Reason <span class="text-danger">*</span></CFormLabel>
                        <CFormTextarea
                            v-model="rejectReason"
                            rows="3"
                            placeholder="State the reason for rejection…"
                            class="mb-3"
                        />
                    </template>
                </template>
            </CModalBody>

            <CModalFooter v-if="selected">
                <!-- Pending actions -->
                <template v-if="selected.status === 'pending'">
                    <template v-if="!rejectPanel">
                        <CButton color="secondary" variant="outline"
                            @click="selected = null; rejectPanel = false">
                            Close
                        </CButton>
                        <CButton color="danger" variant="outline" @click="rejectPanel = true">
                            ❌ Reject
                        </CButton>
                        <CButton color="success" :disabled="submitting" @click="approve">
                            <CSpinner v-if="submitting" size="sm" class="me-1" />
                            ✅ Approve
                        </CButton>
                    </template>
                    <template v-else>
                        <CButton color="secondary" variant="outline" @click="rejectPanel = false">
                            Cancel
                        </CButton>
                        <CButton color="danger"
                            :disabled="submitting || !rejectReason.trim()"
                            @click="reject">
                            <CSpinner v-if="submitting" size="sm" class="me-1" />
                            Confirm Reject
                        </CButton>
                    </template>
                </template>

                <!-- Reviewed — just close -->
                <template v-else>
                    <CButton color="secondary" variant="outline"
                        @click="selected = null; rejectPanel = false">
                        Close
                    </CButton>
                </template>
            </CModalFooter>
        </CModal>

    </AppLayout>
</template>
