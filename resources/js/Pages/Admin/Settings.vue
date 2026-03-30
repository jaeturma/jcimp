<script setup>
import { ref, computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
    smtpSettings:      { type: Object, default: () => ({}) },
    recaptchaSettings: { type: Object, default: () => ({}) },
});
const page = usePage();

// ── SMTP ──────────────────────────────────────────────────────────────────────
const smtpEnabled = ref(
    props.smtpSettings.enabled === true || props.smtpSettings.enabled === 'true'
);
const smtp = ref({
    host:         props.smtpSettings.host         ?? '',
    port:         props.smtpSettings.port         ?? 587,
    username:     props.smtpSettings.username     ?? '',
    password:     '',   // never pre-filled
    scheme:       props.smtpSettings.scheme       ?? 'tls',
    from_address: props.smtpSettings.from_address ?? '',
    from_name:    props.smtpSettings.from_name    ?? '',
});

const smtpSaving  = ref(false);
const smtpSuccess = ref('');
const smtpError   = ref('');
const smtpErrors  = ref({});

function onSchemeChange() {
    if (smtp.value.scheme === 'ssl')       smtp.value.port = 465;
    else if (smtp.value.scheme === 'tls')  smtp.value.port = 587;
    else                                   smtp.value.port = 25;
}

async function saveSmtp() {
    smtpSuccess.value = '';
    smtpError.value   = '';
    smtpErrors.value  = {};

    smtpSaving.value = true;
    try {
        const payload = {
            enabled:      smtpEnabled.value,
            from_address: smtp.value.from_address,
            from_name:    smtp.value.from_name,
        };

        if (smtpEnabled.value) {
            payload.host     = smtp.value.host;
            payload.port     = smtp.value.port;
            payload.scheme   = smtp.value.scheme;
            payload.username = smtp.value.username;
            if (smtp.value.password) payload.password = smtp.value.password;
        }

        const res = await axios.post('/api/admin/settings/smtp', payload);
        smtpSuccess.value   = res.data.message ?? 'Saved.';
        smtp.value.password = '';
    } catch (e) {
        if (e.response?.status === 422) {
            smtpErrors.value = e.response.data.errors ?? {};
            smtpError.value  = e.response.data.message ?? 'Validation failed.';
        } else {
            smtpError.value = e.response?.data?.message ?? 'Failed to save.';
        }
    } finally {
        smtpSaving.value = false;
    }
}

// ── Send Test Email ───────────────────────────────────────────────────────────
const testTo      = ref(page.props.auth?.user?.email ?? '');
const testSending = ref(false);
const testSuccess = ref('');
const testError   = ref('');

async function sendTestEmail() {
    testSuccess.value = ''; testError.value = '';
    if (!testTo.value.trim()) { testError.value = 'Enter a recipient email.'; return; }
    testSending.value = true;
    try {
        const res = await axios.post('/api/admin/settings/test-email', { to: testTo.value.trim() });
        testSuccess.value = res.data.message ?? 'Sent.';
    } catch (e) {
        testError.value = e.response?.data?.message ?? 'Failed to send test email.';
    } finally {
        testSending.value = false;
    }
}

// ── reCAPTCHA ─────────────────────────────────────────────────────────────────
const rcEnabled   = ref(
    props.recaptchaSettings.enabled === true || props.recaptchaSettings.enabled === 'true'
);
const siteKey     = ref(props.recaptchaSettings.site_key ?? '');
const secretKey   = ref('');
const rcSaving    = ref(false);
const rcSuccess   = ref('');
const rcError     = ref('');
const rcErrors    = ref({});

const currentSiteKey = computed(() => page.props.recaptchaSiteKey ?? '');

async function saveRecaptcha() {
    rcSuccess.value = ''; rcError.value = ''; rcErrors.value = {};

    rcSaving.value = true;
    try {
        const payload = { enabled: rcEnabled.value };
        if (rcEnabled.value) {
            payload.site_key   = siteKey.value.trim();
            payload.secret_key = secretKey.value.trim();
        }

        const res = await axios.post('/api/admin/settings/recaptcha', payload);
        rcSuccess.value = res.data.message ?? 'Saved.';
        secretKey.value = '';
    } catch (e) {
        if (e.response?.status === 422) {
            rcErrors.value = e.response.data.errors ?? {};
            rcError.value  = e.response.data.message ?? 'Validation failed.';
        } else {
            rcError.value = e.response?.data?.message ?? 'Failed to save.';
        }
    } finally {
        rcSaving.value = false;
    }
}
</script>

<template>
    <Head title="System Settings" />
    <AppLayout>

        <div class="page-header">
            <h1 class="page-title">System Settings</h1>
        </div>

        <CRow>
            <CCol xs="12" lg="8" xl="7">

                <!-- ── Email / SMTP ─────────────────────────────────────── -->
                <CCard class="mb-4">
                    <CCardHeader class="d-flex align-items-center justify-content-between">
                        <span class="fw-semibold d-flex align-items-center gap-2">
                            <CIcon customClassName="nav-icon" icon="cil-envelope-closed" />
                            Email (SMTP) Configuration
                        </span>
                        <!-- Enable toggle -->
                        <div class="d-flex align-items-center gap-2">
                            <span class="small text-muted">{{ smtpEnabled ? 'Enabled' : 'Disabled' }}</span>
                            <CFormSwitch
                                v-model="smtpEnabled"
                                :color="smtpEnabled ? 'success' : 'secondary'"
                                size="lg"
                            />
                        </div>
                    </CCardHeader>
                    <CCardBody>

                        <!-- Current status banner -->
                        <div class="mb-4 p-3 rounded d-flex align-items-center gap-3"
                            style="background:var(--cui-tertiary-bg,#f0f4f8)">
                            <CBadge :color="smtpEnabled ? 'success' : 'secondary'" class="px-3 py-2">
                                {{ smtpEnabled ? 'SMTP Active' : 'Disabled (log only)' }}
                            </CBadge>
                            <span class="small text-muted">
                                From: <strong>{{ smtpSettings.from_name || '—' }}</strong>
                                &lt;{{ smtpSettings.from_address || '—' }}&gt;
                            </span>
                        </div>

                        <!-- Disabled notice -->
                        <CAlert v-if="!smtpEnabled" color="warning" class="mb-4">
                            <strong>Email is disabled.</strong>
                            Emails will be written to the log file only. Enable SMTP to send real emails.
                        </CAlert>

                        <!-- SMTP fields — only when enabled -->
                        <transition name="slide">
                            <div v-if="smtpEnabled">
                                <CRow class="g-3 mb-3">
                                    <CCol xs="12" sm="8">
                                        <CFormLabel class="fw-semibold">
                                            SMTP Host <span class="text-danger">*</span>
                                        </CFormLabel>
                                        <CFormInput
                                            v-model="smtp.host"
                                            placeholder="smtp.gmail.com"
                                            autocomplete="off"
                                            :invalid="!!smtpErrors.host"
                                        />
                                        <CFormFeedback invalid v-if="smtpErrors.host">{{ smtpErrors.host[0] }}</CFormFeedback>
                                    </CCol>
                                    <CCol xs="12" sm="4">
                                        <CFormLabel class="fw-semibold">
                                            Port <span class="text-danger">*</span>
                                        </CFormLabel>
                                        <CFormInput
                                            v-model.number="smtp.port"
                                            type="number"
                                            placeholder="587"
                                            min="1" max="65535"
                                            :invalid="!!smtpErrors.port"
                                        />
                                        <CFormFeedback invalid v-if="smtpErrors.port">{{ smtpErrors.port[0] }}</CFormFeedback>
                                    </CCol>
                                </CRow>

                                <div class="mb-3">
                                    <CFormLabel class="fw-semibold">Encryption</CFormLabel>
                                    <CFormSelect v-model="smtp.scheme" @change="onSchemeChange">
                                        <option value="tls">TLS (port 587 — recommended)</option>
                                        <option value="ssl">SSL (port 465)</option>
                                        <option value="null">None (port 25)</option>
                                    </CFormSelect>
                                </div>

                                <CRow class="g-3 mb-4">
                                    <CCol xs="12" sm="6">
                                        <CFormLabel class="fw-semibold">SMTP Username</CFormLabel>
                                        <CFormInput
                                            v-model="smtp.username"
                                            type="email"
                                            placeholder="you@gmail.com"
                                            autocomplete="off"
                                        />
                                    </CCol>
                                    <CCol xs="12" sm="6">
                                        <CFormLabel class="fw-semibold">SMTP Password</CFormLabel>
                                        <CFormInput
                                            v-model="smtp.password"
                                            type="password"
                                            placeholder="Leave blank to keep current"
                                            autocomplete="new-password"
                                        />
                                        <div class="form-text">Leave blank to keep the existing password.</div>
                                    </CCol>
                                </CRow>
                            </div>
                        </transition>

                        <!-- From address / name (always shown) -->
                        <CRow class="g-3 mb-4">
                            <CCol xs="12" sm="6">
                                <CFormLabel class="fw-semibold">
                                    From Address <span class="text-danger">*</span>
                                </CFormLabel>
                                <CFormInput
                                    v-model="smtp.from_address"
                                    type="email"
                                    placeholder="noreply@yourdomain.com"
                                    :invalid="!!smtpErrors.from_address"
                                />
                                <CFormFeedback invalid v-if="smtpErrors.from_address">{{ smtpErrors.from_address[0] }}</CFormFeedback>
                                <div class="form-text">Sender address shown in the user's inbox.</div>
                            </CCol>
                            <CCol xs="12" sm="6">
                                <CFormLabel class="fw-semibold">
                                    From Name <span class="text-danger">*</span>
                                </CFormLabel>
                                <CFormInput
                                    v-model="smtp.from_name"
                                    placeholder="Concert Ticketing"
                                    :invalid="!!smtpErrors.from_name"
                                />
                                <CFormFeedback invalid v-if="smtpErrors.from_name">{{ smtpErrors.from_name[0] }}</CFormFeedback>
                            </CCol>
                        </CRow>

                        <CAlert v-if="smtpSuccess" color="success" class="py-2 mb-3">{{ smtpSuccess }}</CAlert>
                        <CAlert v-if="smtpError"   color="danger"  class="py-2 mb-3">{{ smtpError }}</CAlert>

                        <CButton color="primary" :disabled="smtpSaving" @click="saveSmtp">
                            <CSpinner v-if="smtpSaving" size="sm" class="me-2" />
                            {{ smtpSaving ? 'Saving…' : 'Save Email Settings' }}
                        </CButton>

                    </CCardBody>
                </CCard>

                <!-- ── Send Test Email ──────────────────────────────────── -->
                <CCard class="mb-4">
                    <CCardHeader class="fw-semibold d-flex align-items-center gap-2">
                        <CIcon customClassName="nav-icon" icon="cil-send" />
                        Send Test Email
                    </CCardHeader>
                    <CCardBody>
                        <CAlert v-if="!smtpEnabled" color="warning" class="mb-3 py-2">
                            SMTP is currently disabled. Enable it above before sending a test email.
                        </CAlert>
                        <p class="small text-muted mb-3">
                            Send a test message to verify your SMTP configuration is working.
                        </p>
                        <CRow class="g-2 align-items-end">
                            <CCol xs="12" sm="8">
                                <CFormLabel class="fw-semibold">Recipient Email</CFormLabel>
                                <CFormInput
                                    v-model="testTo"
                                    type="email"
                                    placeholder="admin@yourdomain.com"
                                    :disabled="!smtpEnabled"
                                />
                            </CCol>
                            <CCol xs="12" sm="4">
                                <CButton
                                    color="secondary"
                                    class="w-100"
                                    :disabled="testSending || !smtpEnabled"
                                    @click="sendTestEmail"
                                >
                                    <CSpinner v-if="testSending" size="sm" class="me-2" />
                                    {{ testSending ? 'Sending…' : 'Send Test' }}
                                </CButton>
                            </CCol>
                        </CRow>
                        <CAlert v-if="testSuccess" color="success" class="py-2 mt-3 mb-0">{{ testSuccess }}</CAlert>
                        <CAlert v-if="testError"   color="danger"  class="py-2 mt-3 mb-0">{{ testError }}</CAlert>
                    </CCardBody>
                </CCard>

                <!-- ── Google reCAPTCHA ─────────────────────────────────── -->
                <CCard class="mb-4">
                    <CCardHeader class="d-flex align-items-center justify-content-between">
                        <span class="fw-semibold d-flex align-items-center gap-2">
                            <CIcon customClassName="nav-icon" icon="cil-shield-alt" />
                            Google reCAPTCHA v2
                        </span>
                        <!-- Enable toggle -->
                        <div class="d-flex align-items-center gap-2">
                            <span class="small text-muted">{{ rcEnabled ? 'Enabled' : 'Disabled' }}</span>
                            <CFormSwitch
                                v-model="rcEnabled"
                                :color="rcEnabled ? 'success' : 'secondary'"
                                size="lg"
                            />
                        </div>
                    </CCardHeader>
                    <CCardBody>

                        <!-- Current status -->
                        <div class="mb-4 p-3 rounded d-flex align-items-center gap-3"
                            style="background:var(--cui-tertiary-bg,#f0f4f8)">
                            <CBadge :color="rcEnabled ? 'success' : 'secondary'" class="px-3 py-2">
                                {{ rcEnabled ? 'Active' : 'Disabled' }}
                            </CBadge>
                            <span v-if="currentSiteKey" class="font-monospace small text-muted">
                                Site key: {{ currentSiteKey.slice(0, 14) }}…
                            </span>
                            <span v-else class="small text-muted">No site key configured</span>
                        </div>

                        <CAlert v-if="!rcEnabled" color="warning" class="mb-4">
                            <strong>reCAPTCHA is disabled.</strong>
                            The Quick Buy and checkout forms will not require CAPTCHA verification.
                        </CAlert>

                        <CAlert color="info" class="mb-4" style="font-size:.85rem">
                            Get your keys at
                            <a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener noreferrer">
                                google.com/recaptcha/admin
                            </a>
                            — select <strong>Challenge (v2) › "I'm not a robot" checkbox</strong>.
                        </CAlert>

                        <!-- Key fields — only when enabled -->
                        <transition name="slide">
                            <div v-if="rcEnabled">
                                <div class="mb-3">
                                    <CFormLabel class="fw-semibold">
                                        Site Key <span class="text-danger">*</span>
                                    </CFormLabel>
                                    <CFormInput
                                        v-model="siteKey"
                                        placeholder="6Lcxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                        autocomplete="off"
                                        :invalid="!!rcErrors.site_key"
                                    />
                                    <CFormFeedback invalid v-if="rcErrors.site_key">{{ rcErrors.site_key[0] }}</CFormFeedback>
                                    <div class="form-text">Used in the frontend widget. Safe to expose publicly.</div>
                                </div>

                                <div class="mb-4">
                                    <CFormLabel class="fw-semibold">
                                        Secret Key <span class="text-danger">*</span>
                                    </CFormLabel>
                                    <CFormInput
                                        v-model="secretKey"
                                        type="password"
                                        placeholder="Enter new secret key"
                                        autocomplete="new-password"
                                        :invalid="!!rcErrors.secret_key"
                                    />
                                    <CFormFeedback invalid v-if="rcErrors.secret_key">{{ rcErrors.secret_key[0] }}</CFormFeedback>
                                    <div class="form-text">Used server-side only. Never shared publicly.</div>
                                </div>
                            </div>
                        </transition>

                        <CAlert v-if="rcSuccess" color="success" class="py-2 mb-3">{{ rcSuccess }}</CAlert>
                        <CAlert v-if="rcError"   color="danger"  class="py-2 mb-3">{{ rcError }}</CAlert>

                        <CButton color="primary" :disabled="rcSaving" @click="saveRecaptcha">
                            <CSpinner v-if="rcSaving" size="sm" class="me-2" />
                            {{ rcSaving ? 'Saving…' : 'Save reCAPTCHA Settings' }}
                        </CButton>

                    </CCardBody>
                </CCard>

            </CCol>
        </CRow>

    </AppLayout>
</template>

<style scoped>
.slide-enter-active, .slide-leave-active { transition: opacity .2s, transform .2s; }
.slide-enter-from, .slide-leave-to { opacity: 0; transform: translateY(-6px); }
</style>
