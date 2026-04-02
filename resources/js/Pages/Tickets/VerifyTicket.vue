<script setup>
import { ref, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import jsQR from 'jsqr';

// ── Manual input ──────────────────────────────────────────────────────────────
const input   = ref('');
const result  = ref(null);
const error   = ref('');
const loading = ref(false);

// ── Camera / image scanner ────────────────────────────────────────────────────
const scannerOpen   = ref(false);
const scannerError  = ref('');
const scanning      = ref(false);
const videoEl       = ref(null);
const canvasEl      = ref(null);
const fileInput     = ref(null);
const uploadError   = ref('');
const uploadLoading = ref(false);
let   stream        = null;
let   rafHandle     = null;

async function openScanner() {
    scannerError.value = '';
    scannerOpen.value  = true;
    scanning.value     = false;

    // Wait for DOM then start camera
    await new Promise(r => setTimeout(r, 100));
    startCamera();
}

async function onFileUpload(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    uploadError.value   = '';
    uploadLoading.value = true;

    try {
        const bitmap = await createImageBitmap(file);
        const canvas = document.createElement('canvas');
        canvas.width  = bitmap.width;
        canvas.height = bitmap.height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(bitmap, 0, 0);
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const qr = jsQR(imageData.data, imageData.width, imageData.height, {
            inversionAttempts: 'attemptBoth',
        });
        if (qr?.data) {
            input.value = qr.data;
            verify();
        } else {
            uploadError.value = 'No QR code found in the image. Try a clearer photo.';
        }
    } catch {
        uploadError.value = 'Could not read the image. Please try another file.';
    } finally {
        uploadLoading.value  = false;
        e.target.value       = '';   // reset so same file can be re-selected
    }
}

async function startCamera() {
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } },
        });
        if (!videoEl.value) return;
        videoEl.value.srcObject = stream;
        await videoEl.value.play();
        scanning.value = true;
        rafHandle = requestAnimationFrame(scanFrame);
    } catch (e) {
        scannerError.value = e.name === 'NotAllowedError'
            ? 'Camera permission denied. Please allow camera access and try again.'
            : 'Could not access camera: ' + e.message;
    }
}

function scanFrame() {
    if (!scanning.value || !videoEl.value || !canvasEl.value) return;

    const video = videoEl.value;
    if (video.readyState !== video.HAVE_ENOUGH_DATA) {
        rafHandle = requestAnimationFrame(scanFrame);
        return;
    }

    const canvas = canvasEl.value;
    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const qr = jsQR(imageData.data, imageData.width, imageData.height, {
        inversionAttempts: 'dontInvert',
    });

    if (qr?.data) {
        stopCamera();
        input.value = qr.data;
        verify();
        return;
    }

    rafHandle = requestAnimationFrame(scanFrame);
}

function stopCamera() {
    scanning.value = false;
    cancelAnimationFrame(rafHandle);
    if (stream) {
        stream.getTracks().forEach(t => t.stop());
        stream = null;
    }
}

function closeScanner() {
    stopCamera();
    scannerOpen.value  = false;
    scannerError.value = '';
}

onUnmounted(stopCamera);

// ── Verify ────────────────────────────────────────────────────────────────────
async function verify() {
    const code = input.value.trim();
    if (!code) { error.value = 'Please enter a ticket code.'; return; }

    loading.value = true;
    result.value  = null;
    error.value   = '';

    try {
        const res = await axios.get(`/api/tickets/verify/${encodeURIComponent(code)}`);
        result.value = res.data;
    } catch (e) {
        result.value = e.response?.data
            ?? { valid: false, status: 'error', message: 'Verification failed. Please try again.' };
    } finally {
        loading.value = false;
    }
}

function reset() {
    input.value  = '';
    result.value = null;
    error.value  = '';
    closeScanner();
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const statusColor = (s) => ({
    valid:     'success',
    used:      'warning',
    unpaid:    'danger',
    not_found: 'danger',
    error:     'danger',
}[s] ?? 'secondary');

const statusLabel = (s) => ({
    valid:     '✅ Valid',
    used:      '⚠️ Already Used',
    unpaid:    '❌ Unpaid',
    not_found: '❌ Not Found',
    error:     '❌ Error',
}[s] ?? s);

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('en-PH', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
    });
}
</script>

<template>
    <Head title="Verify Ticket" />
    <AppLayout>
        <div class="page-header">
            <h1 class="page-title">Verify Ticket</h1>
        </div>

        <div style="max-width: 540px; margin: 0 auto;">

            <!-- ── Manual input ──────────────────────────────────────────────── -->
            <CCard class="mb-3">
                <CCardHeader class="fw-semibold small text-uppercase text-muted" style="font-size:.7rem;letter-spacing:.04em">
                    Enter Ticket Code
                </CCardHeader>
                <CCardBody class="p-3">
                    <CInputGroup class="mb-2">
                        <CFormInput
                            v-model="input"
                            placeholder="Paste QR code string here…"
                            :disabled="loading"
                            @keyup.enter="verify"
                        />
                        <CButton color="primary" class="text-white" @click="verify" :disabled="loading || !input.trim()">
                            <CSpinner v-if="loading" size="sm" class="me-1" />
                            {{ loading ? 'Checking…' : 'Verify' }}
                        </CButton>
                    </CInputGroup>
                    <div v-if="error" class="text-danger small">{{ error }}</div>
                </CCardBody>
            </CCard>

            <!-- ── Camera / image scanner ────────────────────────────────────── -->
            <CCard class="mb-4">
                <CCardHeader class="fw-semibold small text-uppercase text-muted" style="font-size:.7rem;letter-spacing:.04em">
                    Scan QR Code
                </CCardHeader>
                <CCardBody class="p-3">

                    <!-- Action buttons (always visible when camera not open) -->
                    <template v-if="!scannerOpen">
                        <div class="d-flex gap-2 mb-2">
                            <CButton color="dark" class="text-white flex-grow-1" @click="openScanner">
                                📷 Open Camera
                            </CButton>
                            <CButton
                                color="secondary"
                                variant="outline"
                                class="flex-grow-1"
                                :disabled="uploadLoading"
                                @click="fileInput.click()"
                            >
                                <CSpinner v-if="uploadLoading" size="sm" class="me-1" />
                                {{ uploadLoading ? 'Reading…' : '🖼️ Upload QR Image' }}
                            </CButton>
                        </div>
                        <div v-if="uploadError" class="text-danger small mt-1">{{ uploadError }}</div>
                        <p class="text-muted small mb-0 mt-2">
                            Use your camera for live scanning, or upload a photo of the QR code.
                        </p>
                    </template>

                    <!-- Hidden file input -->
                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/*"
                        style="display:none;"
                        @change="onFileUpload"
                    />

                    <!-- Camera viewport -->
                    <template v-if="scannerOpen">
                        <CAlert v-if="scannerError" color="danger" class="py-2 small mb-3">
                            {{ scannerError }}
                        </CAlert>

                        <div class="position-relative overflow-hidden rounded mb-3" style="background:#000;aspect-ratio:4/3;">
                            <video
                                ref="videoEl"
                                playsinline
                                muted
                                style="width:100%;height:100%;object-fit:cover;display:block;"
                            />
                            <div v-if="scanning" class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="pointer-events:none;">
                                <div class="scan-frame"></div>
                            </div>
                            <div v-if="scanning" class="position-absolute bottom-0 start-0 w-100 text-center pb-2" style="pointer-events:none;">
                                <span class="badge bg-dark bg-opacity-75 text-white small">
                                    <span class="scan-pulse">●</span> Scanning…
                                </span>
                            </div>
                        </div>

                        <canvas ref="canvasEl" style="display:none;" />

                        <CButton color="secondary" variant="outline" size="sm" class="w-100" @click="closeScanner">
                            Close Camera
                        </CButton>
                    </template>

                </CCardBody>
            </CCard>

            <!-- ── Result ────────────────────────────────────────────────────── -->
            <template v-if="result">
                <CAlert :color="statusColor(result.status)" class="d-flex align-items-center gap-2 mb-3">
                    <span class="fw-bold fs-5">{{ statusLabel(result.status) }}</span>
                    <span class="ms-auto small opacity-75">{{ result.message }}</span>
                </CAlert>

                <CCard v-if="result.status !== 'not_found' && result.status !== 'error'" class="mb-3">
                    <CCardHeader class="fw-semibold">Ticket Details</CCardHeader>
                    <CCardBody class="p-0">
                        <CTable borderless small class="mb-0">
                            <CTableBody>
                                <CTableRow>
                                    <CTableDataCell class="text-muted ps-3" style="width:40%">Ticket</CTableDataCell>
                                    <CTableDataCell class="fw-semibold">
                                        {{ result.ticket_name ?? '—' }}
                                        <CBadge
                                            v-if="result.ticket_type"
                                            :color="result.ticket_type === 'student' ? 'info' : 'warning'"
                                            text-color="dark"
                                            class="ms-1 text-capitalize"
                                        >{{ result.ticket_type }}</CBadge>
                                    </CTableDataCell>
                                </CTableRow>
                                <CTableRow>
                                    <CTableDataCell class="text-muted ps-3">Event</CTableDataCell>
                                    <CTableDataCell>{{ result.event_name ?? '—' }}</CTableDataCell>
                                </CTableRow>
                                <CTableRow v-if="result.event_venue">
                                    <CTableDataCell class="text-muted ps-3">Venue</CTableDataCell>
                                    <CTableDataCell>{{ result.event_venue }}</CTableDataCell>
                                </CTableRow>
                                <CTableRow v-if="result.event_date">
                                    <CTableDataCell class="text-muted ps-3">Date</CTableDataCell>
                                    <CTableDataCell>{{ formatDate(result.event_date) }}</CTableDataCell>
                                </CTableRow>
                                <CTableRow>
                                    <CTableDataCell class="text-muted ps-3">Order Ref</CTableDataCell>
                                    <CTableDataCell class="font-monospace small">{{ result.order_ref ?? '—' }}</CTableDataCell>
                                </CTableRow>
                                <CTableRow>
                                    <CTableDataCell class="text-muted ps-3">Holder</CTableDataCell>
                                    <CTableDataCell class="text-break">{{ result.holder_email ?? '—' }}</CTableDataCell>
                                </CTableRow>
                                <CTableRow v-if="result.used_at">
                                    <CTableDataCell class="text-muted ps-3">Used At</CTableDataCell>
                                    <CTableDataCell class="text-warning fw-semibold">
                                        {{ new Date(result.used_at).toLocaleString() }}
                                    </CTableDataCell>
                                </CTableRow>
                            </CTableBody>
                        </CTable>
                    </CCardBody>
                </CCard>

                <div class="text-center mt-2 mb-4">
                    <CButton color="secondary" variant="outline" size="sm" @click="reset">
                        Check Another Ticket
                    </CButton>
                </div>
            </template>

        </div>
    </AppLayout>
</template>

<style scoped>
.scan-frame {
    width: 220px;
    height: 220px;
    border: 3px solid rgba(255, 255, 255, 0.85);
    border-radius: 12px;
    box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.35);
}

.scan-frame::before,
.scan-frame::after {
    content: '';
    position: absolute;
    width: 28px;
    height: 28px;
    border-color: #fff;
    border-style: solid;
}
.scan-frame::before {
    top: -3px; left: -3px;
    border-width: 4px 0 0 4px;
    border-radius: 10px 0 0 0;
}
.scan-frame::after {
    bottom: -3px; right: -3px;
    border-width: 0 4px 4px 0;
    border-radius: 0 0 10px 0;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.3; }
}
.scan-pulse {
    animation: pulse 1.2s ease-in-out infinite;
    display: inline-block;
}
</style>
