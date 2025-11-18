(function(){
	// Strings/config passed from PHP.
	var STR = (window.ACCW_STRINGS || {});
	var CFG = (window.ACCW_CONFIG || {});
	function byId(id){ return document.getElementById(id); }

	function forwardTranscript(session) {
		if (!CFG.forwardTranscriptUrl) {
			return;
		}
		if (!session || !session.messages || !session.messages.length) {
			return;
		}

		var payload = {
			clientId: CFG.clientId || '',
			sessionId: session.id,
			startedAt: session.startedAt,
			endedAt: new Date().toISOString(),
			meta: {
				page: window.location.href,
				title: document.title
			},
			messages: session.messages.map(function (m) {
				return {
					role: m.role,
					text: m.text,
					at: m.at
				};
			})
		};

		var url = CFG.forwardTranscriptUrl;
		if (CFG.forwardToken) {
			var sep = url.indexOf('?') === -1 ? '?' : '&';
			url = url + sep + 'token=' + encodeURIComponent(CFG.forwardToken);
		}

		fetch(url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json'
			},
			body: JSON.stringify(payload)
		}).catch(function () {
			// No logging of errors with PHI
		});
	}

	// Expose so the chatbot implementation can trigger it when chats expire.
	window.ACCW_forwardTranscript = forwardTranscript;

	function init(){
		var chatToggle = byId('chatToggle');
		var chatWindow = byId('chatWindow');
		var chatHelper = byId('chatHelper');
		var headerTitle = byId('accwHeaderTitle');
		var headerSubtitle = byId('accwHeaderSubtitle');

		if(headerTitle){ headerTitle.textContent = STR.headerTitle || CFG.headerTitle || 'Chat with us'; }
		if(headerSubtitle){ headerSubtitle.textContent = STR.headerSubtitle || CFG.headerSubtitle || "We're here to help!"; }
		if(chatHelper){ chatHelper.textContent = STR.helperText || CFG.helperText || 'Hi, how can we help?'; }

		if(!chatToggle || !chatWindow){ return; }

		function openChat(){
			chatWindow.classList.add('open');
			chatToggle.classList.add('active');
			chatToggle.setAttribute('aria-expanded', 'true');
			if(chatHelper){ chatHelper.style.display = 'none'; }
		}
		function closeChat(){
			chatWindow.classList.remove('open');
			chatToggle.classList.remove('active');
			chatToggle.setAttribute('aria-expanded', 'false');
		}

		chatToggle.addEventListener('click', function(){
			if(chatWindow.classList.contains('open')){
				closeChat();
			}else{
				openChat();
			}
		});

		// Keyboard support
		chatToggle.addEventListener('keydown', function(e){
			if(e.key === 'Enter' || e.key === ' '){
				e.preventDefault();
				chatToggle.click();
			}
		});
	}

	if(document.readyState === 'loading'){
		document.addEventListener('DOMContentLoaded', init);
	}else{
		init();
	}
})();
