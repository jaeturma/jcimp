<script setup>
import { ref, onMounted, computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const page = usePage();
const auth = computed(() => page.props.auth);

const permissions = ref([]);
const users = ref([]);
const loading = ref(true);

const showAssignModal = ref(false);
const form = ref({
    user_id: '',
    permissions: [],
});
const formErrors = ref({});
const saving = ref(false);

const canViewPermissions = computed(() => auth.value?.can?.['view permissions'] ?? false);
const canAssignPermissions = computed(() => auth.value?.can?.['assign permissions'] ?? false);

onMounted(() => {
    loadPermissions();
    loadUsers();
});

async function loadPermissions() {
    try {
        const res = await axios.get('/api/admin/permissions');
        permissions.value = res.data.data;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

async function loadUsers() {
    try {
        const res = await axios.get('/api/admin/users');
        users.value = res.data.data;
    } catch (e) {
        console.error(e);
    }
}

function openAssign() {
    form.value = { user_id: '', permissions: [] };
    formErrors.value = {};
    showAssignModal.value = true;
}

async function assignPermissions() {
    saving.value = true;
    formErrors.value = {};
    try {
        await axios.post('/api/admin/permissions/assign', form.value);
        showAssignModal.value = false;
        // Optionally reload users or show success
    } catch (e) {
        if (e.response?.status === 422) {
            formErrors.value = e.response.data.errors;
        }
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Head title="Permissions" />
    <AppLayout>
        <div class="page-header">
            <h1 class="page-title">Permissions</h1>
        </div>

        <CContainer fluid class="p-0">
            <CCard>
                <CCardHeader class="d-flex align-items-center justify-content-between">
                    <span class="fw-semibold">
                        <CBadge color="secondary" class="me-2">{{ permissions.length }}</CBadge>
                        Permissions
                    </span>
                    <CButton
                        v-if="canAssignPermissions"
                        color="primary"
                        size="sm"
                        @click="openAssign"
                    >
                        <CIcon icon="cil-plus" class="me-1" />
                        Assign Permissions
                    </CButton>
                </CCardHeader>
                <CCardBody class="p-0">
                    <div v-if="loading" class="py-5 text-center text-muted">
                        <CSpinner color="primary" />
                        <p class="mt-2 mb-0">Loading permissions…</p>
                    </div>
                    <div v-else class="table-responsive">
                        <CTable striped hover class="mb-0">
                            <CTableHead>
                                <CTableRow>
                                    <CTableHeaderCell>Name</CTableHeaderCell>
                                    <CTableHeaderCell>Guard</CTableHeaderCell>
                                </CTableRow>
                            </CTableHead>
                            <CTableBody>
                                <CTableRow v-for="perm in permissions" :key="perm.id">
                                    <CTableDataCell class="fw-semibold">{{ perm.name }}</CTableDataCell>
                                    <CTableDataCell>{{ perm.guard_name }}</CTableDataCell>
                                </CTableRow>
                                <CTableRow v-if="!permissions.length">
                                    <CTableDataCell colspan="2" class="text-center text-muted py-5">
                                        No permissions found.
                                    </CTableDataCell>
                                </CTableRow>
                            </CTableBody>
                        </CTable>
                    </div>
                </CCardBody>
            </CCard>

            <!-- Assign Modal -->
            <CModal :visible="showAssignModal" @hide="showAssignModal = false">
                <CModalHeader>
                    <CModalTitle>Assign Permissions</CModalTitle>
                </CModalHeader>
                <CModalBody>
                    <CForm>
                        <CRow class="g-3">
                            <CCol xs="12">
                                <CFormLabel for="user_id">User</CFormLabel>
                                <CFormSelect
                                    id="user_id"
                                    v-model="form.user_id"
                                    :invalid="!!formErrors.user_id"
                                >
                                    <option value="">Select User</option>
                                    <option v-for="user in users" :key="user.id" :value="user.id">
                                        {{ user.name }} ({{ user.email }})
                                    </option>
                                </CFormSelect>
                                <CFormFeedback invalid v-if="formErrors.user_id">
                                    {{ formErrors.user_id[0] }}
                                </CFormFeedback>
                            </CCol>
                            <CCol xs="12">
                                <CFormLabel>Permissions</CFormLabel>
                                <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                    <CFormCheck
                                        v-for="perm in permissions"
                                        :key="perm.id"
                                        :id="'perm-' + perm.id"
                                        v-model="form.permissions"
                                        :value="perm.name"
                                        :label="perm.name"
                                        class="mb-2"
                                    />
                                </div>
                            </CCol>
                        </CRow>
                    </CForm>
                </CModalBody>
                <CModalFooter>
                    <CButton color="secondary" variant="outline" @click="showAssignModal = false">
                        Cancel
                    </CButton>
                    <CButton color="primary" :disabled="saving" @click="assignPermissions">
                        <CSpinner v-if="saving" size="sm" class="me-1" />
                        Assign
                    </CButton>
                </CModalFooter>
            </CModal>
        </CContainer>
    </AppLayout>
</template>