<script setup>
import { ref, onMounted, computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const page = usePage();
const auth = computed(() => page.props.auth);

const roles = ref([]);
const permissions = ref([]);
const loading = ref(true);

const showCreateModal = ref(false);
const showEditModal = ref(false);
const editingRole = ref(null);
const form = ref({
    name: '',
    permissions: [],
});
const formErrors = ref({});
const saving = ref(false);

const canViewRoles = computed(() => auth.value?.can?.['view roles'] ?? false);
const canCreateRoles = computed(() => auth.value?.can?.['create roles'] ?? false);
const canUpdateRoles = computed(() => auth.value?.can?.['update roles'] ?? false);
const canDeleteRoles = computed(() => auth.value?.can?.['delete roles'] ?? false);

onMounted(() => {
    loadRoles();
    loadPermissions();
});

async function loadRoles() {
    try {
        const res = await axios.get('/api/admin/roles');
        roles.value = res.data.data;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

async function loadPermissions() {
    try {
        const res = await axios.get('/api/admin/permissions');
        permissions.value = res.data.data;
    } catch (e) {
        console.error(e);
    }
}

function openCreate() {
    form.value = { name: '', permissions: [] };
    formErrors.value = {};
    showCreateModal.value = true;
}

function openEdit(role) {
    editingRole.value = role;
    form.value = {
        name: role.name,
        permissions: role.permissions?.map(p => p.name) || [],
    };
    formErrors.value = {};
    showEditModal.value = true;
}

async function saveRole() {
    saving.value = true;
    formErrors.value = {};
    try {
        if (editingRole.value) {
            await axios.put(`/api/admin/roles/${editingRole.value.id}`, form.value);
        } else {
            await axios.post('/api/admin/roles', form.value);
        }
        showCreateModal.value = false;
        showEditModal.value = false;
        loadRoles();
    } catch (e) {
        if (e.response?.status === 422) {
            formErrors.value = e.response.data.errors;
        }
    } finally {
        saving.value = false;
    }
}

async function deleteRole(role) {
    if (!confirm('Are you sure you want to delete this role?')) return;
    try {
        await axios.delete(`/api/admin/roles/${role.id}`);
        loadRoles();
    } catch (e) {
        console.error(e);
    }
}
</script>

<template>
    <Head title="Roles" />
    <AppLayout>
        <div class="page-header">
            <h1 class="page-title">Roles</h1>
        </div>

        <CContainer fluid class="p-0">
            <CCard>
                <CCardHeader class="d-flex align-items-center justify-content-between">
                    <span class="fw-semibold">
                        <CBadge color="secondary" class="me-2">{{ roles.length }}</CBadge>
                        Roles
                    </span>
                    <CButton
                        v-if="canCreateRoles"
                        color="primary"
                        size="sm"
                        @click="openCreate"
                    >
                        <CIcon icon="cil-plus" class="me-1" />
                        Create Role
                    </CButton>
                </CCardHeader>
                <CCardBody class="p-0">
                    <div v-if="loading" class="py-5 text-center text-muted">
                        <CSpinner color="primary" />
                        <p class="mt-2 mb-0">Loading roles…</p>
                    </div>
                    <div v-else class="table-responsive">
                        <CTable striped hover class="mb-0">
                            <CTableHead>
                                <CTableRow>
                                    <CTableHeaderCell>Name</CTableHeaderCell>
                                    <CTableHeaderCell>Permissions</CTableHeaderCell>
                                    <CTableHeaderCell></CTableHeaderCell>
                                </CTableRow>
                            </CTableHead>
                            <CTableBody>
                                <CTableRow v-for="role in roles" :key="role.id">
                                    <CTableDataCell class="fw-semibold">{{ role.name }}</CTableDataCell>
                                    <CTableDataCell>
                                        <div class="d-flex flex-wrap gap-1">
                                            <CBadge
                                                v-for="perm in role.permissions"
                                                :key="perm.id"
                                                color="info"
                                                size="sm"
                                            >
                                                {{ perm.name }}
                                            </CBadge>
                                        </div>
                                    </CTableDataCell>
                                    <CTableDataCell>
                                        <CButton
                                            v-if="canUpdateRoles"
                                            color="warning"
                                            size="sm"
                                            variant="outline"
                                            @click="openEdit(role)"
                                            class="me-1"
                                        >
                                            Edit
                                        </CButton>
                                        <CButton
                                            v-if="canDeleteRoles"
                                            color="danger"
                                            size="sm"
                                            variant="outline"
                                            @click="deleteRole(role)"
                                        >
                                            Delete
                                        </CButton>
                                    </CTableDataCell>
                                </CTableRow>
                                <CTableRow v-if="!roles.length">
                                    <CTableDataCell colspan="3" class="text-center text-muted py-5">
                                        No roles found.
                                    </CTableDataCell>
                                </CTableRow>
                            </CTableBody>
                        </CTable>
                    </div>
                </CCardBody>
            </CCard>

            <!-- Create/Edit Modal -->
            <CModal :visible="showCreateModal || showEditModal" @hide="showCreateModal = showEditModal = false" size="lg">
                <CModalHeader>
                    <CModalTitle>{{ editingRole ? 'Edit Role' : 'Create Role' }}</CModalTitle>
                </CModalHeader>
                <CModalBody>
                    <CForm>
                        <CRow class="g-3">
                            <CCol xs="12">
                                <CFormLabel for="name">Name</CFormLabel>
                                <CFormInput
                                    id="name"
                                    v-model="form.name"
                                    :invalid="!!formErrors.name"
                                />
                                <CFormFeedback invalid v-if="formErrors.name">
                                    {{ formErrors.name[0] }}
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
                    <CButton color="secondary" variant="outline" @click="showCreateModal = showEditModal = false">
                        Cancel
                    </CButton>
                    <CButton color="primary" :disabled="saving" @click="saveRole">
                        <CSpinner v-if="saving" size="sm" class="me-1" />
                        {{ editingRole ? 'Update' : 'Create' }}
                    </CButton>
                </CModalFooter>
            </CModal>
        </CContainer>
    </AppLayout>
</template>