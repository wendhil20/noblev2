// js/notifications.js — Global Notification Toast + Flyout

(function () {
    // ─── Toast System ─────────────────────────────────────────
    let lastKnownIds = null;
    let toastContainer = null;
    let toastQueue = [];
    let isShowingToast = false;

    const SEEN_IDS_KEY = 'notif_seen_ids';
    const LOGIN_USER_KEY = 'notif_login_user';
    const currentUser = String(typeof CURRENT_USER_ID !== 'undefined' ? CURRENT_USER_ID : 0);
    const storedUser = sessionStorage.getItem(LOGIN_USER_KEY);

    if (storedUser !== currentUser) {
        sessionStorage.removeItem(SEEN_IDS_KEY);
        sessionStorage.setItem(LOGIN_USER_KEY, currentUser);
    }

    function getSeenIds() {
        try { return new Set(JSON.parse(sessionStorage.getItem(SEEN_IDS_KEY) || '[]')); }
        catch { return new Set(); }
    }

    function addSeenIds(ids) {
        const seen = getSeenIds();
        ids.forEach(id => seen.add(id));
        sessionStorage.setItem(SEEN_IDS_KEY, JSON.stringify([...seen]));
    }

    function initToast() {
        toastContainer = document.createElement('div');
        toastContainer.id = 'global-toast-container';
        toastContainer.style.cssText = `
            position:fixed;top:16px;left:50%;transform:translateX(-50%);
            z-index:99999;display:flex;flex-direction:column;
            align-items:center;gap:8px;pointer-events:none;
        `;
        document.body.appendChild(toastContainer);
        poll();
        setInterval(poll, 5000);
    }

    function poll() {
        fetch(BASE_URL + '/fetchnotifications')
            .then(r => r.json())
            .then(data => {
                const currentIds = data.map(n => n.id);
                const seenIds = getSeenIds();
                const toShow = data.filter(n => n.is_read == 0 && !seenIds.has(n.id));

                if (toShow.length > 0) {
                    toShow.forEach(n => toastQueue.push(n));
                    addSeenIds(toShow.map(n => n.id));
                    processQueue();
                }
                lastKnownIds = currentIds;

                // Update badge
                const unread = data.filter(n => !n.is_read).length;
                const badge = document.getElementById('notif-badge');
                if (badge) {
                    badge.textContent = unread;
                    badge.classList.toggle('hidden', unread === 0);
                }
            })
            .catch(() => {});
    }

    function processQueue() {
        if (isShowingToast || toastQueue.length === 0) return;
        isShowingToast = true;
        const notif = toastQueue.shift();
        playSound();
        showToast(notif, () => {
            isShowingToast = false;
            setTimeout(processQueue, 400);
        });
    }

    function showToast(notif, onDone) {
        if (!document.getElementById('toast-anim-style')) {
            const style = document.createElement('style');
            style.id = 'toast-anim-style';
            style.textContent = `
                @keyframes toastSlideIn { from{opacity:0;transform:translateY(-16px)} to{opacity:1;transform:translateY(0)} }
                @keyframes toastFadeOut { from{opacity:1} to{opacity:0} }
            `;
            document.head.appendChild(style);
        }

        const toast = document.createElement('div');
        toast.style.cssText = `
            background:#111111;border-left:2px solid #ffa200;border-radius:5px;
            padding:12px 16px;min-width:280px;max-width:480px;
            box-shadow:0 4px 24px rgba(0,0,0,0.4);pointer-events:none;
            position:relative;animation:toastSlideIn 0.3s ease;
        `;
        toast.innerHTML = `
            <div style="display:flex;align-items:flex-start;gap:10px;">
                <div style="width:32px;height:32px;border-radius:50%;background:rgba(245,158,11,0.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fa-regular fa-bell" style="color:#f59e0b;font-size:14px;"></i>
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:12px;font-weight:600;color:#ffffff;">${notif.title ?? 'Notification'}</div>
                    <div style="font-size:11px;color:#9ca3af;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${notif.message ?? ''}</div>
                    <div style="font-size:10px;color:#6b7280;margin-top:4px;">${timeAgo(notif.created_at)}</div>
                </div>
            </div>
        `;

        toastContainer.appendChild(toast);
        const timer = setTimeout(() => dismissToast(toast, onDone), 5000);
        toast._autoTimer = timer;
    }

    function dismissToast(toast, onDone) {
        if (!toast.isConnected) { if (onDone) onDone(); return; }
        clearTimeout(toast._autoTimer);
        toast.style.animation = 'toastFadeOut 0.3s ease forwards';
        setTimeout(() => { toast.remove(); if (onDone) onDone(); }, 300);
    }

    function playSound() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(440, ctx.currentTime + 0.3);
            gain.gain.setValueAtTime(0.25, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
            osc.start();
            osc.stop(ctx.currentTime + 0.3);
        } catch (e) {}
    }

    // ─── Flyout Panel Functions (global) ──────────────────────
    window.notifOpen = false;

    window.toggleNotif = function () {
        window.notifOpen = !window.notifOpen;
        const panel = document.getElementById('notif-panel');
        const backdrop = document.getElementById('notif-backdrop');
        if (window.notifOpen) {
            panel.classList.remove('translate-x-full');
            backdrop.classList.remove('hidden');
            fetchNotifList();
        } else {
            panel.classList.add('translate-x-full');
            backdrop.classList.add('hidden');
        }
    };

    window.fetchNotifList = function () {
        fetch(BASE_URL + '/fetchnotifications')
            .then(r => r.json())
            .then(data => renderNotifications(data))
            .catch(() => {
                document.getElementById('notif-list').innerHTML =
                    '<div class="px-4 py-4 text-xs text-gray-400 text-center">Failed to load.</div>';
            });
    };

    window.markAllRead = function () {
        fetch(BASE_URL + '/marknotificationread', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'all=1'
        }).then(() => window.fetchNotifList());
    };

    function renderNotifications(notifs) {
        const list = document.getElementById('notif-list');
        const badge = document.getElementById('notif-badge');
        const unread = notifs.filter(n => !n.is_read).length;

        if (badge) {
            badge.textContent = unread;
            badge.classList.toggle('hidden', unread === 0);
        }

        if (!notifs.length) {
            list.innerHTML = '<div class="px-4 py-4 text-xs text-gray-400 text-center">No notifications yet.</div>';
            return;
        }

        list.innerHTML = notifs.map(n => `
            <div data-id="${n.id}" class="notif-row flex items-start gap-3 px-4 py-3 cursor-pointer hover:bg-amber-50 transition-colors duration-150 ${n.is_read ? 'bg-white' : 'bg-amber-50/40'}">
                <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <i class="fa-solid fa-bell text-gray-400 text-xs"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold text-gray-800 leading-snug">${n.title}</p>
                    <p class="text-[11px] text-gray-500 mt-0.5 leading-snug">${n.message}</p>
                    <p class="text-[10px] text-gray-400 mt-1">${timeAgo(n.created_at)}</p>
                </div>
                <span class="notif-dot w-1.5 h-1.5 rounded-full flex-shrink-0 mt-2 ${n.is_read ? 'bg-transparent' : 'bg-amber-400'}"></span>
            </div>
        `).join('');

        document.querySelectorAll('.notif-row').forEach(row => {
            row.addEventListener('click', function () {
                const id = parseInt(this.dataset.id);
                const notif = notifs.find(n => n.id === id);
                readNotif(id, this, notif?.link);
            });
        });
    }

    function readNotif(id, el, link) {
        fetch(BASE_URL + '/marknotificationread', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}`
        }).then(() => { if (link) window.location.href = link; });

        const dot = el.querySelector('.notif-dot');
        if (dot) { dot.classList.remove('bg-amber-400'); dot.classList.add('bg-transparent'); }
        el.classList.remove('bg-amber-50/40');
        el.classList.add('bg-white');

        const badge = document.getElementById('notif-badge');
        const count = Math.max(0, parseInt(badge.textContent) - 1);
        badge.textContent = count;
        badge.classList.toggle('hidden', count === 0);
    }

    function timeAgo(ts) {
        const diff = Math.floor((Date.now() - new Date(ts)) / 1000);
        if (diff < 60) return `${diff}s ago`;
        if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
        if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
        return `${Math.floor(diff / 86400)}d ago`;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initToast);
    } else {
        initToast();
    }
})();