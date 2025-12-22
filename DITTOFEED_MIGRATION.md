# Dittofeed Migration Plan

Migrate from EmailOctopus to Dittofeed for subscriber management.

---

# Part 1: End State

## Files to Modify

- `config.example.php` / `config.php`
- `fluentu-leadbox.php`

## Config Changes

| Remove | Add |
|--------|-----|
| `EO_API_URL` | `DITTOFEED_API_URL` |
| `EO_API_KEY` | `DITTOFEED_WRITE_KEY` |
| `EO_LIST_ID` | `DITTOFEED_APP_ENV` |

## API Differences

| | EmailOctopus | Dittofeed |
|---|---|---|
| **Endpoint** | `PUT /lists/{id}/contacts` | `POST /api/public/apps/identify` |
| **Auth** | `Bearer {api_key}` | `Basic {write_key}:` (base64) |
| **User ID** | `email_address` | `anon:{email}` (anonymous ID) |
| **Data Model** | Tags (flat strings) | Traits (key-value pairs) |
| **Language Tags** | `BLOG - Spanish_Learner` | `learningSpanish: true` |
| **Success** | `id` exists or status 409 | HTTP 2xx |

## Method Changes

- **`addSubscriber()`** — Replace EmailOctopus API call with Dittofeed `/api/public/apps/identify` endpoint
- **`generateTags()`** — Replace with `generateTraits()` using language/trait mappings
- **DocBlocks** — Update references from "Email Octopus" to "Dittofeed"

## Trait Mappings

### Language Learning Traits

| Category Contains | Trait |
|-------------------|-------|
| Spanish | `learningSpanish: true` |
| French | `learningFrench: true` |
| German | `learningGerman: true` |
| Italian | `learningItalian: true` |
| Japanese | `learningJapanese: true` |
| Korean | `learningKorean: true` |
| Chinese / Mandarin Chinese | `learningChinese: true` |
| Russian | `learningRussian: true` |
| Portuguese | `learningPortuguese: true` |
| English | `learningEnglish: true` |
| Arabic | `learningArabic: true` |

### Native Language Traits (English for X Speakers)

| Category | Traits |
|----------|--------|
| English for Spanish Speakers | `learningEnglish: true`, `nativeLanguage: Spanish` |
| English for Chinese Speakers | `learningEnglish: true`, `nativeLanguage: Chinese` |
| English for Japanese Speakers | `learningEnglish: true`, `nativeLanguage: Japanese` |
| English for Korean Speakers | `learningEnglish: true`, `nativeLanguage: Korean` |
| English for Italian Speakers | `learningEnglish: true`, `nativeLanguage: Italian` |
| English for Russian Speakers | `learningEnglish: true`, `nativeLanguage: Russian` |
| English for Portuguese Speakers | `learningEnglish: true`, `nativeLanguage: Portuguese` |

### Other Traits

| Category Contains | Trait |
|-------------------|-------|
| Educator / General | `fluentuAnnouncements: true` |

### Base Traits (always included)

- `email` — User's email address
- `source` — `"Blog - Leadbox"`
- `landingPage` — Blog post URL (`get_permalink($post_id)`)
- `environment` — `DITTOFEED_APP_ENV` value

## Anonymous ID Pattern

User ID format: `anon:{email}`

This allows the user to be aliased to their FluentU user ID later when they create an account, preserving their blog lead traits.

---

# Part 2: Implementation Plan

## Phase 1: Dual-Write

Write to both services simultaneously during transition period.

### Changes

- Add Dittofeed constants to config (keep EmailOctopus constants)
- Add `DUAL_WRITE_ENABLED = true` feature flag
- Extract current logic into `addSubscriberToEmailOctopus()`
- Create new `addSubscriberToDittofeed()` method
- Create new `generateTraits()` method
- Modify `addSubscriber()` to call both when flag is enabled
- Succeed if either service succeeds

### Checklist

- [ ] Add Dittofeed constants to config (`DITTOFEED_API_URL`, `DITTOFEED_WRITE_KEY`, `DITTOFEED_APP_ENV`)
- [ ] Add `DUAL_WRITE_ENABLED = true`
- [ ] Create `addSubscriberToEmailOctopus()` (extract existing logic)
- [ ] Create `addSubscriberToDittofeed()` with identify endpoint
- [ ] Create `generateTraits()` with language/trait mappings
- [ ] Modify `addSubscriber()` to call both
- [ ] Test and verify contacts appear in both services

---

## Phase 2: Dittofeed Only

Remove EmailOctopus integration after dual-write validation.

### Changes

- Remove EmailOctopus constants and `DUAL_WRITE_ENABLED` flag
- Remove `addSubscriberToEmailOctopus()` method
- Remove `generateTags()` method
- Inline Dittofeed logic into `addSubscriber()`
- Update DocBlocks

### Checklist

- [ ] Remove EmailOctopus constants and feature flag
- [ ] Remove `addSubscriberToEmailOctopus()` method
- [ ] Remove `generateTags()` method
- [ ] Simplify `addSubscriber()` to Dittofeed-only
- [ ] Update DocBlocks
- [ ] Final end-to-end test
