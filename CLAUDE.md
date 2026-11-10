# StrideAI — Claude Code Instructions

## 1. Source of truth

Before making implementation changes, read `docs/BLUEPRINT.md`.

The blueprint is the source of truth for product scope, architecture, technical decisions, security requirements, risks and acceptance criteria.

If a requirement is ambiguous, check the blueprint first. If it remains unresolved, explain the ambiguity and ask before making a significant architectural decision. Do not silently replace an approved decision.

## 2. Fixed technology stack

- PHP and Laravel 13.
- MySQL as the database.
- Blade templates and Tailwind CSS.
- Vanilla JavaScript ES modules bundled with Vite for interactive chat and voice features.
- Gemini API for text chat.
- Gemini Live API using `gemini-3.8-live` for real-time voice, subject to verified API access and current documentation.
- Git for version control.
- Laravel Forge for deployment, with the domain registered through GoDaddy.

Do not replace Laravel, MySQL, Blade or the specified Gemini models without explicit approval.

## 3. Engineering standards

- Follow Laravel conventions and keep code readable, maintainable and testable.
- Use migrations, Eloquent models, Form Requests, authorization policies where appropriate, and focused controllers.
- Keep business rules in reusable application actions or services rather than duplicating them across controllers, the storefront, text chat and voice chat.
- Keep Gemini-specific API communication isolated in the service classes defined by the blueprint.
- Avoid unnecessary dependencies, premature abstractions, oversized classes and duplicated logic.
- Validate untrusted input on the server.
- Use database transactions and appropriate locking for stock and checkout operations.
- Never trust AI-generated tool arguments or browser-submitted voice tool calls.
- Never let the model independently determine authoritative prices, stock, discounts or order placement.
- Follow the blueprint's access controls, rate limits, privacy rules, retention policy and cost safeguards.

## 4. Gemini integration rules

- Verify model names, API endpoints, request schemas, authentication, function calling and session behavior against current official Google documentation.
- Do not invent SDK methods, request fields or API capabilities.
- Keep permanent Gemini API keys on the server.
- Use short-lived, appropriately restricted credentials for browser voice sessions as specified in the blueprint.
- Keep text and voice channels connected to the same trusted application tools and business logic.
- Treat voice transcripts received from the browser as client-reported data.
- Handle API errors, rate limits, timeouts and unavailable services gracefully.

## 5. Database and testing rules

- MySQL is the target database; do not assume SQLite is an equivalent substitute for integration testing.
- Test database-specific behavior, including MySQL FULLTEXT search, against MySQL.
- Use Laravel's testing tools and HTTP fakes for external API integration tests.
- Test authorization, validation, conversation ownership, tool-call permissions, cart operations, stock handling and failure cases.
- Run relevant tests after each implementation milestone.
- Never claim tests passed unless they were actually executed and the results inspected.

## 6. Working process

- Work on one approved milestone at a time.
- Before each milestone, explain the intended changes and acceptance criteria.
- Inspect the existing repository before creating or modifying files.
- Keep changes focused and reviewable.
- Do not overwrite unrelated files or discard existing user changes.
- Do not continue to the next milestone until the current milestone is verified or its blockers are clearly reported.
- Ask before adding major dependencies, changing the architecture or expanding the approved scope.
- Update the README and relevant documentation when implementation decisions change.

## 7. Secrets and Git

- Keep credentials in environment variables and local secrets.
- Never commit `.env`, API keys, passwords, tokens or real customer data.
- Maintain `.env.example` with placeholders only.
- Review `git diff` and `git status` before committing.
- Never perform destructive Git operations without explicit approval.

## 8. Definition of done

A milestone is complete only when:

1. Its acceptance criteria are met.
2. Relevant automated tests have been run and inspected.
3. The application boots and affected flows work.
4. Security, validation and error handling have been considered.
5. No unrelated changes or secrets have been introduced.
6. Remaining limitations and unverified assumptions are documented.

Prioritize correctness, security and maintainability without overengineering the prototype.