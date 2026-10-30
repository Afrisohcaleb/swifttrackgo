</main>
<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <?php $companyName = getSiteSettingsSafe()['company_name'] ?: APP_NAME; ?>
            <a class="brand" href="<?= e(url()) ?>" style="color:white"><?= e($companyName) ?></a>
            <p>Clear, dependable shipment tracking from dispatch to destination.</p>
        </div>
        <div><h3>Explore</h3><div class="footer-links">
            <a href="<?= e(url('track.php')) ?>">Track a shipment</a>
            <a href="<?= e(url('services.php')) ?>">Our services</a>
            <a href="<?= e(url('about.php')) ?>">About us</a>
        </div></div>
        <div><h3>Information</h3><div class="footer-links">
            <a href="<?= e(url('contact.php')) ?>">Contact</a>
            <a href="<?= e(url('privacy.php')) ?>">Privacy policy</a>
            <a href="<?= e(url('terms.php')) ?>">Terms</a>
        </div></div>
    </div>
    <div class="container"><hr style="border-color:#ffffff30"><small>&copy; <?= date('Y') ?> <?= e($companyName) ?>. Replace this starter footer with approved company details.</small></div>
</footer>
<?php
$_chatSettings = getSiteSettingsSafe();
$_chatEnabled = ($_chatSettings['livechat_enabled'] ?? '0') === '1';
$_chatOnline  = ($_chatSettings['livechat_online'] ?? '0') === '1';
if ($_chatEnabled):
?>
<!-- Built-in live chat widget -->
<div id="stg-chat-widget">
    <button id="stg-chat-toggle" type="button" aria-label="Open support chat" aria-controls="stg-chat-panel" aria-expanded="false">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <span id="stg-chat-dot" class="<?= $_chatOnline ? 'online' : '' ?>" aria-label="<?= $_chatOnline ? 'Support online' : 'Support offline' ?>"></span>
    </button>
    <div id="stg-chat-panel" class="stg-chat-hidden">
        <div id="stg-chat-header">
            <div>
                <strong><?= e($companyName) ?> Support</strong>
                <small id="stg-chat-status"><?= $_chatOnline ? '● Online' : '○ Offline' ?></small>
            </div>
            <button id="stg-chat-close" type="button" aria-label="Close chat">✕</button>
        </div>
        <div id="stg-chat-intro" class="stg-chat-view">
            <div style="text-align:center;padding:2rem 1rem">
                <div style="font-size:2.5rem;margin-bottom:.75rem">💬</div>
                <h3 style="margin:0 0 .4rem;font-size:1.1rem"><?= $_chatOnline ? 'We\'re here to help!' : 'We\'re away right now' ?></h3>
                <p style="color:var(--muted);font-size:.9rem;margin:0 0 1.2rem">Enter your tracking number to start or continue a shipment conversation. Anyone with the tracking number can access its chat.</p>
                <form id="stg-chat-start-form">
                    <p id="stg-chat-error" class="alert alert-danger stg-chat-error" role="alert" hidden></p>
                    <input id="stg-chat-tracking-number" name="tracking_number" placeholder="Tracking number" maxlength="40" autocapitalize="characters" autocomplete="off" required style="margin-bottom:.5rem">
                    <textarea id="stg-chat-first-msg" placeholder="Type a message to start a chat, or leave blank to continue…" rows="3" maxlength="2000" style="margin-bottom:.5rem;resize:vertical"></textarea>
                    <button class="button" type="submit" style="width:100%">Open chat</button>
                </form>
            </div>
        </div>
        <div id="stg-chat-conversation" class="stg-chat-view stg-chat-hidden">
            <p id="stg-chat-conversation-error" class="alert alert-danger stg-chat-error" role="alert" hidden></p>
            <div style="padding:.45rem .75rem;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center">
                <small id="stg-chat-tracking-label"></small>
                <button id="stg-chat-switch" type="button" class="button button-small button-secondary">Change number</button>
            </div>
            <div id="stg-chat-messages"></div>
            <form id="stg-chat-send-form">
                <div style="display:flex;gap:.4rem;padding:.6rem">
                    <input id="stg-chat-input" placeholder="Type a message…" autocomplete="off" required style="flex:1">
                    <button class="button button-small" type="submit">Send</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(function(){
    var apiUrl = <?= json_encode(url('api/chat.php')) ?>;
    var csrfToken = <?= json_encode(csrfToken()) ?>;
    var toggle = document.getElementById('stg-chat-toggle');
    var panel = document.getElementById('stg-chat-panel');
    var closeBtn = document.getElementById('stg-chat-close');
    var introView = document.getElementById('stg-chat-intro');
    var convoView = document.getElementById('stg-chat-conversation');
    var startForm = document.getElementById('stg-chat-start-form');
    var sendForm = document.getElementById('stg-chat-send-form');
    var messagesEl = document.getElementById('stg-chat-messages');
    var inputEl = document.getElementById('stg-chat-input');
    var trackingInput = document.getElementById('stg-chat-tracking-number');
    var trackingLabel = document.getElementById('stg-chat-tracking-label');
    var switchButton = document.getElementById('stg-chat-switch');
    var conversationId = null;
    var trackingNumber = null;
    var pollTimer = null;
    var statusTimer = null;

    // Restore session
    try {
        var saved = sessionStorage.getItem('stg_chat');
        if (saved) {
            var data = JSON.parse(saved);
            if (data.trackingNumber && data.id) {
                conversationId = data.id;
                trackingNumber = data.trackingNumber;
                trackingLabel.textContent = 'Shipment ' + trackingNumber;
            } else {
                sessionStorage.removeItem('stg_chat');
            }
        }
    } catch(e){}

    function updateStatus(online) {
        var statusEl = document.getElementById('stg-chat-status');
        var dotEl = document.getElementById('stg-chat-dot');
        if (!statusEl || !dotEl) return;
        statusEl.textContent = online ? '● Online' : '○ Offline';
        dotEl.className = online ? 'online' : '';
        dotEl.setAttribute('aria-label', online ? 'Support online' : 'Support offline');
    }

    function refreshStatus() {
        fetch(apiUrl + '?action=status', {headers: {'Accept': 'application/json'}})
        .then(function(response){ return response.json(); })
        .then(function(data){
            if (data.enabled === false) {
                var widget = document.getElementById('stg-chat-widget');
                if (widget) widget.remove();
                if (statusTimer) clearInterval(statusTimer);
                return;
            }
            updateStatus(data.online === true);
        })
        .catch(function(){});
    }

    function showChatError(message) {
        var errorEl = convoView.classList.contains('stg-chat-hidden')
            ? document.getElementById('stg-chat-error')
            : document.getElementById('stg-chat-conversation-error');
        if (!errorEl) return;
        errorEl.textContent = message;
        errorEl.hidden = false;
    }

    function setPanelOpen(open) {
        panel.classList.toggle('stg-chat-hidden', !open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open && conversationId) {
            showConversation();
            loadMessages();
        }
    }

    refreshStatus();
    statusTimer = setInterval(refreshStatus, 30000);

    toggle.addEventListener('click', function(){
        setPanelOpen(panel.classList.contains('stg-chat-hidden'));
    });

    closeBtn.addEventListener('click', function(){
        setPanelOpen(false);
    });

    switchButton.addEventListener('click', function(){
        if (pollTimer) clearInterval(pollTimer);
        conversationId = null;
        trackingNumber = null;
        messagesEl.innerHTML = '';
        trackingInput.value = '';
        document.getElementById('stg-chat-first-msg').value = '';
        document.getElementById('stg-chat-error').hidden = true;
        document.getElementById('stg-chat-conversation-error').hidden = true;
        convoView.classList.add('stg-chat-hidden');
        introView.classList.remove('stg-chat-hidden');
        try { sessionStorage.removeItem('stg_chat'); } catch(e){}
        trackingInput.focus();
    });

    startForm.addEventListener('submit', function(e){
        e.preventDefault();
        var tracking = trackingInput.value.trim().toUpperCase();
        var msg = document.getElementById('stg-chat-first-msg').value.trim();
        if (!tracking) return;
        document.getElementById('stg-chat-error').hidden = true;
        var fd = new FormData();
        fd.append('action', 'start');
        fd.append('tracking_number', tracking);
        fd.append('message', msg);
        fd.append('csrf_token', csrfToken);
        fetch(apiUrl, { method: 'POST', body: fd })
        .then(function(r){ return r.json().then(function(data){ return {ok:r.ok, data:data}; }); })
        .then(function(data){
            if (data.ok && data.data.conversation_id && data.data.tracking_number) {
                conversationId = data.data.conversation_id;
                trackingNumber = data.data.tracking_number;
                trackingLabel.textContent = 'Shipment ' + trackingNumber;
                document.getElementById('stg-chat-first-msg').value = '';
                try { sessionStorage.setItem('stg_chat', JSON.stringify({id: conversationId, trackingNumber: trackingNumber})); } catch(e){}
                showConversation();
                loadMessages();
            } else {
                showChatError(data.data.error || 'Unable to start chat. Please try again.');
            }
        }).catch(function(){ showChatError('Unable to connect to support. Please try again.'); });
    });

    sendForm.addEventListener('submit', function(e){
        e.preventDefault();
        var msg = inputEl.value.trim();
        if (!msg || !conversationId || !trackingNumber) return;
        inputEl.value = '';
        appendMessage(msg, 'visitor');
        var fd = new FormData();
        fd.append('action', 'send');
        fd.append('conversation_id', conversationId);
        fd.append('tracking_number', trackingNumber);
        fd.append('message', msg);
        fd.append('csrf_token', csrfToken);
        fetch(apiUrl, { method: 'POST', body: fd })
        .then(function(r){ return r.json().then(function(data){ if (!r.ok) showChatError(data.error || 'Message could not be sent.'); }); })
        .catch(function(){ showChatError('Unable to connect to support. Please try again.'); });
    });

    function showConversation(){
        introView.classList.add('stg-chat-hidden');
        convoView.classList.remove('stg-chat-hidden');
        startPoll();
    }

    function appendMessage(text, type){
        var div = document.createElement('div');
        div.className = 'stg-msg stg-msg-' + type;
        div.textContent = text;
        messagesEl.appendChild(div);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function loadMessages(){
        if (!conversationId || !trackingNumber) return;
        var query = new URLSearchParams({action:'messages', tracking_number:trackingNumber});
        fetch(apiUrl + '?' + query.toString())
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (!data.messages) return;
            messagesEl.innerHTML = '';
            data.messages.forEach(function(m){
                appendMessage(m.message, m.sender_type);
            });
            // Update online status
            updateStatus(data.online === true);
        });
    }

    function startPoll(){
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(loadMessages, 5000);
    }

    // If returning visitor has a conversation, show it on open
    if (conversationId) {
        introView.classList.add('stg-chat-hidden');
        convoView.classList.remove('stg-chat-hidden');
    }
})();
</script>
<?php endif; ?>
</body>
</html>
