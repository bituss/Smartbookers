document.addEventListener("DOMContentLoaded", () => {
    const box = document.getElementById("msgs");
    const form = document.getElementById("chatForm");
    const textarea = document.getElementById("body");
    if (!box || !form || !window.CHAT) return;
    let conversationId = window.CHAT.conversationId;
    let lastId = window.CHAT.lastId || 0;
    let role = window.CHAT.role;
    function scrollDown() {
      box.scrollTop = box.scrollHeight;
    }
    scrollDown();
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const body = textarea.value.trim();
      if (!body) return;
      try {
        const res = await fetch("/Smartbookers/chat/send_messages.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: new URLSearchParams({
            conversation_id: conversationId,
            body: body
          })
        });
        const data = await res.json();
        if (data.ok) {
          textarea.value = "";
          fetchNewMessages(); 
        }
      } catch (err) {
        console.error("Küldési hiba:", err);
      }
    });
    async function fetchNewMessages() {
      try {
        const res = await fetch(
          `/Smartbookers/chat/get_messages.php?conversation_id=${conversationId}&after_id=${lastId}`
        );
        const data = await res.json();
        if (!data.ok) return;
        data.messages.forEach(msg => {
          if (document.querySelector(`[data-id='${msg.id}']`)) return;
          const div = document.createElement("div");
          div.className = "m " + (msg.is_me ? "me" : "other");
          div.dataset.id = msg.id;
          div.innerHTML =
            escapeHtml(msg.body).replace(/\n/g, "<br>") +
            `<div class="time">${msg.created_at}</div>`;
          box.appendChild(div);
          lastId = Math.max(lastId, msg.id);
        });
        scrollDown();
      } catch (err) {
        console.error("Frissítési hiba:", err);
      }
    }
    setInterval(fetchNewMessages, 3000);
    function escapeHtml(text) {
      return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    }
  });