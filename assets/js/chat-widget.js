(function () {
	// Strings/config passed from PHP.
	var STR = window.ACCW_STRINGS || {};
	var CFG = window.ACCW_CONFIG || {};

	function byId(id) {
		return document.getElementById(id);
	}

	function forwardTranscript(session) {
		if (!CFG.forwardTranscriptUrl) {
			return;
		}
		if (!session || !session.messages || !session.messages.length) {
			return;
		}

		// Build transcript object for Cloud Run
		var transcript = {
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

		// Match Cloud Run TranscriptPayload: { transcript, clientId, token }
		var payload = {
			clientId: CFG.clientId || "",
			token: CFG.forwardToken || "",
			transcript: transcript
		};

		fetch(CFG.forwardTranscriptUrl, {
			method: "POST",
			headers: {
				"Content-Type": "application/json"
			},
			body: JSON.stringify(payload)
		}).catch(function () {
			// No logging of errors with PHI
		});
	}

	// Expose so other scripts can trigger transcript forwarding.
	window.ACCW_forwardTranscript = forwardTranscript;

	function initToggle() {
		var chatToggle = byId("chatToggle");
		var chatWindow = byId("chatWindow");
		var chatHelper = byId("chatHelper");
		var headerTitle = byId("accwHeaderTitle");
		var headerSubtitle = byId("accwHeaderSubtitle");

		if (headerTitle) {
			headerTitle.textContent = STR.headerTitle || CFG.headerTitle || "Chat with us";
		}
		if (headerSubtitle) {
			headerSubtitle.textContent = STR.headerSubtitle || CFG.headerSubtitle || "We're here to help!";
		}
		if (chatHelper) {
			chatHelper.textContent = STR.helperText || CFG.helperText || "Hi, how can we help?";
		}

		if (!chatToggle || !chatWindow) {
			return;
		}

		function openChat() {
			chatWindow.classList.add("open");
			chatToggle.classList.add("active");
			chatToggle.setAttribute("aria-expanded", "true");
			if (chatHelper) {
				chatHelper.style.display = "none";
			}
		}

		function closeChat() {
			chatWindow.classList.remove("open");
			chatToggle.classList.remove("active");
			chatToggle.setAttribute("aria-expanded", "false");
		}

		chatToggle.addEventListener("click", function () {
			if (chatWindow.classList.contains("open")) {
				closeChat();
			} else {
				openChat();
			}
		});

		chatToggle.addEventListener("keydown", function (e) {
			if (e.key === "Enter" || e.key === " ") {
				e.preventDefault();
				chatToggle.click();
			}
		});
	}

	function initChatInterfaces() {
		var containers = document.querySelectorAll("[data-accw-chatbot]");
		if (!containers.length) {
			return;
		}

		containers.forEach(function (container) {
			var messagesEl = container.querySelector("[data-accw-messages]");
			var form = container.querySelector("[data-accw-form]");
			var input = container.querySelector("[data-accw-input]");
			var statusEl = container.querySelector("[data-accw-status]");
			var endBtn = container.querySelector("[data-accw-end]");

			if (!messagesEl || !form || !input) {
				return;
			}

			var introText = container.getAttribute("data-accw-intro");
			var session = null;
			var isSending = false;

			function createSession() {
				return {
					id: "accw-" + Date.now() + "-" + Math.random().toString(16).slice(2),
					startedAt: new Date().toISOString(),
					messages: []
				};
			}

			function setStatus(message, isError) {
				if (!statusEl) {
					return;
				}
				statusEl.textContent = message || "";
				statusEl.classList.toggle("is-error", Boolean(isError && message));
			}

			function toggleForm(disabled) {
				var elements = [input];
				var sendButton = form.querySelector("[data-accw-send]");
				var endButton = endBtn || form.querySelector("[data-accw-end]");
				if (sendButton) {
					elements.push(sendButton);
				}
				if (endButton) {
					elements.push(endButton);
				}
				elements.forEach(function (el) {
					if (!el) {
						return;
					}
					el.disabled = Boolean(disabled);
				});
				form.classList.toggle("is-disabled", Boolean(disabled));
			}

			function addMessage(role, text, options) {
				if (!text) {
					return;
				}
				var normalizedRole =
					role === "assistant" ? "assistant" : role === "system" ? "system" : "user";
				var bubble = document.createElement("div");
				var className = "accw-message ";

				if (normalizedRole === "assistant") {
					className += "accw-message-bot";
				} else if (normalizedRole === "user") {
					className += "accw-message-user";
				} else {
					className += "accw-message-system";
				}

				bubble.className = className;
				bubble.textContent = text;
				messagesEl.appendChild(bubble);
				messagesEl.scrollTop = messagesEl.scrollHeight;

				if (normalizedRole === "system") {
					return;
				}

				if (!options || options.store !== false) {
					session.messages.push({
						role: normalizedRole,
						text: text,
						at: new Date().toISOString()
					});
				}
			}

			function resetConversation() {
				session = createSession();
				messagesEl.innerHTML = "";
				if (introText) {
					addMessage("assistant", introText);
				}
				input.value = "";
				setStatus("");
				toggleForm(!CFG.apiUrl);
			}

			function handleEndChat() {
				if (!session.messages.length) {
					setStatus("No messages to send yet.", true);
					return;
				}

				session.endedAt = new Date().toISOString();

				try {
					forwardTranscript(session);
				} catch (err) {
					console.error("[ACCW transcript]", err);
				}

				setStatus("Transcript sent. Starting a new chat.");
				resetConversation();
			}

			// Updated to match Cloud Run ChatRequest shape
			function sendToApi(latestMessage) {
				// Build messages array from session history
				var messages = session.messages.map(function (msg) {
					var role =
						msg.role === "assistant"
							? "assistant"
							: msg.role === "system"
							? "system"
							: "user";
					return {
						role: role,
						content: msg.text
					};
				});

				var payload = {
					sessionId: session.id,
					clientId: CFG.clientId || "",
					messages: messages,
					latestMessage: {
						role: "user",
						content: latestMessage
					},
					meta: {
						page: window.location.href,
						title: document.title
					}
				};

				var headers = {
					"Content-Type": "application/json"
				};

				// apiAuthToken is optional, Cloud Run does not require it but we can keep it if set
				if (CFG.apiAuthToken) {
					headers.Authorization = "Bearer " + CFG.apiAuthToken;
				}

				return fetch(CFG.apiUrl, {
					method: "POST",
					headers: headers,
					body: JSON.stringify(payload)
				}).then(function (response) {
					if (!response.ok) {
						throw new Error("Chatbot API error (" + response.status + ")");
					}

					return response.text().then(function (text) {
						if (!text) {
							return {};
						}

						try {
							return JSON.parse(text);
						} catch (err) {
							return { reply: text };
						}
					});
				});
			}

			function extractReply(data) {
				if (!data) {
					return "";
				}
				if (typeof data === "string") {
					return data;
				}
				if (data.reply) {
					return data.reply;
				}
				if (data.message) {
					return data.message;
				}
				if (data.answer) {
					return data.answer;
				}
				if (Array.isArray(data.messages) && data.messages.length) {
					var last = data.messages[data.messages.length - 1];
					if (last) {
						return last.text || last.content || "";
					}
				}
				if (Array.isArray(data.choices) && data.choices.length) {
					var choice = data.choices[0];
					if (choice) {
						if (typeof choice.text === "string") {
							return choice.text;
						}
						if (choice.message) {
							if (typeof choice.message === "string") {
								return choice.message;
							}
							if (choice.message.content) {
								return choice.message.content;
							}
						}
					}
				}
				return "";
			}

			resetConversation();

			if (!CFG.apiUrl) {
				setStatus("Chatbot API URL missing. Set ACCW_API_URL.", true);
			}

			form.addEventListener("submit", function (event) {
				event.preventDefault();

				if (!CFG.apiUrl) {
					setStatus("Chatbot is offline.", true);
					return;
				}

				var text = input.value.trim();
				if (!text || isSending) {
					return;
				}

				isSending = true;
				setStatus("Sending...");
				addMessage("user", text);
				input.value = "";
				toggleForm(true);

				sendToApi(text)
					.then(function (response) {
						var reply = extractReply(response);
						if (!reply) {
							throw new Error("Empty reply from chatbot");
						}
						addMessage("assistant", reply);
						setStatus("");
					})
					.catch(function (err) {
						console.error("[ACCW]", err);
						addMessage(
							"system",
							"Something went wrong. Please try again shortly."
						);
						setStatus(
							err && err.message ? err.message : "Unable to reach chatbot",
							true
						);
					})
					.finally(function () {
						isSending = false;
						toggleForm(false);
						input.focus();
					});
			});

			if (endBtn) {
				endBtn.addEventListener("click", handleEndChat);
			}

			input.addEventListener("keydown", function (event) {
				if (event.key === "Enter" && !event.shiftKey) {
					event.preventDefault();
					if (typeof form.requestSubmit === "function") {
						form.requestSubmit();
					} else {
						form.dispatchEvent(
							new Event("submit", { cancelable: true, bubbles: true })
						);
					}
				}
			});
		});
	}

	function init() {
		initToggle();
		initChatInterfaces();
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", init);
	} else {
		init();
	}
})();
