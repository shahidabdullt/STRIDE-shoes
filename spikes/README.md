# Phase 0 spikes

Throwaway experiments for `docs/BLUEPRINT.md` §8 Phase 0. **Not part of the application.** Results go in `docs/phase-0-findings.md`.

The scripts read `GEMINI_API_KEY` (and optionally `GEMINI_TEXT_MODEL` / `GEMINI_LIVE_MODEL`) from the project `.env`. They never print the key or token values. Fixtures in `fixtures/` pass through `spike_redact()`, which masks the API key, `auth_tokens/…` values and thought signatures. Check them before committing anyway.

| Spike | Command | What to record |
|---|---|---|
| S1 token body | `php spikes/s1_token.php` (all variants) or `php spikes/s1_token.php b hold_to_talk` | HTTP status per variant: `a` liveConnectConstraints, `b` bidiGenerateContentSetup, `bw` wrapped in `authToken`, `bm` with fieldMask, `none` unlocked |
| S1 lock test, S2 audio, S3 tools, S5 resumption | `php -S localhost:8081 -t spikes/live`, then open http://localhost:8081 in Chrome or Edge | See the checklist below |
| S4 text API | `php spikes/s4_text.php [model]` | Rounds, step types, usage fields, structured JSON validity, error shapes |
| S6 MySQL FULLTEXT | `php spikes/s6_fulltext.php 127.0.0.1 3307 stride_testing root <password>` | Server version, ft variables, which queries match |

The browser page must be served from `localhost`: microphone access needs a secure context, and `token.php`/`tool.php` reject non-local clients.

## Browser checklist (record results from "Copy log")

1. **Lock test (S1).** Variant `b` with client setup **override** → Connect → Send text. Locked: the reply starts "Stride here" and never "Arrr". Repeat with `none` (control: the pirate instruction should win) and with `bm`.
2. **Single use (S1).** After connecting, click **2nd new session, same token** and note the close code and reason.
3. **New-session expiry (S1).** Set newSession TTL to 5 s, Connect, wait about 10 s, then click 2nd session. (Repeat via Disconnect and Connect with a stale token if needed.)
4. **Hands-free (S2).** With headphones, ask 5 questions. Note the "first model audio … ms" median. Interrupt mid-answer and note the "interrupted … ms after local speech onset" figure. Repeat on laptop speakers and note any self-interruption.
5. **Hold-to-talk (S2).** Mode hold-to-talk. Press, speak, release. Check whether the first word is clipped with the 300 ms pre-buffer. Press while the model speaks.
6. **Tools (S3).** Ask "Is the Trailblazer available in size 9?" Note the tool round-trip time and whether the spoken answer uses the stub data (size 9 is out of stock; 8, 9.5 and 10 are in stock). Set tool delay to 5000, ask, and interrupt during the delay. Note any `toolCallCancellation`.
7. **Resumption (S5).** Mention a fact ("my size is 10"), click **Force reconnect**, ask "what size did I say?". Try with variant `b` and `bm`: with an empty mask the client's `sessionResumption.handle` may be ignored.
8. **Native rate.** Untick "downsample" and confirm the model still understands you (`audio/pcm;rate=48000`).
