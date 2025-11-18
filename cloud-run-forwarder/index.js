// index.js
const express = require('express');
const fetch = require('node-fetch'); // If you prefer, use global fetch in newer Node runtimes

const app = express();
app.use(express.json());

/**
 * Health check.
 */
app.get('/', (req, res) => {
    res.send('ACCW transcript forwarder is running');
});

/**
 * Helper: build a simple text transcript from messages.
 * This mimics the “compiled” transcript your old script likely produced.
 *
 * Example output:
 *
 *  [2025-01-01T12:00:00Z] Patient: I have jaw pain
 *  [2025-01-01T12:00:02Z] Bot: I am sorry to hear that. Are you clenching?
 */
function buildTranscriptText(payload) {
    if (!payload || !Array.isArray(payload.messages)) {
        return '';
    }

    var lines = [];

    lines.push('Session ID: ' + (payload.sessionId || 'unknown'));
    lines.push('Page: ' + (payload.meta && payload.meta.page ? payload.meta.page : ''));
    lines.push('Title: ' + (payload.meta && payload.meta.title ? payload.meta.title : ''));
    lines.push('Started at: ' + (payload.startedAt || ''));
    lines.push('Ended at: ' + (payload.endedAt || ''));
    lines.push('');
    lines.push('Transcript:');
    lines.push('--------------------------------');

    payload.messages.forEach(function (m) {
        var at = m.at || '';
        var speaker = m.role === 'assistant' ? 'Bot' : 'User';
        var text = m.text || '';
        lines.push('[' + at + '] ' + speaker + ': ' + text);
    });

    return lines.join('\n');
}

/**
 * POST /forward-transcript
 *
 * Body:
 * {
 *   sessionId: "accw_...",
 *   startedAt: "iso string",
 *   endedAt: "iso string",
 *   meta: { page, title },
 *   messages: [ { role, text, at }, ... ]
 * }
 *
 * This endpoint:
 *   - compiles the transcript text
 *   - sends it to CallTrackingMetrics via their API or webhook
 */
app.post('/forward-transcript', async (req, res) => {
    const payload = req.body || {};

    if (!payload || !Array.isArray(payload.messages)) {
        return res.status(400).json({ error: 'Invalid payload' });
    }

    // Simple shared token check, to avoid random posts
    const expectedToken = process.env.ACCW_FORWARD_TOKEN;
    const receivedToken = req.get('x-accw-token');

    if (expectedToken && expectedToken !== receivedToken) {
        return res.status(401).json({ error: 'Unauthorized' });
    }

    const transcriptText = buildTranscriptText(payload);

    try {
        // This is where you mirror your existing CTM integration.
        // Replace this stub with the actual CTM call you had before.
        //
        // Example skeleton using a webhook:
        //
        // await fetch(process.env.CTM_WEBHOOK_URL, {
        //     method: 'POST',
        //     headers: {
        //         'Content-Type': 'application/json',
        //         'Authorization': 'Token ' + process.env.CTM_API_KEY
        //     },
        //     body: JSON.stringify({
        //         sessionId: payload.sessionId,
        //         page: payload.meta && payload.meta.page,
        //         title: payload.meta && payload.meta.title,
        //         transcript: transcriptText
        //     })
        // });

        console.log('Transcript received for session', payload.sessionId || 'unknown');

        // Do not log transcriptText to avoid PHI in logs
        return res.status(204).end();
    } catch (err) {
        console.error('Error forwarding transcript to CTM', err && err.message ? err.message : err);
        return res.status(502).json({ error: 'Failed to forward transcript' });
    }
});

const port = process.env.PORT || 8080;
app.listen(port, () => {
    console.log('ACCW transcript forwarder listening on port', port);
});
