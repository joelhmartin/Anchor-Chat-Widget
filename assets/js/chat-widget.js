(function(){
	// Strings passed from PHP
	var STR = (window.ACCW_STRINGS || {});
	function byId(id){ return document.getElementById(id); }

	function init(){
		var chatToggle = byId('chatToggle');
		var chatWindow = byId('chatWindow');
		var chatHelper = byId('chatHelper');
		var headerTitle = byId('accwHeaderTitle');
		var headerSubtitle = byId('accwHeaderSubtitle');

		if(headerTitle){ headerTitle.textContent = STR.headerTitle || 'Chat with us'; }
		if(headerSubtitle){ headerSubtitle.textContent = STR.headerSubtitle || "We're here to help!"; }
		if(chatHelper){ chatHelper.textContent = STR.helperText || 'Hi, how can we help?'; }

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