<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const tickets = ref([]);
const events = ref([]);
const coverUploading = ref(false);
const coverFile = ref(null);
const coverMsg = ref('');
const selectedEventId = ref(null);
const loading = ref(true);
const loadError = ref('');
const showForm = ref(false);
const saving = ref(false);
const formError = ref('');
const editingId = ref(null);

// GCash QR modal state
const qrModal      = ref(false);
const qrTicket     = ref(null);
const qrFile       = ref(null);
const qrPreview    = ref(null);
const qrUploading  = ref(false);
const qrRemoving   = ref(false);
const qrMsg        = ref('');
const qrErr        = ref('');

const filters = ref({ search: '', event_id: '', status: '' });
const perPage = ref(10);
const pagination = ref({ current_page: 1, last_page: 1, total: 0 });

const page = usePage();
const isAdminOrManager = computed(() => !!(page.props.auth?.isAdmin || page.props.auth?.isManager));
const canCreateDeleteTickets = computed(() => !!page.props.auth?.isAdmin);
const canUpdateTickets = computed(() => !!(page.props.auth?.isAdmin || page.props.auth?.isManager));

const blankForm = () => ({
    event_id: 1,
    name: '',
    price: '',
    total_quantity: '',
    type: 'regular',
    max_per_user: 4,
    requires_verification: false,
});
const form = ref(blankForm());

const selectedEvent = computed(() => events.value.find(e => e.id === selectedEventId.value));

onMounted(() => {
    load();
    loadEvents();
});

watch([filters, perPage], () => {
    pagination.value.current_page = 1;
    load(1);
}, { deep: true });

async function loadEvents() {
    try {
        const res = await axios.get('/api/admin/events', { params: { per_page: 100 } });
        events.value = res.data.data || [];
        if (events.value.length && !selectedEventId.value) {
            selectedEventId.value = events.value[0].id;
        }
    } catch (e) {
        console.warn('Failed to load events', e);
    }
}

async function uploadCover() {
    if (!coverFile.value || !selectedEventId.value) return;
    coverUploading.value = true;
    coverMsg.value = '';
    const fd = new FormData();
    fd.append('cover', coverFile.value);

    try {
        const res = await axios.post(`/api/admin/events/${selectedEventId.value}/cover`, fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        coverMsg.value = 'Cover uploaded successfully!';
        const ev = events.value.find(e => e.id === selectedEventId.value);
        if (ev) {
            ev.cover_url = res.data.cover_url;
            ev.cover_image = res.data.cover_image;
        }
    } catch (e) {
        coverMsg.value = e.response?.data?.message ?? 'Upload failed.';
    } finally {
        coverUploading.value = false;
    }
}

async function load(page = 1) {
    loading.value = true;
    loadError.value = '';
    try {
        const res = await axios.get('/api/admin/tickets', {
            params: {
                page,
                per_page: perPage.value,
                search: filters.value.search,
                event_id: filters.value.event_id,
            },
        });
        tickets.value = res.data.data;
        pagination.value.current_page = res.data.current_page;
        pagination.value.last_page = res.data.last_page;
        pagination.value.total = res.data.total;
    } catch (e) {
        loadError.value = e.response?.data?.message ?? 'Error loading ticket tiers.';
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    if (!canCreateDeleteTickets.value) {
        formError.value = 'You do not have permission to create ticket tiers.';
        return;
    }

    form.value = blankForm();
    editingId.value = null;
    showForm.value = true;
    formError.value = '';
}

function openEdit(ticket) {
    if (!canUpdateTickets.value) {
        formError.value = 'You do not have permission to edit ticket tiers.';
        return;
    }

    form.value = { ...ticket };
    editingId.value = ticket.id;
    showForm.value = true;
    formError.value = '';
}

async function save() {
    saving.value = true;
    formError.value = '';
    try {
        if (editingId.value) {
            await axios.put(`/api/admin/tickets/${editingId.value}`, form.value);
        } else {
            if (!canCreateDeleteTickets.value) {
                throw new Error('You do not have permission to create ticket tiers.');
            }
            await axios.post('/api/admin/tickets', form.value);
        }
        showForm.value = false;
        await load();
    } catch (e) {
        const errors = e.response?.data?.errors;
        formError.value = errors
            ? Object.values(errors).flat().join(' ')
            : (e.response?.data?.message ?? 'Save failed.');
    } finally {
        saving.value = false;
    }
}

async function remove(ticket) {
    if (!canCreateDeleteTickets.value) {
        alert('You do not have permission to delete ticket tiers.');
        return;
    }
    if (!confirm(`Delete "${ticket.name}"?`)) return;
    try {
        await axios.delete(`/api/admin/tickets/${ticket.id}`);
        await load();
    } catch (e) {
        alert(e.response?.data?.message ?? 'Delete failed.');
    }
}

function openQrModal(ticket) {
    qrTicket.value  = ticket;
    qrFile.value    = null;
    qrPreview.value = ticket.gcash_qr_url ?? null;
    qrMsg.value     = '';
    qrErr.value     = '';
    qrModal.value   = true;
}

function onQrFileChange(e) {
    const file = e.target.files[0];
    if (!file) { qrFile.value = null; return; }
    qrFile.value = file;
    const reader = new FileReader();
    reader.onload = (ev) => { qrPreview.value = ev.target.result; };
    reader.readAsDataURL(file);
}

async function uploadQr() {
    if (!qrFile.value) return;
    qrUploading.value = true;
    qrMsg.value = '';
    qrErr.value = '';
    const fd = new FormData();
    fd.append('qr_image', qrFile.value);
    try {
        const res = await axios.post(`/api/admin/tickets/${qrTicket.value.id}/qr`, fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        qrMsg.value = res.data.message;
        // Update local list
        const idx = tickets.value.findIndex(t => t.id === qrTicket.value.id);
        if (idx !== -1) tickets.value[idx].gcash_qr_url = res.data.gcash_qr_url;
        qrTicket.value.gcash_qr_url = res.data.gcash_qr_url;
        qrFile.value = null;
    } catch (e) {
        qrErr.value = e.response?.data?.message ?? 'Upload failed.';
    } finally {
        qrUploading.value = false;
    }
}

async function removeQr() {
    if (!confirm('Remove the GCash QR code for this ticket tier?')) return;
    qrRemoving.value = true;
    qrMsg.value = '';
    qrErr.value = '';
    try {
        await axios.delete(`/api/admin/tickets/${qrTicket.value.id}/qr`);
        qrMsg.value = 'QR code removed.';
        qrPreview.value = null;
        const idx = tickets.value.findIndex(t => t.id === qrTicket.value.id);
        if (idx !== -1) tickets.value[idx].gcash_qr_url = null;
        qrTicket.value.gcash_qr_url = null;
    } catch (e) {
        qrErr.value = e.response?.data?.message ?? 'Remove failed.';
    } finally {
        qrRemoving.value = false;
    }
}
</script>

<template>
    <Head title="Manage Tickets" />
    <AppLayout>

        <!-- GCash QR Modal -->
        <CModal :visible="qrModal" @hide="qrModal = false" alignment="center" size="md">
            <CModalHeader>
                <CModalTitle>GCash QR Code — {{ qrTicket?.name }}</CModalTitle>
            </CModalHeader>
            <CModalBody>
                <CAlert v-if="qrErr" color="danger" class="py-2 mb-3">{{ qrErr }}</CAlert>
                <CAlert v-if="qrMsg" color="success" class="py-2 mb-3">{{ qrMsg }}</CAlert>

                <!-- Current QR preview -->
                <div class="text-center mb-4">
                    <div v-if="qrPreview" class="d-inline-block position-relative">
                        <img :src="qrPreview" alt="GCash QR Code"
                            class="border border-2 rounded p-2"
                            style="max-width:220px;max-height:220px;object-fit:contain" />
                        <div class="mt-2">
                            <a
                                v-if="qrTicket?.gcash_qr_url"
                                :href="qrTicket.gcash_qr_url"
                                download
                                class="btn btn-sm btn-outline-primary me-2"
                            >
                                ⬇ Download QR
                            </a>
                            <CButton size="sm" color="danger" variant="outline" :disabled="qrRemoving" @click="removeQr">
                                <CSpinner v-if="qrRemoving" size="sm" class="me-1" />
                                Remove
                            </CButton>
                        </div>
                    </div>
                    <div v-else class="border rounded d-flex align-items-center justify-content-center bg-light text-muted"
                        style="height:160px;width:160px;margin:0 auto;font-size:.85rem">
                        No QR uploaded
                    </div>
                </div>

                <hr class="my-3" />

                <!-- Upload new QR -->
                <CFormLabel class="fw-semibold">Upload / Replace GCash QR</CFormLabel>
                <CFormInput
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    class="mb-2"
                    @change="onQrFileChange"
                />
                <div class="form-text mb-3">Upload the GCash QR code image (JPG, PNG, WebP — max 4 MB). Users will see this on the payment page to scan and pay.</div>
            </CModalBody>
            <CModalFooter>
                <CButton color="secondary" variant="outline" @click="qrModal = false">Close</CButton>
                <CButton color="primary" :disabled="qrUploading || !qrFile" @click="uploadQr">
                    <CSpinner v-if="qrUploading" size="sm" class="me-1" />
                    {{ qrUploading ? 'Uploading…' : 'Save QR Code' }}
                </CButton>
            </CModalFooter>
        </CModal>

        <!-- Add / Edit Modal -->
        <CModal :visible="showForm" @hide="showForm = false" alignment="center" size="lg">
            <CModalHeader>
                <CModalTitle>{{ editingId ? 'Edit Ticket Tier' : 'New Ticket Tier' }}</CModalTitle>
            </CModalHeader>
            <CModalBody>
                <CAlert v-if="formError" color="danger" class="py-2">{{ formError }}</CAlert>

                <div class="mb-3">
                    <CFormLabel class="fw-semibold">Tier Name</CFormLabel>
                    <CFormInput v-model="form.name" placeholder="e.g. VIP, Gold, General Admission" />
                </div>

                <CRow class="g-3 mb-3">
                    <CCol :md="6">
                        <CFormLabel class="fw-semibold">Price (₱)</CFormLabel>
                        <CFormInput v-model="form.price" type="number" min="0" step="0.01" placeholder="0.00" />
                    </CCol>
                    <CCol :md="6">
                        <CFormLabel class="fw-semibold">Total Quantity</CFormLabel>
                        <CFormInput v-model="form.total_quantity" type="number" min="1" placeholder="100" />
                    </CCol>
                </CRow>

                <CRow class="g-3 mb-3">
                    <CCol :md="6">
                        <CFormLabel class="fw-semibold">Ticket Type</CFormLabel>
                        <CFormSelect v-model="form.type">
                            <option value="regular">Regular</option>
                            <option value="student">Student</option>
                        </CFormSelect>
                    </CCol>
                    <CCol :md="6">
                        <CFormLabel class="fw-semibold">Max Per Person</CFormLabel>
                        <CFormInput v-model="form.max_per_user" type="number" min="1" max="10" />
                    </CCol>
                </CRow>

                <CFormCheck
                    v-model="form.requires_verification"
                    id="requiresVerification"
                    label="Requires student ID verification to purchase"
                />
            </CModalBody>
            <CModalFooter>
                <CButton color="secondary" variant="outline" @click="showForm = false">
                    Cancel
                </CButton>
                <CButton color="primary" :disabled="saving" @click="save">
                    <CSpinner v-if="saving" size="sm" class="me-1" />
                    {{ saving ? 'Saving…' : (editingId ? 'Update Tier' : 'Create Tier') }}
                </CButton>
            </CModalFooter>
        </CModal>

        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Ticket Tiers</h1>
            <div class="page-actions">
                <CButton v-if="canCreateDeleteTickets" color="primary" @click="openCreate">
                    <CIcon icon="cil-plus" class="me-1" /> Add Tier
                </CButton>
                <span v-else class="text-muted small">Managers can view and edit ticket tiers (no create/delete rights).</span>
            </div>
        </div>

        <!-- Event Cover Upload -->
        <CCard class="shadow-sm mb-4">
            <CCardHeader class="fw-semibold">Event Ticket Cover Image</CCardHeader>
            <CCardBody>
                <CRow class="align-items-center g-3">
                    <CCol :md="3">
                        <div v-if="selectedEvent?.cover_url" class="border rounded overflow-hidden" style="max-height:160px">
                            <img :src="selectedEvent.cover_url" class="img-fluid w-100" style="object-fit:cover;max-height:160px" alt="Cover" />
                        </div>
                        <div v-else class="border rounded d-flex align-items-center justify-content-center bg-light text-muted" style="height:120px;font-size:.85rem">
                            No cover uploaded
                        </div>
                    </CCol>
                    <CCol :md="9">
                        <div v-if="events.length > 1" class="mb-2">
                            <CFormLabel>Event</CFormLabel>
                            <CFormSelect v-model="selectedEventId">
                                <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                            </CFormSelect>
                        </div>
                        <CFormLabel class="fw-semibold">Upload Cover (JPG, PNG, WebP — max 4MB)</CFormLabel>
                        <CFormInput type="file" accept="image/jpeg,image/png,image/webp" @change="e => coverFile = e.target.files[0]" class="mb-2" />
                        <CAlert v-if="coverMsg" :color="coverMsg.includes('success') ? 'success' : 'danger'" class="py-2 mb-2">{{ coverMsg }}</CAlert>
                        <CButton color="primary" :disabled="coverUploading || !coverFile" @click="uploadCover">
                            <CSpinner v-if="coverUploading" size="sm" class="me-1" />
                            {{ coverUploading ? 'Uploading…' : 'Upload Cover' }}
                        </CButton>
                    </CCol>
                </CRow>
            </CCardBody>
        </CCard>

        <!-- Table Controls -->
        <CCard class="mb-4">
            <CCardBody>
                <CRow class="g-3 align-items-center">
                    <CCol md="4">
                        <CFormInput v-model="filters.search" placeholder="Search by ticket, type, or event" />
                    </CCol>
                    <CCol md="4">
                        <CFormSelect v-model="filters.event_id">
                            <option value="">All Events</option>
                            <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                        </CFormSelect>
                    </CCol>
                    <CCol md="2">
                        <CFormSelect v-model="perPage">
                            <option :value="5">5</option>
                            <option :value="10">10</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                        </CFormSelect>
                    </CCol>
                    <CCol md="2" class="text-end">
                        <span class="text-muted">{{ pagination.total }} items</span>
                    </CCol>
                </CRow>
            </CCardBody>
        </CCard>

        <!-- Table Card -->
        <CCard class="shadow-sm">
            <CCardBody class="p-0">
                <div v-if="loading" class="py-5 text-center text-muted">
                    <CSpinner color="primary" size="sm" />
                    <span class="ms-2">Loading ticket tiers…</span>
                </div>

                <CAlert v-else-if="loadError" color="danger" class="m-3">{{ loadError }}</CAlert>

                <div v-else class="table-responsive">
                    <CTable hover class="mb-0 align-middle">
                        <CTableHead class="table-light">
                            <CTableRow>
                                <CTableHeaderCell class="fw-semibold">Name</CTableHeaderCell>
                                <CTableHeaderCell class="fw-semibold">Price</CTableHeaderCell>
                                <CTableHeaderCell class="fw-semibold">Type</CTableHeaderCell>
                                <CTableHeaderCell class="fw-semibold text-center">Sold</CTableHeaderCell>
                                <CTableHeaderCell class="fw-semibold text-center">Reserved</CTableHeaderCell>
                                <CTableHeaderCell class="fw-semibold text-center">Available</CTableHeaderCell>
                                <CTableHeaderCell class="fw-semibold">Revenue</CTableHeaderCell>
                                <CTableHeaderCell class="fw-semibold text-center">GCash QR</CTableHeaderCell>
                                <CTableHeaderCell style="width:160px"></CTableHeaderCell>
                            </CTableRow>
                        </CTableHead>
                        <CTableBody>
                            <CTableRow v-for="t in tickets" :key="t.id">
                                <CTableDataCell class="fw-semibold">{{ t.name }}</CTableDataCell>
                                <CTableDataCell>₱{{ Number(t.price).toLocaleString() }}</CTableDataCell>
                                <CTableDataCell>
                                    <CBadge
                                        :color="t.type === 'student' ? 'info' : 'secondary'"
                                        shape="rounded-pill"
                                        class="text-capitalize"
                                    >
                                        {{ t.type }}
                                    </CBadge>
                                </CTableDataCell>
                                <CTableDataCell class="text-center text-success fw-semibold">{{ t.sold_quantity }}</CTableDataCell>
                                <CTableDataCell class="text-center text-warning fw-semibold">{{ t.reserved_quantity }}</CTableDataCell>
                                <CTableDataCell class="text-center">{{ t.available }}</CTableDataCell>
                                <CTableDataCell class="fw-semibold text-primary">₱{{ t.revenue }}</CTableDataCell>
                                <CTableDataCell class="text-center">
                                    <CBadge v-if="t.gcash_qr_url" color="success" shape="rounded-pill">Set</CBadge>
                                    <CBadge v-else color="secondary" shape="rounded-pill">None</CBadge>
                                </CTableDataCell>
                                <CTableDataCell>
                                    <div class="d-flex gap-1 flex-wrap">
                                        <CButton color="info" variant="outline" size="sm" @click="openQrModal(t)">
                                            GCash QR
                                        </CButton>
                                        <CButton v-if="canUpdateTickets" color="secondary" variant="outline" size="sm" @click="openEdit(t)">
                                            Edit
                                        </CButton>
                                        <CButton v-if="canCreateDeleteTickets" color="danger" variant="ghost" size="sm" @click="remove(t)">
                                            Del
                                        </CButton>
                                    </div>
                                </CTableDataCell>
                            </CTableRow>
                            <CTableRow v-if="!tickets.length">
                                <CTableDataCell colspan="9" class="text-center text-muted py-5">
                                    No ticket tiers yet. Click <strong>Add Tier</strong> to create one.
                                </CTableDataCell>
                            </CTableRow>
                        </CTableBody>
                    </CTable>
                </div>

                <!-- Pagination -->
                <div v-if="pagination.last_page > 1" class="d-flex justify-content-center gap-2 px-3 py-3 border-top">
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

    </AppLayout>
</template>
