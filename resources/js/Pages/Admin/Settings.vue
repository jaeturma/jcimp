<script setup>
import { ref, computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props  = defineProps({ smtpSettings: { type: Object, default: () => ({}) } });
const page   = usePage();

// ── reCAPTCHA state ───────────────────────────────────────────────────────
const currentSiteKey = computed(() => page.props.recaptchaSiteKey ?? '');
const siteKey        = ref(currentSiteKey.value);
const secretKey      = ref('');
const rcSaving       = ref(false);
const rcSuccess      = ref('');
const rcError        = ref('');

async function saveRecaptcha() {
    rcSuccess.value = ''; rcError.value = '';
    if (!siteKey.value.trim() || !secretKey.value.trim()) {
        rcError.value = 'Both Site Key and Secret Key are required.'; return;
    }
    rcSaving.value = true;
    try {
        const res = await axios.post('/api/admin/settings/recaptcha', {
            site_key:   siteKey.value.trim(),
            secret_key: secretKey.value.trim(),
        });
        rcSuccess.value = res.data.message ?? 'Saved.';
        secretKey.value = '';
    } catch (e) {
        rcError.value = e.response?.data?.message ?? 'Failed to save.';
    } finally {
        rcSaving.value = false;
    }
}

// ── SMTP state ────────────────────────────────────────────────────────────
const smtp = ref({
    mailer:       props.smtpSettings.mailer       ?? 'log',
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

const isSmtp = computed(() => smtp.value.mailer === 'smtp');

// Sensible port defaults per scheme
function onSchemeChange() {
    if (smtp.value.scheme === 'ssl')              smtp.value.port = 465;
    else if (smtp.value.scheme === 'tls')         smtp.value.port = 587;
    else if (smtp.value.scheme === 'null')        smtp.value.port = 25;
}

async function saveSmtp() {
    smtpSuccess.value = ''; smtpError.value = '';
    if (!smtp.value.from_address.trim()) {
        smtpError.value = 'From Address is required.'; return;
    }
    if (!smtp.value.from_name.trim()) {
        smtpError.value = 'From Name is required.'; return;
    }
    smtpSaving.value = true;
    try {
        const res = await axios.post('/api/admin/settings/smtp', {
            mailer:       smtp.value.mailer,
            host:         smtp.value.host,
            port:         smtp.value.port,
            username:     smtp.value.username,
            password:     smtp.value.password || undefined,
            scheme:       smtp.value.scheme,
            from_address: smtp.value.from_address,
            from_name:    smtp.value.from_name,
        });
        smtpSuccess.value   = res.data.message ?? 'Saved.';
        smtp.value.password = '';
    } catch (e) {
        smtpError.value = e.response?.data?.message ?? 'Failed to save.';
    } finally {
        smtpSaving.value = false;
    }
}

// ── Test email state ───────────────────────────────────────────────────────
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
                    <CCardHeader class="fw-semibold d-flex align-items-center gap-2">
                        <CIcon customClassName="nav-icon" icon="cil-envelope-closed" />
                        Email (SMTP) Configuration
                    </CCardHeader>
                    <CCardBody>

                        <!-- Status banner -->
                        <div class="mb-4 p-3 rounded d-flex align-items-center gap-3"
                            style="background:var(--cui-tertiary-bg,#f0f4f8)">
                            <CBadge :color="smtp.mailer === 'smtp' ? 'success' : 'secondary'" class="px-3 py-2">
                                {{ smtp.mailer === 'smtp' ? 'SMTP Active' : smtp.mailer === 'log' ? 'Log Only (dev)' : smtp.mailer }}
                            </CBadge>
                            <span class="small text-muted">
                                From: <strong>{{ smtpSettings.from_name || '—' }}</strong>
                                &lt;{{ smtpSettings.from_address || '—' }}&gt;
                            </span>
                        </div>

                        <!-- Mailer driver -->
                        <div class="mb-3">
                            <CFormLabel class="fw-semibold">Mail Driver <span class="text-danger">*</span></CFormLabel>
                            <CFormSelect v-model="smtp.mailer">
                                <option value="smtp">SMTP</option>
                                <option value="log">Log (development — emails written to log file)</option>
                                <option value="sendmail">Sendmail</option>
                            </CFormSelect>
                        </div>

                        <!-- SMTP-specific fields -->
                        <transition name="slide">
                            <div v-if="isSmtp">

                                <CRow class="g-3 mb-3">
                                    <CCol xs="12" sm="8">
                                        <CFormLabel class="fw-semibold">SMTP Host <span class="text-danger">*</span></CFormLabel>
                                        <CFormInput
                                            v-model="smtp.host"
                                            placeholder="smtp.gmail.com"
                                            autocomplete="off"
                                        />
                                    </CCol>
                                    <CCol xs="12" sm="4">
                                        <CFormLabel class="fw-semibold">Port <span class="text-danger">*</span></CFormLabel>
                                        <CFormInput
                                            v-model.number="smtp.port"
                                            type="number"
                                            placeholder="587"
                                            min="1" max="65535"
                                        />
                                    </CCol>
                                </CRow>

                                <div class="mb-3">
                                    <CFormLabel class="fw-semibold">Encryption / Scheme</CFormLabel>
                                    <CFormSelect v-model="smtp.scheme" @change="onSchemeChange">
                                        <option value="tls">TLS (port 587 — recommended)</option>
                                        <option value="ssl">SSL (port 465)</option>
                                        <option value="null">None (port 25)</option>
                                    </CFormSelect>
                                </div>

                                <CRow class="g-3 mb-3">
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
                                <CFormLabel class="fw-semibold">From Address <span class="text-danger">*</span></CFormLabel>
                                <CFormInput
                                    v-model="smtp.from_address"
                                    type="email"
                                    placeholder="noreply@yourdomain.com"
                                />
                                <div class="form-text">Sender address shown in the user's inbox.</div>
                            </CCol>
                            <CCol xs="12" sm="6">
                                <CFormLabel class="fw-semibold">From Name <span class="text-danger">*</span></CFormLabel>
                                <CFormInput
                                    v-model="smtp.from_name"
                                    placeholder="Concert Ticketing"
                                />
                            </CCol>
                        </CRow>

                        <!-- Feedback -->
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
                        <p class="small text-muted mb-3">
                            Send a test message to verify your email configuration is working.
                        </p>
                        <CRow class="g-2 align-items-end">
                            <CCol xs="12" sm="8">
                                <CFormLabel class="fw-semibold">Recipient Email</CFormLabel>
                                <CFormInput
                                    v-model="testTo"
                                    type="email"
                                    placeholder="admin@yourdomain.com"
                                />
                            </CCol>
                            <CCol xs="12" sm="4">
                                <CButton
                                    color="secondary"
                                    class="w-100"
                                    :disabled="testSending"
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
                    <CCardHeader class="fw-semibold d-flex align-items-center gap-2">
                        <CIcon customClassName="nav-icon" icon="cil-shield-alt" />
                        Google reCAPTCHA v2 Configuration
                    </CCardHeader>
                    <CCardBody>

                        <CAlert color="info" class="mb-4" style="font-size:.85rem">
                            reCAPTCHA protects the <strong>Quick Buy</strong> form from bots.
                            Get your keys at
                            <a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener noreferrer">
                                google.com/recaptcha/admin
                            </a>
                            — select <strong>Challenge (v2) › "I'm not a robot" checkbox</strong>.
                        </CAlert>

                        <div class="mb-4 p-3 rounded" style="background:var(--cui-tertiary-bg,#f0f4f8)">
                            <p class="small fw-semibold text-muted mb-1">Current Status</p>
                            <div class="d-flex align-items-center gap-2">
                                <CBadge :color="currentSiteKey ? 'success' : 'secondary'">
                                    {{ currentSiteKey ? 'Enabled' : 'Disabled' }}
                                </CBadge>
                                <span v-if="currentSiteKey" class="font-monospace small text-muted">
                                    {{ currentSiteKey.slice(0, 12) }}…
                                </span>
                                <span v-else class="small text-muted">No site key configured</span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <CFormLabel class="fw-semibold">Site Key <span class="text-danger">*</span></CFormLabel>
                            <CFormInput v-model="siteKey" placeholder="6Lcxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" autocomplete="off" />
                            <div class="form-text">Shown in the frontend widget. Safe to expose publicly.</div>
                        </div>

                        <div class="mb-4">
                            <CFormLabel class="fw-semibold">Secret Key <span class="text-danger">*</span></CFormLabel>
                            <CFormInput
                                v-model="secretKey"
                                type="password"
                                placeholder="Enter new secret key"
                                autocomplete="new-password"
                            />
                            <div class="form-text">Used server-side only. Never shared.</div>
                        </div>

                        <CAlert v-if="rcSuccess" color="success" class="py-2 mb-3">{{ rcSuccess }}</CAlert>
                        <CAlert v-if="rcError"   color="danger"  class="py-2 mb-3">{{ rcError }}</CAlert>

                        <CButton color="primary" :disabled="rcSaving" @click="saveRecaptcha">
                            <CSpinner v-if="rcSaving" size="sm" class="me-2" />
                            {{ rcSaving ? 'Saving…' : 'Save reCAPTCHA Keys' }}
                        </CButton>

                    </CCardBody>
                </CCard>

            </CCol>
        </CRow>

    </AppLayout>
</template>

<style scoped>
.slide-enter-active, .slide-leave-active { transition: opacity .2s, transform .15s; }
.slide-enter-from, .slide-leave-to { opacity: 0; transform: translateY(-6px); }
</style>
