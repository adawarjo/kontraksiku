/*
 * KKQueue - antrean sinkronisasi offline berbasis IndexedDB.
 *
 * Setiap item dikunci oleh UUID kontraksi (keyPath), sehingga menyimpan ulang
 * kontraksi yang sama hanya memperbarui item yang ada (tidak menggandakan).
 * Item: { uuid, user_id, op: 'sync'|'cancel', started_at, ended_at, notes,
 *         status: 'pending'|'error', error, updated_at }
 * Data di sini BELUM tersimpan di server sampai server mengonfirmasi.
 */
(function (global) {
    'use strict';

    var DB_NAME = 'kontraksiku';
    var DB_VERSION = 1;
    var STORE = 'pending';
    var dbPromise = null;
    var memoryStore = {}; // cadangan bila IndexedDB tidak tersedia (mis. mode privat tertentu)
    var listeners = [];

    function openDb() {
        if (dbPromise) return dbPromise;
        dbPromise = new Promise(function (resolve) {
            if (!('indexedDB' in global)) { resolve(null); return; }
            var req;
            try { req = indexedDB.open(DB_NAME, DB_VERSION); } catch (e) { resolve(null); return; }
            req.onupgradeneeded = function () {
                var db = req.result;
                if (!db.objectStoreNames.contains(STORE)) {
                    var store = db.createObjectStore(STORE, { keyPath: 'uuid' });
                    store.createIndex('user_id', 'user_id', { unique: false });
                }
            };
            req.onsuccess = function () { resolve(req.result); };
            req.onerror = function () { resolve(null); };
            req.onblocked = function () { resolve(null); };
        });
        return dbPromise;
    }

    function tx(mode, fn) {
        return openDb().then(function (db) {
            if (!db) return fn(null);
            return new Promise(function (resolve, reject) {
                var t = db.transaction(STORE, mode);
                var store = t.objectStore(STORE);
                var result;
                Promise.resolve(fn(store)).then(function (r) { result = r; });
                t.oncomplete = function () { resolve(result); };
                t.onerror = function () { reject(t.error); };
                t.onabort = function () { reject(t.error); };
            });
        });
    }

    function reqToPromise(req) {
        return new Promise(function (resolve, reject) {
            req.onsuccess = function () { resolve(req.result); };
            req.onerror = function () { reject(req.error); };
        });
    }

    function notify() {
        listeners.forEach(function (fn) { try { fn(); } catch (e) { /* abaikan */ } });
    }

    var KKQueue = {
        /** Simpan/perbarui item berdasarkan uuid (upsert, tidak menggandakan). */
        put: function (item) {
            item = Object.assign({ status: 'pending', error: null, op: 'sync', notes: null }, item, { updated_at: Date.now() });
            return tx('readwrite', function (store) {
                if (!store) { memoryStore[item.uuid] = item; return item; }
                return reqToPromise(store.put(item)).then(function () { return item; });
            }).then(function (r) { notify(); return r; });
        },

        get: function (uuid) {
            return tx('readonly', function (store) {
                if (!store) return memoryStore[uuid] || null;
                return reqToPromise(store.get(uuid)).then(function (r) { return r || null; });
            });
        },

        remove: function (uuid) {
            return tx('readwrite', function (store) {
                if (!store) { delete memoryStore[uuid]; return true; }
                return reqToPromise(store.delete(uuid)).then(function () { return true; });
            }).then(function (r) { notify(); return r; });
        },

        /** Semua item (opsional: hanya milik user tertentu), urut waktu mulai. */
        all: function (userId) {
            return tx('readonly', function (store) {
                if (!store) return Object.keys(memoryStore).map(function (k) { return memoryStore[k]; });
                return reqToPromise(store.getAll());
            }).then(function (items) {
                items = (items || []).filter(function (it) {
                    return userId == null || userId === '' || String(it.user_id) === String(userId);
                });
                items.sort(function (a, b) { return a.started_at - b.started_at; });
                return items;
            });
        },

        count: function (userId) {
            return KKQueue.all(userId).then(function (items) { return items.length; });
        },

        onChange: function (fn) { listeners.push(fn); },

        isPersistent: function () {
            return openDb().then(function (db) { return !!db; });
        }
    };

    global.KKQueue = KKQueue;
})(window);
