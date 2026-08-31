# Taste

- Communicates in Indonesian (Bahasa Indonesia) and expects the conversation to be conducted in Indonesian. Confidence: 0.9
- The business/store operates in WIB (UTC+7); any date/time handling in the app (POS sale dates, reports, daily filters) must be consistent with WIB, not UTC. Confidence: 0.7
- When a feature depends on an external API, verify the API directly (e.g. curl the endpoints) to pin down whether the root cause is the external service or the app code, rather than assuming the app is at fault. Confidence: 0.6
