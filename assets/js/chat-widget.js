(function () {
	// Strings/config passed from PHP.
	var STR = window.ACCW_STRINGS || {};
	var CFG = window.ACCW_CONFIG || {};

	var MAX_HISTORY = 20;

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
		var chatContainer = byId("accwContainer");
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

		function handleToggle(e) {
			if (e && e.__accwHandled) return;
			if (e) {
				e.preventDefault();
				e.stopImmediatePropagation();
				e.__accwHandled = true;
			}
			if (chatWindow.classList.contains("open")) {
				closeChat();
			} else {
				openChat();
			}
			if (chatHelper) {
				chatHelper.style.display = "none";
			}
		}

		// Attach on the button (capture to avoid other listeners swallowing it).
		chatToggle.addEventListener("click", handleToggle, { capture: true });

		// Fallback delegation in case another script interferes.
		if (chatContainer) {
			chatContainer.addEventListener(
				"click",
				function (e) {
					if (e && e.__accwHandled) return;
					if (e && e.target && e.target.closest && e.target.closest("#chatToggle")) {
						handleToggle(e);
					}
				},
				{ capture: true }
			);
		}

		// Auto-hide helper bubble after a short delay to avoid overlay confusion.
		if (chatHelper) {
			setTimeout(function () {
				chatHelper.style.display = "none";
			}, 4000);
		}

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
			var leadBubble = container.querySelector("[data-accw-lead-bubble]");
			var leadForm = container.querySelector("[data-accw-lead-form]");
			var leadName = container.querySelector("[data-accw-lead-name]");
			var leadEmail = container.querySelector("[data-accw-lead-email]");
			var leadPhone = container.querySelector("[data-accw-lead-phone]");
			var leadStatus = container.querySelector("[data-accw-lead-status]");
			var leadCall = container.querySelector("[data-accw-lead-call]");

			if (!messagesEl || !form || !input) {
				return;
			}

			var introText = container.getAttribute("data-accw-intro");
			var session = null;
			var isSending = false;
			var leadShown = false;
			var leadSubmitted = false;
			var hasUserMessage = false;

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

				if (normalizedRole === "user") {
					hasUserMessage = true;
				}

				// Show lead bubble only after we have a user message and an assistant reply.
				if (!leadShown && normalizedRole === "assistant" && hasUserMessage) {
					showLeadBubble();
				}
			}

			function showLeadBubble() {
				leadShown = true;
				if (leadBubble) {
					messagesEl.appendChild(leadBubble);
					leadBubble.classList.add("is-visible");
					messagesEl.scrollTop = messagesEl.scrollHeight;
				}
			}

			// Create an empty bot bubble for streaming, returns the DOM element.
			function createStreamingBubble() {
				var bubble = document.createElement("div");
				bubble.className = "accw-message accw-message-bot is-streaming";
				messagesEl.appendChild(bubble);
				messagesEl.scrollTop = messagesEl.scrollHeight;
				return bubble;
			}

			function resetConversation() {
				session = createSession();
				messagesEl.innerHTML = "";
				if (leadBubble) {
					leadBubble.classList.remove("is-visible");
				}
				if (introText) {
					addMessage("assistant", introText);
				}
				leadShown = false;
				leadSubmitted = false;
				hasUserMessage = false;
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

				resetConversation();

				// Close the widget window
				var chatWindow = byId("chatWindow");
				var chatToggle = byId("chatToggle");
				if (chatWindow) chatWindow.classList.remove("open");
				if (chatToggle) {
					chatToggle.classList.remove("active");
					chatToggle.setAttribute("aria-expanded", "false");
				}
			}

			function buildPayload(latestMessage) {
				// Cap history to last MAX_HISTORY messages for API payload
				var allMessages = session.messages;
				var capped = allMessages.length > MAX_HISTORY
					? allMessages.slice(allMessages.length - MAX_HISTORY)
					: allMessages;

				var messages = capped.map(function (msg) {
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

				return {
					sessionId: session.id,
					clientId: CFG.clientId || "",
					messages: messages,
					latestMessage: {
						role: "user",
						content: latestMessage
					},
					meta: {
						page: window.location.href,
						title: document.title,
						businessName: CFG.businessName || "",
						businessLocation: CFG.businessLocation || "",
						businessPhone: CFG.businessPhone || "",
						businessEmail: CFG.businessEmail || "",
						context: CFG.businessContext || ""
					}
				};
			}

			function buildHeaders() {
				var headers = {
					"Content-Type": "application/json",
					"Accept": "text/event-stream"
				};
				if (CFG.apiAuthToken) {
					headers.Authorization = "Bearer " + CFG.apiAuthToken;
				}
				return headers;
			}

			// SSE streaming fetch — calls onChunk(text) for each token, onDone(fullText) when complete.
			function sendToApiStream(text, onChunk, onDone, onError) {
				var payload = buildPayload(text);
				var headers = buildHeaders();

				fetch(CFG.apiUrl, {
					method: "POST",
					headers: headers,
					body: JSON.stringify(payload)
				}).then(function (response) {
					if (!response.ok) {
						throw new Error("Chatbot API error (" + response.status + ")");
					}

					var contentType = response.headers.get("content-type") || "";

					// Fallback: server returned JSON instead of SSE
					if (contentType.indexOf("text/event-stream") === -1) {
						return response.text().then(function (raw) {
							var reply = "";
							try {
								var data = JSON.parse(raw);
								reply = extractReply(data);
							} catch (e) {
								reply = raw;
							}
							if (!reply) throw new Error("Empty reply from chatbot");
							onChunk(reply);
							onDone(reply);
						});
					}

					// SSE streaming path
					var reader = response.body.getReader();
					var decoder = new TextDecoder();
					var fullText = "";
					var sseBuffer = "";

					function pump() {
						return reader.read().then(function (result) {
							if (result.done) {
								onDone(fullText);
								return;
							}
							sseBuffer += decoder.decode(result.value, { stream: true });
							var lines = sseBuffer.split("\n");
							sseBuffer = lines.pop() || "";

							for (var i = 0; i < lines.length; i++) {
								var line = lines[i].trim();
								if (!line || !line.startsWith("data:")) continue;
								var payload = line.slice(5).trim();
								if (payload === "[DONE]") {
									onDone(fullText);
									return;
								}
								try {
									var parsed = JSON.parse(payload);
									if (parsed.error) {
										onError(new Error(parsed.error));
										return;
									}
									if (parsed.content) {
										fullText += parsed.content;
										onChunk(parsed.content);
									}
								} catch (e) {
									// skip malformed
								}
							}
							return pump();
						});
					}

					return pump();
				}).catch(function (err) {
					onError(err);
				});
			}

			// Non-streaming fallback
			function sendToApi(latestMessage) {
				var payload = buildPayload(latestMessage);
				var headers = {
					"Content-Type": "application/json"
				};
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

				if (leadShown && !leadSubmitted) {
					setStatus("Please share your contact details to continue.", true);
					if (leadBubble) {
						leadBubble.classList.add("is-visible");
						messagesEl.scrollTop = messagesEl.scrollHeight;
					}
					return;
				}

				var text = input.value.trim();
				if (!text || isSending) {
					return;
				}

				isSending = true;
				addMessage("user", text);
				input.value = "";
				toggleForm(true);

				// Create streaming bubble upfront
				var streamBubble = createStreamingBubble();
				setStatus("");

				sendToApiStream(
					text,
					// onChunk — append token to bubble
					function (chunk) {
						streamBubble.textContent += chunk;
						messagesEl.scrollTop = messagesEl.scrollHeight;
					},
					// onDone — finalize
					function (fullText) {
						streamBubble.classList.remove("is-streaming");
						if (!fullText) {
							streamBubble.remove();
							addMessage("system", "Something went wrong. Please try again shortly.");
							setStatus("Empty reply from chatbot", true);
						} else {
							// Store in session
							session.messages.push({
								role: "assistant",
								text: fullText,
								at: new Date().toISOString()
							});
							// Trigger lead bubble after first assistant reply
							if (!leadShown && hasUserMessage) {
								showLeadBubble();
							}
						}
						setStatus("");
						isSending = false;
						toggleForm(false);
						input.focus();
					},
					// onError — cleanup
					function (err) {
						streamBubble.classList.remove("is-streaming");
						if (!streamBubble.textContent) {
							streamBubble.remove();
						}
						console.error("[ACCW]", err);
						addMessage("system", "Something went wrong. Please try again shortly.");
						setStatus(
							err && err.message ? err.message : "Unable to reach chatbot",
							true
						);
						isSending = false;
						toggleForm(false);
						input.focus();
					}
				);
			});

			if (endBtn) {
				endBtn.addEventListener("click", handleEndChat);
			}

			function setLeadStatus(message, isError) {
				if (!leadStatus) return;
				leadStatus.textContent = message || "";
				leadStatus.classList.toggle("is-error", Boolean(isError && message));
			}

			function buildTranscriptText() {
				var lines = [];
				(session.messages || []).forEach(function (m) {
					var speaker = m.role === "assistant" ? "Assistant" : m.role === "system" ? "System" : "User";
					lines.push("[" + (m.at || "") + "] " + speaker + ": " + (m.text || ""));
				});
				return lines.join("\n");
			}

			function sendLeadPayload(payload) {
				return fetch(CFG.forwardTranscriptUrl, {
					method: "POST",
					headers: {
						"Content-Type": "application/json"
					},
					body: JSON.stringify(payload)
				}).then(function (response) {
					if (!response.ok) {
						throw new Error("Lead API error (" + response.status + ")");
					}
					return response.json().catch(function () {
						return {};
					});
				});
			}

			if (leadForm) {
				leadForm.addEventListener("submit", function (event) {
					event.preventDefault();
					if (leadSubmitted) {
						setLeadStatus("Details already sent.", false);
						return;
					}
					if (!CFG.forwardTranscriptUrl) {
						setLeadStatus("Lead capture unavailable.", true);
						return;
					}

					var nameVal = (leadName && leadName.value || "").trim();
					var emailVal = (leadEmail && leadEmail.value || "").trim();
					var phoneVal = (leadPhone && leadPhone.value || "").trim();

					if (!nameVal || !emailVal || !phoneVal) {
						setLeadStatus("Please fill name, email, and phone.", true);
						return;
					}

					setLeadStatus("Sending...", false);

					var transcriptText = buildTranscriptText();
					var payload = {
						clientId: CFG.clientId || "",
						token: CFG.forwardToken || "",
						sessionId: session ? session.id : "",
						name: nameVal,
						email: emailVal,
						phone: phoneVal,
						transcript: transcriptText,
						meta: {
							page: window.location.href,
							title: document.title
						}
					};

					sendLeadPayload(payload)
						.then(function () {
							leadSubmitted = true;
							setLeadStatus("Thanks! We've got your details.", false);
							if (leadBubble) {
								leadBubble.classList.remove("is-visible");
							}
							setStatus("", false);
						})
						.catch(function (err) {
							console.error("[ACCW lead]", err);
							setLeadStatus("Could not send details. Please try again.", true);
						});
				});
			}

			// Render call link when phone is present.
			if (leadCall) {
				var phone = (CFG.businessPhone || "").replace(/\D/g, "");
				if (phone) {
					var link = leadCall.querySelector("a");
					if (link) {
						link.href = "tel:" + phone;
						link.textContent = "or just give us a call: " + (CFG.businessPhone || "");
					}
					leadCall.style.display = "block";
				} else {
					leadCall.style.display = "none";
				}
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
