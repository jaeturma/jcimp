/**
 * ticketDb.js — IndexedDB wrapper for offline ticket storage.
 *
 * Stores issued ticket data locally on the device so the user can
 * access their QR codes even without an internet connection.
 */

const DB_NAME    = 'ConcertTickets';
const DB_VERSION = 1;
const STORE      = 'tickets';

function openDb() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);

        req.onupgradeneeded = (e) => {
            const db    = e.target.result;
            if (!db.objectStoreNames.contains(STORE)) {
                const store = db.createObjectStore(STORE, { keyPath: 'qr_code' });
                store.createIndex('order_ref', 'order_ref', { unique: false });
                store.createIndex('saved_at',  'saved_at',  { unique: false });
            }
        };

        req.onsuccess = (e) => resolve(e.target.result);
        req.onerror   = (e) => reject(e.target.error);
    });
}

/**
 * Save all tickets from a paid order to IndexedDB.
 *
 * @param {string} orderRef   - Order reference number
 * @param {string} email      - Customer email
 * @param {Object} orderData  - Full order object with tickets_issued, items, etc.
 */
export async function saveOrderTickets(orderRef, email, orderData) {
    if (!orderData?.tickets_issued?.length) return;

    const db = await openDb();
    const tx = db.transaction(STORE, 'readwrite');
    const st = tx.objectStore(STORE);

    for (const t of orderData.tickets_issued) {
        const record = {
            qr_code:     t.qr_code,
            order_ref:   orderRef,
            email,
            ticket_name: t.ticket?.name ?? 'Concert Ticket',
            ticket_type: t.ticket?.type ?? 'general',
            status:      t.status,
            event_name:  orderData.event_name  ?? '',
            event_venue: orderData.event_venue ?? '',
            event_date:  orderData.event_date  ?? '',
            total_amount: orderData.total_amount,
            saved_at:    new Date().toISOString(),
        };
        st.put(record);
    }

    return new Promise((resolve, reject) => {
        tx.oncomplete = () => { db.close(); resolve(); };
        tx.onerror    = (e) => { db.close(); reject(e.target.error); };
    });
}

/**
 * Get all saved tickets, newest first.
 */
export async function getAllSavedTickets() {
    const db      = await openDb();
    const tx      = db.transaction(STORE, 'readonly');
    const st      = tx.objectStore(STORE);
    const index   = st.index('saved_at');
    const request = index.getAll();

    return new Promise((resolve, reject) => {
        request.onsuccess = (e) => {
            db.close();
            // Newest first
            resolve([...e.target.result].reverse());
        };
        request.onerror = (e) => { db.close(); reject(e.target.error); };
    });
}

/**
 * Get saved tickets for a specific order reference.
 */
export async function getTicketsByRef(orderRef) {
    const db      = await openDb();
    const tx      = db.transaction(STORE, 'readonly');
    const st      = tx.objectStore(STORE);
    const index   = st.index('order_ref');
    const request = index.getAll(orderRef);

    return new Promise((resolve, reject) => {
        request.onsuccess = (e) => { db.close(); resolve(e.target.result); };
        request.onerror   = (e) => { db.close(); reject(e.target.error); };
    });
}

/**
 * Delete all saved tickets for a specific order reference.
 */
export async function deleteTicketsByRef(orderRef) {
    const tickets = await getTicketsByRef(orderRef);
    if (!tickets.length) return;

    const db = await openDb();
    const tx = db.transaction(STORE, 'readwrite');
    const st = tx.objectStore(STORE);
    for (const t of tickets) st.delete(t.qr_code);

    return new Promise((resolve, reject) => {
        tx.oncomplete = () => { db.close(); resolve(); };
        tx.onerror    = (e) => { db.close(); reject(e.target.error); };
    });
}

/**
 * Return count of saved tickets.
 */
export async function getSavedTicketCount() {
    const db      = await openDb();
    const tx      = db.transaction(STORE, 'readonly');
    const request = tx.objectStore(STORE).count();

    return new Promise((resolve, reject) => {
        request.onsuccess = (e) => { db.close(); resolve(e.target.result); };
        request.onerror   = (e) => { db.close(); reject(e.target.error); };
    });
}
