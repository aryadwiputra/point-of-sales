import { openDB } from 'idb';

const DB_NAME = 'pos-offline';
const DB_VERSION = 1;

const dbPromise = openDB(DB_NAME, DB_VERSION, {
    upgrade(db) {
        if (!db.objectStoreNames.contains('products')) {
            db.createObjectStore('products', { keyPath: 'id' });
        }
        if (!db.objectStoreNames.contains('customers')) {
            db.createObjectStore('customers', { keyPath: 'id' });
        }
        if (!db.objectStoreNames.contains('pricing')) {
            db.createObjectStore('pricing', { keyPath: 'id' });
        }
        if (!db.objectStoreNames.contains('pending_transactions')) {
            db.createObjectStore('pending_transactions', { keyPath: 'id', autoIncrement: true });
        }
    },
});

export async function cacheProducts(products) {
    const db = await dbPromise;
    const tx = db.transaction('products', 'readwrite');
    for (const product of products) {
        await tx.store.put(product);
    }
    await tx.done;
}

export async function getCachedProducts() {
    const db = await dbPromise;
    return db.getAll('products');
}

export async function cacheCustomers(customers) {
    const db = await dbPromise;
    const tx = db.transaction('customers', 'readwrite');
    for (const customer of customers) {
        await tx.store.put(customer);
    }
    await tx.done;
}

export async function getCachedCustomers() {
    const db = await dbPromise;
    return db.getAll('customers');
}

export async function queueTransaction(transactionData) {
    const db = await dbPromise;
    return db.add('pending_transactions', {
        data: transactionData,
        created_at: new Date().toISOString(),
    });
}

export async function getPendingTransactions() {
    const db = await dbPromise;
    return db.getAll('pending_transactions');
}

export async function getPendingCount() {
    const db = await dbPromise;
    return db.count('pending_transactions');
}

export async function removePendingTransaction(id) {
    const db = await dbPromise;
    return db.delete('pending_transactions', id);
}

export async function clearPendingTransactions() {
    const db = await dbPromise;
    await db.clear('pending_transactions');
}
