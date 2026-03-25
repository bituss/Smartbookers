(function () {
  // Mobil menü nyitás/zárás
  const menuToggle = document.getElementById('menuToggle');
  if (menuToggle) {
    menuToggle.addEventListener('click', (e) => {
      e.preventDefault();
      document.body.classList.toggle('menu-open');
    });
  }

  // NAV: sorok számolása (ugrálás ellen)
  function countRowsByTop(nav) {
    const items = Array.from(nav.children);
    const tops = new Set();
    items.forEach(el => tops.add(Math.round(el.getBoundingClientRect().top)));
    return tops.size;
  }

  let scheduled = false;

  function syncHeaderMenu() {
    const nav = document.querySelector('.nav-desktop');
    if (!nav) return;

    const navStyle = window.getComputedStyle(nav);
    if (navStyle.display === 'none') {
      document.body.classList.remove('nav-compact');
      return;
    }

    if (scheduled) return;
    scheduled = true;

    requestAnimationFrame(() => {
      scheduled = false;

      const chatMenu = document.getElementById('chatMenu');
      if (chatMenu && chatMenu.classList.contains('is-open')) return;

      const rows = countRowsByTop(nav);
      const shouldCompact = rows >= 3;

      const isCompact = document.body.classList.contains('nav-compact');
      if (shouldCompact !== isCompact) {
        document.body.classList.toggle('nav-compact', shouldCompact);
      }
    });
  }

  const ro = new ResizeObserver(syncHeaderMenu);

  window.addEventListener('load', () => {
    const nav = document.querySelector('.nav-desktop');
    if (nav) ro.observe(nav);
    syncHeaderMenu();

    if (document.fonts && document.fonts.ready) {
      document.fonts.ready.then(syncHeaderMenu);
    }
  });

  window.addEventListener('resize', syncHeaderMenu);

  // Mobil menü bezárása linkre kattintva
  document.addEventListener('click', (e) => {
    if (e.target.closest('.nav-mobile a')) {
      document.body.classList.remove('menu-open');
    }
  });
})();

(function () {
  const btn = document.getElementById('chatBtn');
  const mobileBtn = document.getElementById('chatBtnMobile');

  const menu = document.getElementById('chatMenu');
  const close = document.getElementById('chatClose');

  const wrap = document.getElementById('chatDD'); // desktop gomb konténer

  const threadsBox = document.getElementById('chatThreads');
  const badge = document.getElementById('chatBadge');
  const mobileBadge = document.getElementById('chatBadgeMobile');

  const panelHead = document.getElementById('chatPanelHead');
  const msgsBox = document.getElementById('chatMessages');

  const form = document.getElementById('chatForm');
  const input = document.getElementById('chatInput');
  const sendBtn = document.getElementById('chatSend');
  const convIdEl = document.getElementById('chatConversationId');

  if (!menu) return;

  const DEFAULT_AVATAR = '/Smartbookers/public/images/avatars/a1.png';

  let activeConversationId = 0;
  let polling = null;

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, m => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[m]));
  }

  function openMenu() {
    menu.classList.add('is-open');
    menu.setAttribute('aria-hidden', 'false');

    if (threadsBox && badge) {
      loadThreads(true);
      startPolling();
    }
  }

  function closeMenu() {
    menu.classList.remove('is-open');
    menu.setAttribute('aria-hidden', 'true');
    stopPolling();
  }

  function toggleMenu() {
    menu.classList.contains('is-open') ? closeMenu() : openMenu();
  }

  if (btn) {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      toggleMenu();
    });
  }

  if (mobileBtn) {
    mobileBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      toggleMenu();
    });
  }

  if (close) {
    close.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      closeMenu();
    });
  }

  // kattintás kívül → zár
  document.addEventListener('click', (e) => {
    if (!menu.classList.contains('is-open')) return;

    const inDesktopBtn = wrap ? wrap.contains(e.target) : false;
    const inMobileBtn = mobileBtn ? mobileBtn.contains(e.target) : false;
    const inMenu = menu.contains(e.target);

    if (!inDesktopBtn && !inMobileBtn && !inMenu) closeMenu();
  });

  if (!threadsBox || !badge || !msgsBox || !panelHead || !form || !input || !sendBtn || !convIdEl) return;

  function startPolling() {
    stopPolling();
    polling = setInterval(() => {
      loadThreads(false);
      if (activeConversationId) loadMessages(activeConversationId, true);
    }, 5000);
  }

  function stopPolling() {
    if (polling) clearInterval(polling);
    polling = null;
  }

  function setActiveConversation(id, title) {
    activeConversationId = id | 0;
    convIdEl.value = activeConversationId ? String(activeConversationId) : '';
    panelHead.textContent = title || 'Beszélgetés';

    const canWrite = activeConversationId > 0;
    input.disabled = !canWrite;
    sendBtn.disabled = !canWrite;

    if (!canWrite) {
      msgsBox.innerHTML = '<div class="chat-empty">Nincs kiválasztott beszélgetés.</div>';
    }

    threadsBox.querySelectorAll('.thread').forEach(t => {
      t.classList.toggle('active', parseInt(t.getAttribute('data-id'), 10) === activeConversationId);
    });
  }

  async function loadThreads(first) {
    try {
      const r = await fetch('/Smartbookers/api/chat_threads.php', { credentials: 'include' });
      const data = await r.json();

      const unreadTotal = parseInt(data.unread_total || 0, 10);

      if (unreadTotal > 0) {
        badge.style.display = 'inline-flex';
        badge.textContent = String(unreadTotal);

        if (mobileBadge) {
          mobileBadge.style.display = 'inline-flex';
          mobileBadge.textContent = String(unreadTotal);
        }
      } else {
        badge.style.display = 'none';
        if (mobileBadge) mobileBadge.style.display = 'none';
      }

      const threads = data.threads || [];
      if (threads.length === 0) {
        threadsBox.innerHTML = '<div class="chat-empty">Nincs üzenet (még).</div>';
        setActiveConversation(0, 'Válassz egy beszélgetést');
        return;
      }

      threadsBox.innerHTML = threads.map(t => {
        const avatar = t.avatar ? esc(t.avatar) : DEFAULT_AVATAR;

        return `
          <div class="thread ${activeConversationId == t.conversation_id ? 'active' : ''}"
               data-id="${t.conversation_id}"
               data-title="${esc(t.title || 'Beszélgetés')}"
               data-avatar="${avatar}">
            <img class="threadAvatar" src="${avatar}" alt="avatar">
            <div class="meta">
              <div class="name">${esc(t.title || 'Beszélgetés')}</div>
              <div class="last">${esc(t.last_message || '')}</div>
            </div>
            ${parseInt(t.unread_count || 0, 10) > 0 ? `<div class="pill">${t.unread_count}</div>` : ``}
          </div>
        `;
      }).join('');

      threadsBox.querySelectorAll('.thread').forEach(el => {
        el.addEventListener('click', () => {
          const id = parseInt(el.getAttribute('data-id'), 10);
          const title = el.getAttribute('data-title') || 'Beszélgetés';
          setActiveConversation(id, title);
          loadMessages(id, false);
        });
      });

      if (first && !activeConversationId) {
        const firstEl = threadsBox.querySelector('.thread');
        if (firstEl) firstEl.click();
      }
    } catch (e) {
      threadsBox.innerHTML = '<div class="chat-empty">Hiba a beszélgetések betöltésénél.</div>';
    }
  }

  async function loadMessages(conversationId, keepScroll) {
    try {
      const r = await fetch(
        '/Smartbookers/api/chat_messages.php?conversation_id=' + encodeURIComponent(conversationId),
        { credentials: 'include' }
      );
      const data = await r.json();

      if (data.error) {
        msgsBox.innerHTML = '<div class="chat-empty">' + esc(data.error) + '</div>';
        return;
      }

      const atBottom = msgsBox.scrollTop + msgsBox.clientHeight >= msgsBox.scrollHeight - 40;

      // kivesszük a thread avatarját (a másik fél)
      const activeThread = threadsBox.querySelector(`.thread[data-id="${conversationId}"]`);
      const otherAvatar = activeThread?.getAttribute('data-avatar') || DEFAULT_AVATAR;

      const msgs = data.messages || [];
      if (msgs.length === 0) {
        msgsBox.innerHTML = '<div class="chat-empty">Még nincs üzenet. Írj elsőként!</div>';
      } else {
        msgsBox.innerHTML = msgs.map(m => {
          // API-ból jöhet avatar is, ha adsz (opcionális)
          const avatar = m.avatar ? esc(m.avatar) : otherAvatar;

          return `
            <div class="msgRow ${m.is_me ? 'me' : 'other'}">
              ${m.is_me ? '' : `<img class="msgAvatar" src="${avatar}" alt="avatar">`}
              <div class="bubble">
                ${esc(m.body).replace(/\n/g, '<br>')}
                <div class="msgTime">${esc(m.created_at)}</div>
              </div>
            </div>
          `;
        }).join('');
      }

      if (!keepScroll || atBottom) msgsBox.scrollTop = msgsBox.scrollHeight;
    } catch (e) {
      msgsBox.innerHTML = '<div class="chat-empty">Hiba az üzenetek betöltésénél.</div>';
    }
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const cid = parseInt(convIdEl.value || '0', 10);
    const text = (input.value || '').trim();
    if (!cid || !text) return;

    input.disabled = true;
    sendBtn.disabled = true;

    try {
      const fd = new FormData();
      fd.append('conversation_id', String(cid));
      fd.append('body', text);

      const r = await fetch('/Smartbookers/api/chat_send.php', {
        method: 'POST',
        body: fd,
        credentials: 'include'
      });

      const data = await r.json();

      if (data.error) {
        alert(data.error);
      } else {
        input.value = '';
        await loadMessages(cid, false);
        await loadThreads(false);
      }
    } catch (err) {
      alert('Hiba küldés közben.');
    } finally {
      input.disabled = false;
      sendBtn.disabled = false;
      input.focus();
    }
  });
})();

let logoutUrl = "/Smartbookers/public/logout.php";

function openLogoutConfirm(e){
  e.preventDefault();
  document.getElementById('logoutModal').style.display = 'flex';
}

function closeLogout(){
  document.getElementById('logoutModal').style.display = 'none';
}

function confirmLogout(){
  window.location.href = logoutUrl;
}
