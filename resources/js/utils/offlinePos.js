const DB_NAME = 'qlbh-offline-v1'
const DB_VERSION = 1
const PRODUCT_STORE = 'products'
const QUEUE_STORE = 'sales'

let databasePromise

function database() {
    if (!('indexedDB' in window)) return Promise.reject(new Error('Trình duyệt không hỗ trợ lưu dữ liệu offline.'))
    if (!databasePromise) {
        databasePromise = new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION)
            request.onupgradeneeded = () => {
                const db = request.result
                if (!db.objectStoreNames.contains(PRODUCT_STORE)) db.createObjectStore(PRODUCT_STORE, { keyPath: 'key' })
                if (!db.objectStoreNames.contains(QUEUE_STORE)) db.createObjectStore(QUEUE_STORE, { keyPath: 'id' })
            }
            request.onsuccess = () => resolve(request.result)
            request.onerror = () => reject(request.error)
        })
    }
    return databasePromise
}

async function request(storeName, mode, action) {
    const db = await database()
    return new Promise((resolve, reject) => {
        const tx = db.transaction(storeName, mode)
        const req = action(tx.objectStore(storeName))
        req.onsuccess = () => resolve(req.result)
        req.onerror = () => reject(req.error)
        tx.onabort = () => reject(tx.error)
    })
}

export const cacheOfflineProducts = (userId, query, products) => request(PRODUCT_STORE, 'readwrite', store =>
    store.put({ key: `${userId}:${query}`, products, savedAt: Date.now() })
)

export const readOfflineProducts = (userId, query) => request(PRODUCT_STORE, 'readonly', store =>
    store.get(`${userId}:${query}`)
)

export const enqueueOfflineSale = sale => request(QUEUE_STORE, 'readwrite', store => store.put(sale))
export const removeOfflineSale = id => request(QUEUE_STORE, 'readwrite', store => store.delete(id))
export const readOfflineSales = async userId => {
    const sales = await request(QUEUE_STORE, 'readonly', store => store.getAll())
    return userId === undefined ? sales : sales.filter(sale => String(sale.userId) === String(userId))
}
