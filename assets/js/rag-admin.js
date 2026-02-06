(function ($) {
	"use strict";

	var $status     = $("#accw-rag-status");
	var $controls   = $("#accw-rag-controls");
	var $enableBtn  = $("#accw-rag-enable");
	var $disableBtn = $("#accw-rag-disable");
	var $uploadSec  = $("#accw-rag-upload-section");
	var $filesSec   = $("#accw-rag-files-section");
	var $uploadForm = $("#accw-rag-upload-form");
	var $uploadBtn  = $("#accw-rag-upload-btn");
	var $uploadSpin = $("#accw-rag-upload-spinner");
	var $uploadMsg  = $("#accw-rag-upload-status");
	var $filesBody  = $("#accw-rag-files-body");

	var isEnabled = false;

	function ajax(action, data) {
		var params = $.extend({ action: action, nonce: accwRag.nonce }, data || {});
		return $.post(accwRag.ajaxUrl, params);
	}

	function formatBytes(bytes) {
		if (!bytes) return "—";
		var b = parseInt(bytes, 10);
		if (isNaN(b) || b === 0) return "—";
		if (b < 1024) return b + " B";
		if (b < 1024 * 1024) return (b / 1024).toFixed(1) + " KB";
		return (b / (1024 * 1024)).toFixed(1) + " MB";
	}

	function formatDate(iso) {
		if (!iso) return "—";
		var d = new Date(iso);
		if (isNaN(d.getTime())) return "—";
		return d.toLocaleDateString() + " " + d.toLocaleTimeString();
	}

	function showEnabled(corpusId) {
		isEnabled = true;
		$status.html(
			'<span style="color:#00a32a;font-weight:600;">&#10003; Knowledge Base active</span>' +
			(corpusId ? ' <code style="font-size:11px;">' + corpusId + "</code>" : "")
		);
		$enableBtn.hide();
		$disableBtn.show();
		$controls.show();
		$uploadSec.show();
		$filesSec.show();
		loadFiles();
	}

	function showDisabled() {
		isEnabled = false;
		$status.html('<span style="color:#72777c;">No knowledge base configured for this client.</span>');
		$enableBtn.show();
		$disableBtn.hide();
		$controls.show();
		$uploadSec.hide();
		$filesSec.hide();
	}

	function checkStatus() {
		ajax("accw_rag_status").done(function (res) {
			if (res.success && res.data && res.data.enabled) {
				showEnabled(res.data.corpusId);
			} else {
				showDisabled();
			}
		}).fail(function () {
			$status.html('<span style="color:#d63638;">Could not reach the Cloud Run backend.</span>');
			$controls.show();
			$enableBtn.show();
		});
	}

	function loadFiles() {
		$filesBody.html('<tr><td colspan="4">Loading…</td></tr>');
		ajax("accw_rag_list_files").done(function (res) {
			if (!res.success) {
				$filesBody.html('<tr><td colspan="4">Error: ' + (res.data || "unknown") + "</td></tr>");
				return;
			}
			var files = (res.data && res.data.files) || [];
			if (!files.length) {
				$filesBody.html('<tr><td colspan="4">No documents uploaded yet.</td></tr>');
				return;
			}
			var rows = "";
			files.forEach(function (f) {
				rows += "<tr>";
				rows += "<td>" + $("<span>").text(f.displayName || f.ragFileName).html() + "</td>";
				rows += "<td>" + formatBytes(f.sizeBytes) + "</td>";
				rows += "<td>" + formatDate(f.createTime) + "</td>";
				rows += '<td><button type="button" class="button button-link-delete accw-rag-delete-file" data-name="' +
					$("<span>").text(f.ragFileName).html() + '">Delete</button></td>';
				rows += "</tr>";
			});
			$filesBody.html(rows);
		}).fail(function () {
			$filesBody.html('<tr><td colspan="4">Failed to load files.</td></tr>');
		});
	}

	// Enable knowledge base.
	$enableBtn.on("click", function () {
		if (!confirm("Create a knowledge base for this client? This may take up to 2 minutes.")) {
			return;
		}
		$enableBtn.prop("disabled", true).text("Creating…");
		ajax("accw_rag_create_corpus").done(function (res) {
			if (res.success) {
				showEnabled(res.data && res.data.corpusId);
			} else {
				alert("Error: " + (res.data || "unknown"));
			}
		}).fail(function () {
			alert("Request failed. Check that the Cloud Run backend is running.");
		}).always(function () {
			$enableBtn.prop("disabled", false).text("Enable Knowledge Base");
		});
	});

	// Delete knowledge base.
	$disableBtn.on("click", function () {
		if (!confirm("Delete the knowledge base and ALL uploaded documents? This cannot be undone.")) {
			return;
		}
		$disableBtn.prop("disabled", true).text("Deleting…");
		ajax("accw_rag_delete_corpus").done(function (res) {
			if (res.success) {
				showDisabled();
			} else {
				alert("Error: " + (res.data || "unknown"));
			}
		}).fail(function () {
			alert("Request failed.");
		}).always(function () {
			$disableBtn.prop("disabled", false).text("Delete Knowledge Base");
		});
	});

	// Upload file.
	$uploadForm.on("submit", function (e) {
		e.preventDefault();
		var fileInput = document.getElementById("accw-rag-file");
		if (!fileInput || !fileInput.files.length) {
			$uploadMsg.html('<span style="color:#d63638;">Please select a file.</span>');
			return;
		}

		var formData = new FormData();
		formData.append("action", "accw_rag_upload_file");
		formData.append("nonce", accwRag.nonce);
		formData.append("file", fileInput.files[0]);

		$uploadBtn.prop("disabled", true);
		$uploadSpin.addClass("is-active");
		$uploadMsg.html("Uploading and processing… this may take a minute.");

		$.ajax({
			url: accwRag.ajaxUrl,
			type: "POST",
			data: formData,
			processData: false,
			contentType: false,
			timeout: 120000,
		}).done(function (res) {
			if (res.success) {
				$uploadMsg.html('<span style="color:#00a32a;">Document imported successfully.</span>');
				fileInput.value = "";
				loadFiles();
			} else {
				$uploadMsg.html('<span style="color:#d63638;">Error: ' + (res.data || "unknown") + "</span>");
			}
		}).fail(function () {
			$uploadMsg.html('<span style="color:#d63638;">Upload failed. Check file size and type.</span>');
		}).always(function () {
			$uploadBtn.prop("disabled", false);
			$uploadSpin.removeClass("is-active");
		});
	});

	// Delete file (delegated).
	$filesBody.on("click", ".accw-rag-delete-file", function () {
		var $btn = $(this);
		var name = $btn.data("name");
		if (!confirm("Delete this document from the knowledge base?")) {
			return;
		}
		$btn.prop("disabled", true).text("Deleting…");
		ajax("accw_rag_delete_file", { ragFileName: name }).done(function (res) {
			if (res.success) {
				loadFiles();
			} else {
				alert("Error: " + (res.data || "unknown"));
				$btn.prop("disabled", false).text("Delete");
			}
		}).fail(function () {
			alert("Request failed.");
			$btn.prop("disabled", false).text("Delete");
		});
	});

	// Init.
	checkStatus();
})(jQuery);
