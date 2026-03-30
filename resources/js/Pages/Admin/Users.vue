<script setup>
import { ref, onMounted, computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const page = usePage();
const auth = computed(() => page.props.auth);

const users = ref([]);
const loading = ref(true);
const selected = ref(null);
const detailLoading = ref(false);

const showCreateModal = ref(false);
const showEditModal = ref(false);
const editingUser = ref(null);
const form = ref({
    name: '',
    email: '',
    password: '',
    role: '',
});
const formErrors = ref({});
const saving = ref(false);

const canViewUsers = computed(() => auth.value?.can?.['view users'] ?? false);
const canCreateUsers = computed(() => auth.value?.can?.['create users'] ?? false);
const canUpdateUsers = computed(() => auth.value?.can?.['update users'] ?? false);
const canDeleteUsers = computed(() => auth.value?.can?.['delete users'] ?? false);

onMounted(() => load());

async function load() {
    loading.value = true;
    try {
        const res = await axios.get('/api/admin/users');
        users.value = res.data.data;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    form.value = { name: '', email: '', password: '', role: '' };
    formErrors.value = {};
    showCreateModal.value = true;
}

function openEdit(user) {
    editingUser.value = user;
    form.value = {
        name: user.name,
        email: user.email,
        password: '',
        role: user.roles?.[0]?.name || '',
    };
    formErrors.value = {};
    showEditModal.value = true;
}

async function saveUser() {
    saving.value = true;
    formErrors.value = {};
    try {
        if (editingUser.value) {
            await axios.put(`/api/admin/users/${editingUser.value.id}`, form.value);
        } else {
            await axios.post('/api/admin/users', form.value);
        }
        showCreateModal.value = false;
        showEditModal.value = false;
        load();
    } catch (e) {
        if (e.response?.status === 422) {
            formErrors.value = e.response.data.errors;
        }
    } finally {
        saving.value = false;
    }
}

async function deleteUser(user) {
    if (!confirm('Are you sure you want to delete this user?')) return;
    try {
        await axios.delete(`/api/admin/users/${user.id}`);
        load();
    } catch (e) {
        console.error(e);
    }
}
</script>

<template>
    <Head title="Users" />
    <AppLayout>
        <div class="page-header">
            <h1 class="page-title">Users</h1>
        </div>

        <CContainer fluid class="p-0">
            <CCard>
                <CCardHeader class="d-flex align-items-center justify-content-between">
                    <span class="fw-semibold">
                        <CBadge color="secondary" class="me-2">{{ users.length }}</CBadge>
                        Users
                    </span>
                    <CButton
                        v-if="canCreateUsers"
                        color="primary"
                        size="sm"
                        @click="openCreate"
                    >
                        <CIcon icon="cil-plus" class="me-1" />
                        Create User
                    </CButton>
                </CCardHeader>
                <CCardBody class="p-0">
                    <div v-if="loading" class="py-5 text-center text-muted">
                        <CSpinner color="primary" />
                        <p class="mt-2 mb-0">Loading users…</p>
                    </div>
                    <div v-else class="table-responsive">
                        <CTable striped hover class="mb-0">
                            <CTableHead>
                                <CTableRow>
                                    <CTableHeaderCell>Name</CTableHeaderCell>
                                    <CTableHeaderCell>Email</CTableHeaderCell>
                                    <CTableHeaderCell>Role</CTableHeaderCell>
                                    <CTableHeaderCell>Created</CTableHeaderCell>
                                    <CTableHeaderCell></CTableHeaderCell>
                                </CTableRow>
                            </CTableHead>
                            <CTableBody>
                                <CTableRow v-for="user in users" :key="user.id">
                                    <CTableDataCell class="fw-semibold">{{ user.name }}</CTableDataCell>
                                    <CTableDataCell>{{ user.email }}</CTableDataCell>
                                    <CTableDataCell>
                                        <CBadge :color="user.roles?.[0]?.name === 'super_admin' ? 'danger' : 'primary'">
                                            {{ user.roles?.[0]?.name || 'No Role' }}
                                        </CBadge>
                                    </CTableDataCell>
                                    <CTableDataCell class="text-muted text-nowrap">
                                        {{ new Date(user.created_at).toLocaleDateString() }}
                                    </CTableDataCell>
                                    <CTableDataCell>
                                        <CButton
                                            v-if="canUpdateUsers"
                                            color="warning"
                                            size="sm"
                                            variant="outline"
                                            @click="openEdit(user)"
                                            class="me-1"
                                        >
                                            Edit
                                        </CButton>
                                        <CButton
                                            v-if="canDeleteUsers"
                                            color="danger"
                                            size="sm"
                                            variant="outline"
                                            @click="deleteUser(user)"
                                        >
                                            Delete
                                        </CButton>
                                    </CTableDataCell>
                                </CTableRow>
                                <CTableRow v-if="!users.length">
                                    <CTableDataCell colspan="5" class="text-center text-muted py-5">
                                        No users found.
                                    </CTableDataCell>
                                </CTableRow>
                            </CTableBody>
                        </CTable>
                    </div>
                </CCardBody>
            </CCard>

            <!-- Create/Edit Modal -->
            <CModal :visible="showCreateModal || showEditModal" @hide="showCreateModal = showEditModal = false">
                <CModalHeader>
                    <CModalTitle>{{ editingUser ? 'Edit User' : 'Create User' }}</CModalTitle>
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
                                <CFormLabel for="email">Email</CFormLabel>
                                <CFormInput
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    :invalid="!!formErrors.email"
                                />
                                <CFormFeedback invalid v-if="formErrors.email">
                                    {{ formErrors.email[0] }}
                                </CFormFeedback>
                            </CCol>
                            <CCol xs="12">
                                <CFormLabel for="password">Password</CFormLabel>
                                <CFormInput
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    :invalid="!!formErrors.password"
                                />
                                <CFormFeedback invalid v-if="formErrors.password">
                                    {{ formErrors.password[0] }}
                                </CFormFeedback>
                            </CCol>
                            <CCol xs="12">
                                <CFormLabel for="role">Role</CFormLabel>
                                <CFormSelect
                                    id="role"
                                    v-model="form.role"
                                    :invalid="!!formErrors.role"
                                >
                                    <option value="">Select Role</option>
                                    <option value="super_admin">Super Admin</option>
                                    <option value="admin">Admin</option>
                                    <option value="manager">Manager</option>
                                    <option value="validator">Validator</option>
                                    <option value="staff">Staff</option>
                                </CFormSelect>
                                <CFormFeedback invalid v-if="formErrors.role">
                                    {{ formErrors.role[0] }}
                                </CFormFeedback>
                            </CCol>
                        </CRow>
                    </CForm>
                </CModalBody>
                <CModalFooter>
                    <CButton color="secondary" variant="outline" @click="showCreateModal = showEditModal = false">
                        Cancel
                    </CButton>
                    <CButton color="primary" :disabled="saving" @click="saveUser">
                        <CSpinner v-if="saving" size="sm" class="me-1" />
                        {{ editingUser ? 'Update' : 'Create' }}
                    </CButton>
                </CModalFooter>
            </CModal>
        </CContainer>
    </AppLayout>
</template>