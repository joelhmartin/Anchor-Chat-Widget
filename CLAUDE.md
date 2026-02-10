# Anchor Corps Chat Widget

WordPress plugin that adds a floating chat widget to every page. Users interact with an AI chatbot, submit lead info, and transcripts are forwarded to CallTrackingMetrics.

## Project Structure

```
anchor-corps-chat-widget.php   # All PHP — settings page, widget rendering, RAG admin, enqueue
assets/css/chat-widget.css     # Frontend widget styles
assets/js/chat-widget.js       # Frontend widget logic (IIFE)
assets/js/rag-admin.js         # WP Admin Knowledge Base AJAX UI
cloud-run-forwarder/           # Legacy stub — simple transcript forwarder (NOT the active backend)
composer.json                  # PHP deps: phpdotenv, plugin-update-checker
```

## Key Architecture

- **Single PHP file** — all plugin code lives in `anchor-corps-chat-widget.php` (~1400 lines)
- **Not shortcode-based** — widget renders via `wp_footer` hook, controlled by Display Visibility setting
- **Auto-updates** from GitHub releases via `yahnis-elsts/plugin-update-checker`
- **Cache busting** uses `filemtime()`, not the static version constant

### WordPress Hooks (in load order)
1. `wp_enqueue_scripts` priority 5 → `accw_enqueue_assets()` (CSS/JS)
2. `wp_enqueue_scripts` priority 6 → `accw_root_css_vars()` (inline CSS vars + custom CSS)
3. `wp_footer` priority 10 → `accw_render_widget()` (HTML output)

## Code Style

- **PHP**: Tabs for indentation (not spaces). WordPress coding standards.
- **JS**: IIFE pattern in chat-widget.js. No build step.
- **CSS**: Plain CSS, no preprocessor.

## Important Gotchas

- The Edit tool shows tabs as spaces — when editing the PHP file, match actual tab characters
- Both container and button have capture-phase click handlers; `handleToggle` uses `e.__accwHandled` + `stopImmediatePropagation()` to prevent double-toggle
- `.env` file may contain `GITHUB_ACCESS_TOKEN` — never commit it

## Cloud Run Backend (`ai-endpoint`)

The widget's active backend lives in a **separate repo** at `/Volumes/G-DRIVE SSD/DEVELOPER/ai-endpoint`. It is a TypeScript/Express service deployed on Google Cloud Run.

### ai-endpoint Structure
```
ai-endpoint/
├── src/
│   ├── index.ts          # Express server, route setup, startup (CTM + RAG init)
│   ├── chat.ts           # POST /chat — Vertex AI Gemini 2.0 Flash, RAG retrieval
│   ├── lead.ts           # POST /lead — create lead or update transcript
│   ├── ctm.ts            # CallTrackingMetrics API integration
│   ├── ctmClients.ts     # CTM sub-account registry, FormReactor setup
│   ├── sessionStore.ts   # In-memory session → trackback mapping
│   ├── rag.ts            # RAG endpoint handlers (corpus CRUD, file upload)
│   ├── ragApi.ts         # Vertex AI RAG API client
│   ├── ragCorpusStore.ts # In-memory clientId → corpus mapping
│   ├── ragTypes.ts       # RAG TypeScript interfaces
│   ├── types.ts          # Core request/response types
│   └── env.ts            # Environment variable defaults
├── dist/                 # Compiled JS output
├── Dockerfile            # Node 18-slim container
├── cloudbuild.yaml       # GCP Cloud Build pipeline
├── tsconfig.json
└── package.json
```

### How the Widget Connects to ai-endpoint

Default production URL: `https://ai-endpoint-kqikza7ska-ew.a.run.app`

**Endpoints the widget calls:**

| Widget action | Endpoint | Purpose |
|---|---|---|
| User sends message | `POST /chat` | Sends messages + business meta → returns AI reply |
| Lead form submitted | `POST /lead` | Creates CTM lead (name/email/phone) + transcript |
| Ongoing chat updates | `POST /lead` | Updates transcript on existing lead |
| Admin: KB status | `GET /rag/status` | Check if RAG corpus exists for client |
| Admin: Enable KB | `POST /rag/corpus` | Create Vertex AI RAG corpus |
| Admin: Disable KB | `DELETE /rag/corpus` | Delete corpus |
| Admin: Upload doc | `POST /rag/files` | Upload file → GCS → Vertex RAG import |
| Admin: List docs | `GET /rag/files` | List uploaded knowledge files |
| Admin: Delete doc | `DELETE /rag/files` | Remove file from corpus |

**Auth:** All mutating endpoints require `FORWARD_TOKEN` (passed as `forwardToken` in widget settings, matched against `FORWARD_TOKEN` env var on Cloud Run).

**PHP → Cloud Run bridge:** `accw_cloud_run_base_url()` derives the base URL from the configured `apiUrl` setting (strips `/chat` path). RAG admin AJAX handlers (`accw_ajax_rag_*`) proxy requests through WordPress to Cloud Run via `accw_rag_request()`.

### Data Flow
```
Browser (chat-widget.js)
  ├─ POST /chat ──────────→ ai-endpoint
  │                           ├─ RAG retrieval (if corpus exists, score > 0.3)
  │                           ├─ Build system prompts (HIPAA + business + RAG context)
  │                           └─ Vertex AI Gemini 2.0 Flash → { reply }
  │
  ├─ POST /lead (create) ─→ ai-endpoint
  │                           ├─ CTM FormReactor → create lead
  │                           ├─ Lookup call by trackbackId
  │                           └─ Set chat_transcription custom field
  │
  └─ POST /lead (update) ─→ ai-endpoint
                              └─ Update existing call's transcript

WP Admin (rag-admin.js)
  └─ AJAX → PHP proxy ────→ ai-endpoint /rag/* endpoints
                              └─ Vertex AI RAG API + GCS bucket
```

### ai-endpoint Key Config (env vars)
- `FORWARD_TOKEN` — **required**, must match widget's `forwardToken`
- `GCP_PROJECT_ID` — default `anchor-hub-480305`
- `GCP_REGION` — default `europe-west1`
- `MODEL_NAME` — default `google/gemini-2.0-flash-001`
- `CTM_ACCESS_KEY` / `CTM_SECRET_KEY` — CallTrackingMetrics API credentials
- `RAG_GCS_BUCKET` — GCS bucket for uploaded knowledge files

### Note on `cloud-run-forwarder/`
The `cloud-run-forwarder/` directory in this repo is a **legacy stub** (simple Express/Node transcript forwarder). It is **not the active backend** — all production traffic goes to `ai-endpoint`.

## Git / Releases

- Remote: `origin` → `https://github.com/joelhmartin/Anchor-Chat-Widget.git`
- Branch: `main`
- Version is in the plugin header comment (`Version: X.Y.Z`)
