# DharmaVera - Hackathon Edition

Live demo: https://southrule.com/?demo=1

Note: dharmavera.com is untouched and is NOT the demo. The hackathon demo runs only on southrule.com.

## Track and theme

Indie developer, Build for Your World.

## Try the demo in 30 seconds

1. Open https://southrule.com/?demo=1
2. Tap 4 numbers (the first must be 1-9 for Mahabote).
3. The reading draws: overall score, seven oracle scores, remedy (when a score is low), the saved wave chart, the lifeline graph, and one AI guidance sentence.
4. ?demo=1 skips the 90-minute cooldown, so judges can draw again at once.

## Old vs New

No old work is claimed as new. Everything new is commented in code as hackathon work.

| Old, before October 26, 2026 | New, built October 26-27, 2026, during the hackathon |
| --- | --- |
| The 7-oracle engine and formulas (Mahabote, Kau Cim, Tehillim, Bible, Quran, Ram Shalaka, Gypsy Cards) | Overall remedy at score 5 or below |
| The simple average 1-10 score | Per-oracle Remedy buttons below 5 with single-tradition popups only (Mahabote-Burmese, Kau Cim-Chinese, Tehillim-Jewish, Bible-Christian, Quran-Islamic, Ram Shalaka-Hindu, Gypsy-neutral reflection) |
| The 90-minute cooldown | Overall remedy keeps the six-tradition choice |
| Ranks Silver / Gold / Platinum | Saved wave chart 1 month / 1 year / 2 years with labeled sample data and per-browser guest saving |
| Harmony | Facebook likes and monetization income per day overlays as manual entries only, never merged into scores |
| Admin dashboard | ?demo=1 cooldown bypass |
| PWA / Android app | One thick Chinese Zodiac sine line only (center = animal overall score, period 12 years, swing 1.5, clamped 1-10; Dragon example center 8.4, range 6.9-9.9, peaks 11/23/35/47, troughs 5/17/29/41, no trait table) |
| Privacy / Terms / About pages | Platinum Personality box hidden for Silver and guests |
|  | Shar-Mee and Janaka Calculation rows removed from the DO / DON'T guide display only |
|  | Meta Model API guidance sentence by Muse Spark 1.1 (one sentence, 30 words or less, scores only sent to Meta, no question text sent) |
|  | Privacy / Terms hackathon disclosure note (disclosure update only, not a demo feature) |

## Explicitly NOT claimed

Hafez, Llama RAG, vector database, automatic Facebook / Instagram ingestion, WhatsApp agent, medical advice, guaranteed fortune reversal, monetized remedies, personality ranking.

## Architecture

PHP + JavaScript + MariaDB on Virtualmin / Apache. Oracle formulas run server-side outside the web root. Guests receive scores only. The question memo is optional and held privately. The Meta API key lives outside the web root and is never in the repo.
