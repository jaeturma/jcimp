<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const events = ref([]);
const loading = ref(true);

const filters = ref({ search: '', is_active: '' });
const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
const perPage = ref(10);

const showModal = ref(false);
const editingEvent = ref(null);
const form = ref({ name: '', description: '', venue: '', event_date: '', is_active: true });
const formError = ref('');
const formSaving = ref(false);
const deleting = ref(false);

const coverFile = ref(null);
const coverUploading = ref(false);
const coverMsg = ref('');

const page = usePage();
const canModifyEvents = computed(() => !!page.props.auth?.isAdmin);
const canViewEvents = computed(() => !!(page.props.auth?.isAdmin || page.props.auth?.isManager));
const isEditing = computed(() => !!editingEvent.value);

onMounted(() => load());
watch([filters, perPage], () => { pagination.value.current_page = 1; load(); }, { deep: true });

async function load(page = 1) {
    loading.value = true;
    try {
        const res = await axios.get('/api/admin/events', {
            params: {
                page,
                per_page: perPage.value,
                ...filters.value,
            },
        });
        events.value = res.data.data;
        pagination.value.current_page = res.data.current_page;
        pagination.value.last_page = res.data.last_page;
        pagination.value.total = res.data.total;
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    if (!canModifyEvents.value) {
        formError.value = 'You do not have permission to create events.';
        return;
    }

    editingEvent.value = null;
    form.value = { name: '', description: '', venue: '', event_date: '', is_active: true };
    formError.value = '';
    coverFile.value = null;
    coverMsg.value = '';
    showModal.value = true;
}

function openEdit(event) {
    if (!canModifyEvents.value) {
        formError.value = 'You do not have permission to edit events.';
        return;
    }

    editingEvent.value = event;
    form.value = { ...event, event_date: event.event_date ? event.event_date.split('T')[0] : '' };
    formError.value = '';
    coverFile.value = null;
    coverMsg.value = '';
    showModal.value = true;
}

async function save() {
    formError.value = '';
    if (!form.value.name || !form.value.event_date) {
        formError.value = 'Name and event date are required.';
        return;
    }
    formSaving.value = true;
    try {
        const payload = {
            name: form.value.name,
            description: form.value.description,
            venue: form.value.venue,
            event_date: form.value.event_date,
            is_active: form.value.is_active,
        };

        let savedEvent;
        if (isEditing.value) {
            await axios.put(`/api/admin/events/${editingEvent.value.id}`, payload);
            savedEvent = editingEvent.value;
        } else {
            const res = await axios.post('/api/admin/events', payload);
            savedEvent = res.data.event || res.data;
        }

        // Upload cover if file is selected
        if (coverFile.value && savedEvent?.id) {
            await uploadCover(savedEvent);
        }

        showModal.value = false;
        await load(pagination.value.current_page);
    } catch (e) {
        formError.value = e.response?.data?.message ?? 'Failed to save event.';
    } finally {
        formSaving.value = false;
    }
}

async function removeEvent(event) {
    if (!confirm(`Delete event "${event.name}"?`)) return;
    deleting.value = true;
    try {
        await axios.delete(`/api/admin/events/${event.id}`);
        await load(pagination.value.current_page);
    } catch (e) {
        alert(e.response?.data?.message ?? 'Delete failed.');
    } finally {
        deleting.value = false;
    }
}

async function uploadCover(event) {
    if (!coverFile.value || !event?.id) {
        coverMsg.value = 'Please choose a file first.';
        return;
    }
    coverUploading.value = true;
    coverMsg.value = '';
    const fd = new FormData();
    fd.append('cover', coverFile.value);

    try {
        const res = await axios.post(`/api/admin/events/${event.id}/cover`, fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        coverMsg.value = 'Cover image uploaded.';
        event.cover_url = res.data.cover_url;
        event.cover_image = res.data.cover_image;
        coverFile.value = null; // Clear the file after successful upload
    } catch (e) {
        coverMsg.value = e.response?.data?.message ?? 'Upload failed.';
    } finally {
        coverUploading.value = false;
    }
}

const propertyStatus = (event) => event.is_active ? 'Active' : 'Inactive';
</script>

<template>
    <Head title="Manage Events" />
    <AppLayout>
        <div class="page-header">
            <h1 class="page-title">Manage Events</h1>
            <div class="page-actions">
                <CButton v-if="canModifyEvents" color="primary" @click="openCreate">
                    <CIcon icon="cil-plus" class="me-1" /> New Event
                </CButton>
            </div>
        </div>

        <CCard class="mb-3">
            <CCardBody>
                <CRow class="g-2 align-items-center">
                    <CCol md="4">
                        <CFormInput v-model="filters.search" placeholder="Search events..." />
                    </CCol>
                    <CCol md="3">
                        <CFormSelect v-model="filters.is_active">
                            <option value="">All Statuses</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </CFormSelect>
                    </CCol>
                    <CCol md="3">
                        <CFormSelect v-model="perPage">
                            <option :value="5">5 per page</option>
                            <option :value="10">10 per page</option>
                            <option :value="25">25 per page</option>
                            <option :value="50">50 per page</option>
                        </CFormSelect>
                    </CCol>
                    <CCol md="2" class="text-end">
                        <span class="text-muted">{{ pagination.total }} total</span>
                    </CCol>
                </CRow>
            </CCardBody>
        </CCard>

        <CCard>
            <CCardBody class="p-0">
                <div v-if="loading" class="py-5 text-center text-muted">
                    <CSpinner /> Loading events…
                </div>

                <div v-else class="table-responsive">
                    <CTable hover striped class="mb-0">
                        <CTableHead>
                            <CTableRow>
                                <CTableHeaderCell>Name</CTableHeaderCell>
                                <CTableHeaderCell>Venue</CTableHeaderCell>
                                <CTableHeaderCell>Date</CTableHeaderCell>
                                <CTableHeaderCell>Status</CTableHeaderCell>
                                <CTableHeaderCell>Cover</CTableHeaderCell>
                                <CTableHeaderCell class="text-end">Actions</CTableHeaderCell>
                            </CTableRow>
                        </CTableHead>
                        <CTableBody>
                            <CTableRow v-for="event in events" :key="event.id">
                                <CTableDataCell>{{ event.name }}</CTableDataCell>
                                <CTableDataCell>{{ event.venue || '—' }}</CTableDataCell>
                                <CTableDataCell>{{ new Date(event.event_date).toLocaleDateString() }}</CTableDataCell>
                                <CTableDataCell>
                                    <CBadge :color="event.is_active ? 'success' : 'secondary'" class="text-capitalize">
                                        {{ propertyStatus(event) }}
                                    </CBadge>
                                </CTableDataCell>
                                <CTableDataCell>
                                    <div v-if="event.cover_url" class="d-flex align-items-center gap-2">
                                        <img :src="event.cover_url" alt="Cover" style="width:48px;height:32px;object-fit:cover;border-radius:.25rem" />
                                        <span class="text-muted small">Uploaded</span>
                                    </div>
                                    <span v-else class="text-muted small">No cover</span>
                                </CTableDataCell>
                                <CTableDataCell class="text-end">
                                    <CButton v-if="canModifyEvents" size="sm" color="info" variant="outline" class="me-1" @click="openEdit(event)">Edit</CButton>
                                    <CButton v-if="canModifyEvents" size="sm" color="danger" variant="outline" @click="removeEvent(event)" :disabled="deleting">Delete</CButton>
                                </CTableDataCell>
                            </CTableRow>
                            <CTableRow v-if="!events.length">
                                <CTableDataCell colspan="6" class="text-center text-muted py-5">
                                    No events found.
                                </CTableDataCell>
                            </CTableRow>
                        </CTableBody>
                    </CTable>
                </div>

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

        <CModal :visible="showModal" @hide="showModal = false" size="lg" alignment="center">
            <CModalHeader>
                <CModalTitle>{{ isEditing ? 'Edit Event' : 'Create Event' }}</CModalTitle>
            </CModalHeader>
            <CModalBody>
                <CAlert v-if="formError" color="danger" class="py-2 mb-3">{{ formError }}</CAlert>
                <CRow class="g-3">
                    <CCol md="6">
                        <CFormLabel>Name</CFormLabel>
                        <CFormInput v-model="form.name" placeholder="Event name" />
                    </CCol>
                    <CCol md="6">
                        <CFormLabel>Date</CFormLabel>
                        <CFormInput type="date" v-model="form.event_date" />
                    </CCol>
                    <CCol md="6">
                        <CFormLabel>Venue</CFormLabel>
                        <CFormInput v-model="form.venue" placeholder="Venue" />
                    </CCol>
                    <CCol md="6">
                        <CFormLabel>Status</CFormLabel>
                        <CFormSelect v-model="form.is_active">
                            <option :value="true">Active</option>
                            <option :value="false">Inactive</option>
                        </CFormSelect>
                    </CCol>
                    <CCol md="12">
                        <CFormLabel>Description</CFormLabel>
                        <CFormTextarea v-model="form.description" rows="3" placeholder="Optional event description" />
                    </CCol>
                </CRow>
                <div class="mt-4">
                    <CFormLabel>Upload Cover (JPG, PNG, WebP — max 4MB)</CFormLabel>
                    <CFormInput type="file" accept="image/jpeg,image/png,image/webp" @change="e => coverFile.value = e.target.files[0]" class="mb-2" />
                    <div class="d-flex align-items-center gap-2">
                        <CButton color="primary" :disabled="coverUploading || !coverFile || formSaving" @click="isEditing ? uploadCover(editingEvent) : null">
                            <CSpinner v-if="coverUploading" size="sm" class="me-1" />
                            {{ coverUploading ? 'Uploading…' : 'Upload Cover' }}
                        </CButton>
                        <small class="text-muted">{{ isEditing ? 'Upload immediately' : 'Cover will be uploaded after saving the event' }}</small>
                    </div>
                    <CAlert v-if="coverMsg" :color="coverMsg.includes('Failed') ? 'danger' : 'success'" class="py-2 mt-2">{{ coverMsg }}</CAlert>
                </div>
            </CModalBody>
            <CModalFooter>
                <CButton color="secondary" variant="outline" @click="showModal = false">Cancel</CButton>
                <CButton color="primary" :disabled="formSaving" @click="save">
                    <CSpinner v-if="formSaving" size="sm" class="me-1" />
                    {{ formSaving ? 'Saving…' : (isEditing ? 'Update Event' : 'Create Event') }}
                </CButton>
            </CModalFooter>
        </CModal>
    </AppLayout>
</template>
