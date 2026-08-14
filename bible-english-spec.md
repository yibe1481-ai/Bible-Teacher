# BIBLE ENGLISH — Full Claude Code Master Specification

## 1. PROJECT OVERVIEW

Build a production-ready **WordPress plugin** whose primary user interface is a **Telegram Bot + Telegram Mini App**.

**Product name:** Bible English
**Tagline:** Learn English through the Word. One verse a day.

**Core concept:** A gamified English language learning app that teaches vocabulary, listening, speaking, reading, and writing through daily Bible verses. Powered by free AI models (Groq, OpenRouter, Gemini). No payments in MVP — engagement and retention only.

---

## 2. CORE LEARNING LOOP

Every day, for every user:

```
Morning push notification (7am user timezone)
        ↓
Daily verse delivered (KJV, level-appropriate)
        ↓
AI vocabulary breakdown (5 words, level-aware)
        ↓
Listening exercise (TTS audio playback)
        ↓
Fill-in-the-blank quiz (AI-generated, 3 questions)
        ↓
Speaking exercise (record → Groq Whisper → score)
        ↓
AI personalized feedback (1-2 sentences, tutor tone)
        ↓
XP awarded → streak updated → leaderboard refreshed
        ↓
Tomorrow's preview ("See you at 7am — John 3:17 is next 🔥")
```

This loop must complete in under 5 minutes for the user.

---

## 3. USER LEVELS

### 3.1 Classification

On first use, show a 3-question placement quiz (no test framing — just "let's find your starting point"):

**Question 1 — vocabulary (easy signal):**
"What does the word 'eternal' mean?"
A) Very old  B) Lasting forever  C) Very large  D) Far away

**Question 2 — fill-in-the-blank (medium signal):**
"For God so loved the world, that he ___ his only begotten Son."
A) sends  B) gave  C) make  D) bring

**Question 3 — comprehension (hard signal):**
"What does John 3:16 say is required to have everlasting life?"
A) Doing good works  B) Believing in Jesus  C) Going to church  D) Reading the Bible

**Scoring:**
- 0–1 correct → Beginner
- 2 correct → Intermediate
- 3 correct + time under 30 seconds → Advanced
- 3 correct + time over 30 seconds → Intermediate

Never show the score. Transition immediately: "Perfect, we've found your starting point. Welcome to your first lesson."

### 3.2 Level Definitions

**Beginner:**
- Verse pool: under 15 words, simple sentences
- Starting book: John (narrative, clear)
- Quiz: 3 options per question
- TTS speed: 0.8× (slower)
- Vocabulary: simple definitions only
- Speaking threshold: 60% accuracy to pass
- XP multiplier: 1.2×
- Writing exercise: copy the verse (no paraphrase)

**Intermediate:**
- Verse pool: standard KJV verses
- Starting book: Psalms
- Quiz: 4 options per question
- TTS speed: 1.0×
- Vocabulary: definition + example sentence
- Speaking threshold: 70% accuracy to pass
- XP multiplier: 1.0×
- Writing exercise: fill missing words from memory

**Advanced:**
- Verse pool: multi-clause, complex verses
- Starting book: Romans or Proverbs
- Quiz: 4 options, close distractors
- Additional: grammar analysis question, cross-reference question
- TTS speed: 1.0×
- Vocabulary: definition + etymology + grammar note
- Speaking threshold: 85% accuracy to pass
- XP multiplier: 0.9×
- Writing exercise: paraphrase in modern English (AI-scored)

### 3.3 Level Progression

**Level up trigger (all three signals tracked invisibly):**
- Quiz accuracy > 85% for 7 consecutive days
- Speaking score consistently > 80% (5 of last 7 attempts)
- Average lesson completion time < 3 minutes for 5 days

When 2 of 3 signals fire → bot sends:
> "You've been crushing your lessons this week 🔥 Ready for a challenge? We think you're ready for [next level]. Want to move up?"
> [Let's go 🚀] [Not yet, keep me here]

**Level down trigger:**
- Quiz accuracy < 50% for 5 consecutive days

Bot sends:
> "These lessons feeling tough? Sometimes slower is faster. Want to try the [lower level] track for a week?"
> [Yes, switch] [Keep me here]

User always controls the decision. Never force a level change.

---

## 4. XP ECONOMY

```
Daily verse read:                    10 XP
Vocabulary section completed:        10 XP
Listening exercise completed:        10 XP
Quiz completed:                      15 XP
Perfect quiz score (3/3):           +10 XP bonus
Speaking exercise attempted:         20 XP
Speaking score > 80%:               +15 XP bonus
Writing exercise completed:          15 XP
Writing score > 80% (AI-graded):    +10 XP bonus
Day streak maintained:               +5 XP per day (compounds to max +50)
First lesson before 9am:            +5 XP early bird
Sharing verse card to Telegram:     +10 XP
Referring a new user who completes day 1: +50 XP
```

Apply level XP multiplier after all bonuses are summed.

XP resets to zero every Monday at midnight (weekly league cycle).
Lifetime XP is tracked separately and never resets (for badges and rank).

---

## 5. COMPETITION SYSTEM

### 5.1 Weekly Leagues

Every Monday at midnight UTC:
- All active users (completed at least one lesson in the last 7 days) are placed into leagues
- Leagues are segregated by level (Beginner / Intermediate / Advanced)
- Each league contains 20–30 users of similar lifetime XP
- League runs Monday to Sunday
- Sunday midnight: results locked

**End of week outcomes:**
- Top 5 in each league: promoted to next division
- Bottom 5 in each league: relegated to previous division
- Middle: stay in same division

**Division names (thematic, Bible-based):**
1. Genesis (entry)
2. Psalms
3. Proverbs
4. John
5. Romans
6. Revelation (top)

**Weekly result message (every Sunday at 9pm):**
> "Your Proverbs League results are in! 🏆
> You finished 4th with 847 XP.
> Top 3 got promoted to John League.
> You're 103 XP behind 3rd place.
> New league starts tomorrow — come back strong! 💪"

### 5.2 Monthly Championship

Last week of every month:
- Top 3 from each division's weekly leagues qualify
- Single-elimination bracket tournament
- Separate championships per level (Beginner / Intermediate / Advanced)
- Winners get permanent Champion badge on profile

**Grand Championship:**
- Top 3 from each level championship compete
- One Grand Champion per month across all levels
- Permanent "👑 Grand Champion [Month Year]" badge
- Featured on app home screen for the following month

### 5.3 Group / Church Mode

Any user can create a group:
- Group has its own internal leaderboard (separate from global)
- Group admin sees aggregate stats: average XP, average streak, most active members
- Group can set a shared verse focus (e.g., "our church is studying John this month")
- Group leaderboard resets weekly same as global

---

## 6. BADGE SYSTEM

**Streak badges:**
- 🔥 Week Warrior: 7-day streak
- 📅 Monthly Faithful: 30-day streak
- 💯 Century: 100-day streak
- 📖 Year of the Word: 365-day streak

**Completion badges:**
- ✅ Gospel of John: complete all John verses
- 🎵 Psalms Complete: complete all Psalms verses
- 🧠 Proverbs Master: complete all Proverbs verses

**Performance badges:**
- ⭐ Perfect Week: 7 days consecutive with 100% quiz scores
- 🌅 Early Bird: complete lesson before 8am for 14 straight days
- 🗣️ Speaker: 50 speaking exercises completed
- ✍️ Writer: 50 writing exercises completed
- 🎯 Sharpshooter: 10 consecutive perfect quiz scores

**Competition badges:**
- 🏆 Champion [Level] [Month Year]: monthly championship winner
- 👑 Grand Champion [Month Year]: grand championship winner
- 🥇 League Leader: finish #1 in weekly league (any division)

All badges are permanent and visible on user profile.

---

## 7. AI ARCHITECTURE

### 7.1 Provider Abstraction

```php
interface AIProviderInterface {
    public function chat(array $messages, array $options = []): AIResponse;
    public function transcribe(string $audioFilePath): TranscriptionResult;
    public function testConnection(): AIConnectionResult;
    public function getModels(): array;
}
```

Create adapters for:
- Groq
- OpenRouter
- Google Gemini (via OpenAI-compatible endpoint)
- Custom OpenAI-compatible endpoint
- Disabled (no AI — fallback to static content)

### 7.2 AI Features and Their Prompts

**Feature: vocabulary_generation**
```
System: You are an English language tutor helping [LEVEL] learners understand the Bible in English. Be encouraging, clear, and simple.

User: From this KJV Bible verse, identify the 5 most important words for a [LEVEL] English learner to understand.

For each word provide:
- word: the word exactly as it appears
- simple_definition: [BEGINNER: one sentence, simple words] [INTERMEDIATE: one sentence with example] [ADVANCED: definition + etymology + grammar note]
- example_sentence: a modern example sentence using the word
- pronunciation_hint: how to say it (e.g. "sounds like 'ee-ter-nal'")

Return ONLY valid JSON, no markdown, no explanation:
{"vocabulary": [{"word": "", "simple_definition": "", "example_sentence": "", "pronunciation_hint": ""}]}

Verse: [VERSE_TEXT]
Book: [BOOK] Chapter: [CHAPTER] Verse: [VERSE_NUMBER]
```

**Feature: quiz_generation**
```
System: You are creating English comprehension exercises for [LEVEL] Bible learners.

User: Create [3 for BEGINNER, 3 for INTERMEDIATE, 4 for ADVANCED] fill-in-the-blank or comprehension questions from this verse.

Rules:
- BEGINNER: fill-in-the-blank only, 3 answer options, one word answers
- INTERMEDIATE: mix of fill-in-the-blank and meaning questions, 4 options
- ADVANCED: include one grammar analysis question, one cross-reference hint question, 4 close options

Each question must have exactly one correct answer and [2/3] plausible wrong answers.
Vary difficulty: include at least one easy, one medium question.

Return ONLY valid JSON:
{"questions": [{"question": "", "options": ["", "", ""], "correct_index": 0, "explanation": ""}]}

Verse: [VERSE_TEXT]
```

**Feature: speaking_score**
```
System: You are scoring English pronunciation for a [LEVEL] learner.

User: The learner was asked to read this verse aloud:
[VERSE_TEXT]

Their speech was transcribed as:
[TRANSCRIPTION]

Compare the transcription to the original verse. Identify:
1. Words they got right
2. Words they missed or mispronounced
3. Overall accuracy percentage (0-100)

Be encouraging. If score is low, note 1 specific word to practice.

Return ONLY valid JSON:
{"score": 75, "correct_words": ["for", "god", "loved"], "missed_words": ["begotten", "perish"], "tip": "Practice saying 'begotten' — it sounds like 'beh-GOT-ten'", "encouragement": "Great effort! You got the main words perfectly."}
```

**Feature: writing_score** (Advanced only)
```
System: You are evaluating an English paraphrase written by an Advanced Bible English learner.

User: Original KJV verse: [VERSE_TEXT]

Learner's paraphrase: [USER_TEXT]

Evaluate:
- Does it capture the core meaning? (0-40 points)
- Is the English grammatically correct? (0-30 points)
- Is it in their own words (not just copied)? (0-30 points)

Return ONLY valid JSON:
{"score": 85, "meaning_score": 35, "grammar_score": 28, "originality_score": 22, "feedback": "Excellent paraphrase! Your grammar is strong. Try using more of your own words next time.", "corrections": []}
```

**Feature: personalized_feedback**
```
System: You are an encouraging English tutor and Bible study guide. Keep responses under 40 words. Be warm, specific, and motivating.

User: Student level: [LEVEL]
Today's verse: [VERSE_TEXT]
Quiz score: [X]/[TOTAL] correct
Speaking score: [X]% (if attempted, else "not attempted")
Writing score: [X]% (if attempted, else "not attempted")  
Current streak: [N] days
XP earned today: [N]

Give them one encouraging sentence about their performance and one specific tip for tomorrow. Reference the verse content naturally.
```

**Feature: tomorrow_preview**
```
System: You write brief, motivating previews for a Bible English learning app. Under 25 words.

User: Tomorrow's verse is [BOOK] [CHAPTER]:[VERSE] — "[VERSE_TEXT]"
Today's user streak: [N] days.
Write a teaser that makes them want to come back tomorrow. Reference one interesting word from the verse.
```

### 7.3 Caching Strategy

- vocabulary_generation: cache per verse per level (generated once, served to all users at that level)
- quiz_generation: cache 5 variants per verse per level (rotate to avoid users sharing identical quizzes)
- speaking_score: never cache (unique per user recording)
- writing_score: never cache (unique per user text)
- personalized_feedback: never cache (unique per user session)
- tomorrow_preview: cache per verse (same for all users)

Cache duration: 30 days for verse-level content.

### 7.4 Fallback System

If primary AI provider fails (timeout, rate limit, error):
1. Try fallback provider (configured per feature in admin)
2. If fallback also fails: use static pre-built content for vocabulary and quiz
3. Log failure with provider, model, error type, timestamp
4. Never log API keys
5. Never show AI errors to users — degrade gracefully

### 7.5 AI Usage Limits (free tier management)

Daily request tracking:
- Count requests per provider per feature per day
- When approaching 80% of configured daily limit: switch to fallback
- When fallback also at 80%: switch to static content
- Dashboard shows: requests today, estimated daily limit remaining, provider health

---

## 8. BIBLE CONTENT

### 8.1 Primary API

**bible-api.com** — free, no API key, no rate limits, KJV public domain.

Endpoint format: `https://bible-api.com/[reference]?translation=kjv`

Example: `https://bible-api.com/john+3:16?translation=kjv`

Always cache verse responses locally in the database. Never make live API calls during a user lesson — fetch and cache on schedule.

### 8.2 Verse Sequencing

**Beginner pool (short verses, clear meaning):**
Start: John 3:16, John 1:1, John 14:6, Psalm 23:1, John 11:35 ("Jesus wept" — shortest verse, great for beginners), Proverbs 3:5-6, Romans 8:28

**Intermediate pool:**
Start: Psalms (sequential), then Proverbs, then Matthew

**Advanced pool:**
Start: Romans (sequential), then Hebrews, then Revelation

Verses are delivered in book-sequential order within each pool. Admin can override the sequence or add custom verse sets.

### 8.3 Verse Difficulty Tagging

On plugin activation, run a one-time verse classification job:
- Word count ≤ 15 → tag as beginner_eligible
- Word count 16–30 → tag as intermediate_eligible  
- Word count > 30 or contains semicolons/complex clauses → tag as advanced_eligible

Store tags in `wp_be_verse_cache` table. Admin can manually override tags.

---

## 9. VOICE FEATURES

### 9.1 Text-to-Speech (Listening Exercise)

**Primary:** Google Cloud TTS free tier (4M characters/month)
**Fallback:** Browser Web Speech API (no cost, lower quality)
**Premium fallback:** ElevenLabs (configured in admin, off by default)

TTS is triggered in the Mini App frontend. Audio plays inline.
Speed is set per level: Beginner 0.8×, Intermediate/Advanced 1.0×.

### 9.2 Speech Recognition (Speaking Exercise)

**Method:** User records audio in Mini App → audio file uploaded to WordPress REST endpoint → forwarded to Groq Whisper API → transcription returned → AI scoring applied.

Do NOT use Web Speech API for recognition (unreliable in Telegram Mini App on Android).

**Recording flow in Mini App:**
1. User taps "Start Recording" button
2. MediaRecorder API captures audio (WebM/Opus format)
3. Recording stops after user taps "Stop" or after 30 seconds max
4. Audio blob uploaded via multipart POST to `/be/v1/speaking/submit`
5. Server transcribes via Groq Whisper
6. Server scores via AI speaking_score feature
7. Result returned to Mini App: score, correct words (green), missed words (red), tip

**Groq Whisper endpoint:**
```
POST https://api.groq.com/openai/v1/audio/transcriptions
model: whisper-large-v3-turbo
```

If Groq Whisper fails or is rate-limited: return a "transcription unavailable" message and award partial XP for the attempt (20 XP for attempting, regardless of score).

### 9.3 Writing Exercise

Beginner: copy the verse text into an input field (accuracy scored by string match)
Intermediate: verse displayed with blanks, user fills missing words
Advanced: full text input, user paraphrases in their own words (AI-scored)

---

## 10. TELEGRAM INTEGRATION

### 10.1 Architecture

```
Telegram User
     ↓
Telegram Bot API / Mini App
     ↓
WordPress REST API (/be/v1/)
     ↓
Plugin Business Logic
     ↓
Database + AI Providers + Bible API
```

### 10.2 Bot Commands

```
/start          — open Mini App
/lesson         — start today's lesson
/streak         — show current streak
/leaderboard    — show current league standings
/profile        — show profile, badges, stats
/level          — show current level, progress to next
/help           — show help
/settings       — notification time, language preference
```

### 10.3 Webhook

Route: `POST /be/v1/telegram/webhook/{secret}`

Must:
- Validate secret against stored value
- Validate Telegram request signature (X-Telegram-Bot-Api-Secret-Token header)
- Support idempotency (track processed update_ids)
- Process updates asynchronously where possible
- Never log the bot token or webhook secret
- Return 200 immediately even if processing is queued

### 10.4 Telegram Authentication

All Mini App requests must:
1. Receive `initData` from Telegram.WebApp.initData
2. Server validates HMAC-SHA256 signature against bot token
3. Extract verified user data (id, first_name, username)
4. Never trust client-supplied user_id, level, XP, streak, or badge ownership
5. Return short-lived JWT for subsequent API calls

### 10.5 Mini App Pages

```
/          — Home (today's lesson status, streak, XP)
/lesson    — Active lesson flow
/profile   — Profile, badges, lifetime stats
/league    — Current league, leaderboard
/groups    — Church/group management
/settings  — Notification time, level, preferences
```

### 10.6 Notification Schedule

Daily verse notification: 7am user's local timezone (or admin-configured global time if timezone unknown).

Use WordPress cron + Action Scheduler for reliable scheduling.

Notification message format:
```
📖 Your daily verse is ready!

[BOOK] [CHAPTER]:[VERSE]

"[FIRST 10 WORDS OF VERSE]..."

Tap to start today's lesson 👇
[Open Lesson]
```

Reminder (if lesson not completed by 8pm):
```
⏰ Don't break your [N]-day streak!

Today's lesson takes less than 5 minutes.
[Open Lesson]
```

---

## 11. DATABASE SCHEMA

Use custom tables with WordPress prefix. Never use wp_posts or wp_postmeta as primary data store.

### `wp_be_users`
```sql
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
telegram_user_id BIGINT UNSIGNED UNIQUE NOT NULL,
telegram_username VARCHAR(255) NULL,
first_name VARCHAR(255) NOT NULL,
last_name VARCHAR(255) NULL,
language_code VARCHAR(10) DEFAULT 'en',
timezone VARCHAR(60) DEFAULT 'UTC',
level ENUM('beginner','intermediate','advanced') DEFAULT 'beginner',
placement_completed TINYINT(1) DEFAULT 0,
notification_time TIME DEFAULT '07:00:00',
notifications_enabled TINYINT(1) DEFAULT 1,
status ENUM('active','inactive','banned') DEFAULT 'active',
created_at DATETIME NOT NULL,
updated_at DATETIME NOT NULL,
last_active_at DATETIME NULL
```

### `wp_be_progress`
```sql
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
user_id BIGINT UNSIGNED NOT NULL,
verse_reference VARCHAR(50) NOT NULL,
book VARCHAR(50) NOT NULL,
chapter TINYINT UNSIGNED NOT NULL,
verse_number TINYINT UNSIGNED NOT NULL,
lesson_date DATE NOT NULL,
completed TINYINT(1) DEFAULT 0,
vocab_completed TINYINT(1) DEFAULT 0,
listening_completed TINYINT(1) DEFAULT 0,
quiz_completed TINYINT(1) DEFAULT 0,
quiz_score TINYINT UNSIGNED DEFAULT 0,
quiz_total TINYINT UNSIGNED DEFAULT 0,
speaking_completed TINYINT(1) DEFAULT 0,
speaking_score TINYINT UNSIGNED DEFAULT 0,
writing_completed TINYINT(1) DEFAULT 0,
writing_score TINYINT UNSIGNED DEFAULT 0,
xp_earned SMALLINT UNSIGNED DEFAULT 0,
completed_at DATETIME NULL,
created_at DATETIME NOT NULL,
UNIQUE KEY user_date (user_id, lesson_date)
```

### `wp_be_streaks`
```sql
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
user_id BIGINT UNSIGNED UNIQUE NOT NULL,
current_streak INT UNSIGNED DEFAULT 0,
longest_streak INT UNSIGNED DEFAULT 0,
last_lesson_date DATE NULL,
streak_started_at DATE NULL,
updated_at DATETIME NOT NULL
```

### `wp_be_xp`
```sql
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
user_id BIGINT UNSIGNED UNIQUE NOT NULL,
weekly_xp INT UNSIGNED DEFAULT 0,
lifetime_xp INT UNSIGNED DEFAULT 0,
week_start_date DATE NOT NULL,
updated_at DATETIME NOT NULL
```

### `wp_be_xp_log`
```sql
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
user_id BIGINT UNSIGNED NOT NULL,
amount SMALLINT NOT NULL,
reason VARCHAR(100) NOT NULL,
reference_type VARCHAR(50) NULL,
reference_id BIGINT UNSIGNED NULL,
created_at DATETIME NOT NULL
```

### `wp_be_leagues`
```sql
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
name VARCHAR(100) NOT NULL,
division ENUM('genesis','psalms','proverbs','john','romans','revelation') NOT NULL,
level ENUM('beginner','intermediate','advanced') NOT NULL,
week_start DATE NOT NULL,
week_end DATE NOT NULL,
status ENUM('active','completed') DEFAULT 'active',
created_at DATETIME NOT NULL
```

### `wp_be_league_members`
```sql
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
league_id BIGINT UNSIGNED NOT NULL,
user_id BIGINT UNSIGNED NOT NULL,
starting_xp INT UNSIGNED DEFAULT 0,
final_xp INT UNSIGNED DEFAULT 0,
rank TINYINT UNSIGNED NULL,
outcome ENUM('promoted','stayed','relegated') NULL,
UNIQUE KEY league_user (league_id, user_id)
```

### `wp_be_championships`
```sql
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
month TINYINT UNSIGNED NOT NULL,
year SMALLINT UNSIGNED NOT NULL,
level ENUM('beginner','intermediate','advanced','grand') NOT NULL,
status ENUM('upcoming','active','completed') DEFAULT 'upcoming',
winner_user_id BIGINT UNSIGNED NULL,
created_at DATETIME NOT NULL
```

### `wp_be_badges`
```sql
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
user_id BIGINT UNSIGNED NOT NULL,
badge_slug VARCHAR(100) NOT NULL,
badge_name VARCHAR(200) NOT NULL,
awarded_at DATETIME NOT NULL,
UNIQUE KEY user_badge (user_id, badge_slug)
```

### `wp_be_groups`
```sql
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
name VARCHAR(255) NOT NULL,
description TEXT NULL,
admin_user_id BIGINT UNSIGNED NOT NULL,
invite_code VARCHAR(20) UNIQUE NOT NULL,
verse_focus_book VARCHAR(50) NULL,
member_count INT UNSIGNED DEFAULT 0,
status ENUM('active','inactive') DEFAULT 'active',
created_at DATETIME NOT NULL
```

### `wp_be_group_members`
```sql
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
group_id BIGINT UNSIGNED NOT NULL,
user_id BIGINT UNSIGNED NOT NULL,
role ENUM('admin','member') DEFAULT 'member',
joined_at DATETIME NOT NULL,
UNIQUE KEY group_user (group_id, user_id)
```

### `wp_be_verse_cache`
```sql
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
reference VARCHAR(50) UNIQUE NOT NULL,
book VARCHAR(50) NOT NULL,
chapter TINYINT UNSIGNED NOT NULL,
verse_number TINYINT UNSIGNED NOT NULL,
text TEXT NOT NULL,
word_count TINYINT UNSIGNED NOT NULL,
difficulty_tag ENUM('beginner','intermediate','advanced') NOT NULL,
difficulty_override TINYINT(1) DEFAULT 0,
cached_at DATETIME NOT NULL
```

### `wp_be_ai_content_cache`
```sql
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
cache_key VARCHAR(200) UNIQUE NOT NULL,
feature VARCHAR(100) NOT NULL,
level ENUM('beginner','intermediate','advanced') NOT NULL,
verse_reference VARCHAR(50) NOT NULL,
variant TINYINT UNSIGNED DEFAULT 0,
content LONGTEXT NOT NULL,
provider VARCHAR(100) NOT NULL,
model VARCHAR(200) NOT NULL,
created_at DATETIME NOT NULL,
expires_at DATETIME NOT NULL
```

### `wp_be_ai_logs`
```sql
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
feature VARCHAR(100) NOT NULL,
provider VARCHAR(100) NOT NULL,
model VARCHAR(200) NOT NULL,
user_id BIGINT UNSIGNED NULL,
status ENUM('success','failure','fallback') NOT NULL,
input_tokens INT UNSIGNED NULL,
output_tokens INT UNSIGNED NULL,
latency_ms INT UNSIGNED NULL,
error_code VARCHAR(100) NULL,
created_at DATETIME NOT NULL
```

### `wp_be_notifications`
```sql
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
user_id BIGINT UNSIGNED NOT NULL,
type VARCHAR(100) NOT NULL,
message TEXT NOT NULL,
telegram_message_id BIGINT UNSIGNED NULL,
status ENUM('pending','sent','failed') DEFAULT 'pending',
scheduled_at DATETIME NOT NULL,
sent_at DATETIME NULL,
created_at DATETIME NOT NULL
```

---

## 12. REST API

**Namespace:** `/be/v1/`

### Authentication
```
POST /be/v1/auth/telegram
Body: { initData: string }
Response: { token: string, user: {...} }
```

All subsequent requests use `Authorization: Bearer {token}` header.

### User
```
GET  /be/v1/me
PUT  /be/v1/me/settings
GET  /be/v1/me/badges
GET  /be/v1/me/stats
```

### Lessons
```
GET  /be/v1/lesson/today
POST /be/v1/lesson/vocab/complete
POST /be/v1/lesson/listening/complete
POST /be/v1/lesson/quiz/submit        Body: { answers: [0,2,1] }
POST /be/v1/lesson/speaking/submit    Body: multipart audio file
POST /be/v1/lesson/writing/submit     Body: { text: string }
GET  /be/v1/lesson/feedback
```

### Placement
```
GET  /be/v1/placement/questions
POST /be/v1/placement/submit   Body: { answers: [0,1,2], time_seconds: 24 }
```

### Leaderboard
```
GET  /be/v1/league/current
GET  /be/v1/league/history
GET  /be/v1/leaderboard/global?level=beginner&limit=20
```

### Groups
```
GET  /be/v1/groups/mine
POST /be/v1/groups/create
POST /be/v1/groups/join    Body: { invite_code: string }
GET  /be/v1/groups/{id}/leaderboard
```

### Telegram
```
POST /be/v1/telegram/webhook/{secret}
```

### Admin (requires WordPress admin capability)
```
GET  /be/v1/admin/stats/overview
GET  /be/v1/admin/users
GET  /be/v1/admin/ai/logs
POST /be/v1/admin/ai/test
POST /be/v1/admin/telegram/test
POST /be/v1/admin/telegram/webhook/set
```

---

## 13. ADMIN PANEL — FULL SPECIFICATION

Create complete WordPress admin panel under menu: **Bible English**

### 13.1 Admin Menu Structure

```
Bible English
│
├── Dashboard
├── Users
├── Lessons
├── Leagues
├── Championships
├── Groups
├── Badges
├── Analytics
│
├── Settings
│   ├── General
│   ├── Telegram
│   ├── Mini App
│   ├── AI Providers
│   ├── AI Features
│   ├── Learning
│   ├── Competition
│   ├── Notifications
│   ├── Bible Content
│   ├── Voice / TTS
│   └── Security
│
└── System
    ├── Health
    ├── AI Logs
    ├── Cron Jobs
    ├── Cache
    └── Tools
```

---

### 13.2 Settings → General

```
Plugin Name:              [Bible English          ]
Tagline:                  [Learn English through the Word]
Default Language:         [English ▾]
Default Timezone:         [UTC ▾]
Daily Lesson Time:        [07:00]  (default push time)
Lesson Reminder Time:     [20:00]  (if lesson not completed)
Max Users Per League:     [30     ]
Min Users Per League:     [10     ]
XP Reset Day:             [Monday ▾]
Plugin Status:            [● Enabled / ○ Disabled]
Maintenance Mode:         [○ Enabled / ● Disabled]
```

---

### 13.3 Settings → Telegram

```
Bot Token:               [••••••••••••••••••••] [Show] [Save]
Bot Username:            [@BibleEnglishBot      ]
Webhook URL:             [https://yoursite.com/wp-json/be/v1/telegram/webhook/]
Webhook Secret:          [••••••••••••] [Regenerate]
Mini App URL:            [https://yoursite.com/bible-english-app/]
Admin Telegram IDs:      [123456789, 987654321  ] (comma-separated)

Actions:
[Set Webhook] [Delete Webhook] [Check Webhook Status] [Send Test Message]
[Open Mini App]

Webhook Status: ✅ Active — last ping 2 minutes ago
```

---

### 13.4 Settings → AI Providers

This is the core AI configuration page. It must support multiple provider configurations.

#### Provider List

Display all configured providers in a table:

| # | Name | Type | Model | Status | Test |
|---|------|------|-------|--------|------|
| 1 | Groq Main | Groq | llama-4-scout | ✅ Active | [Test] |
| 2 | OpenRouter Fallback | OpenRouter | google/gemini-2.5-flash | ✅ Active | [Test] |
| 3 | Custom Endpoint | Custom | gpt-4o-mini | ⚠️ Untested | [Test] |

[+ Add Provider]

#### Add / Edit Provider Form

```
Provider Configuration
──────────────────────────────────────────

Provider Name:     [Groq Main                    ]
                   (your internal label)

Provider Type:     [● Groq]  [○ OpenRouter]  [○ Google Gemini]  [○ Custom]

Enabled:           [● Yes  ○ No]

API Key:           [••••••••••••••••••••••••••••] [Show] [Save]
                   Stored encrypted. Never logged.

Base URL:          [https://api.groq.com/openai/v1          ]
                   (pre-filled per provider type, editable)

──────────────────────────────────────────
Model ID:          [llama-4-scout                            ]
                   ⚠️ Type any model ID supported by this provider.
                   This is a free-text field — any model string is valid.

                   [Fetch Available Models]
                   (optional — queries provider API to populate a list.
                   Manual entry above always takes priority.)

──────────────────────────────────────────
Default Temperature:    [0.3    ]  (0.0 to 1.0)
Default Max Tokens:     [1000   ]
Request Timeout (sec):  [30     ]
Retry Attempts:         [2      ]

──────────────────────────────────────────
Daily Request Limit:    [1400   ]
                        (set below provider's free tier limit)
Monthly Request Limit:  [0      ]  (0 = unlimited)

──────────────────────────────────────────
[Save Provider]  [Test Connection]  [Delete]

Test Result: ✅ Connection successful — model responded in 847ms
```

**Important:** The Model ID field MUST always be a free-text input. Fetching models from the API is an optional convenience only. Administrators must always be able to type any model ID manually, including models released after this plugin was built.

#### Provider Type Defaults (auto-fill Base URL when type selected)

```
Groq:           https://api.groq.com/openai/v1
OpenRouter:     https://openrouter.ai/api/v1
Google Gemini:  https://generativelanguage.googleapis.com/v1beta/openai
Custom:         (blank — admin enters manually)
```

---

### 13.5 Settings → AI Features

Each AI feature is independently configurable. Do NOT use a single global AI on/off switch as the only control.

```
Global AI Enabled:        [● Yes  ○ No]
                          (master switch — overrides all below if disabled)

──────────────────────────────────────────
FEATURE SETTINGS
──────────────────────────────────────────

Each feature below has independent configuration:

┌─────────────────────────────────────────────────────────┐
│ Vocabulary Generation                           [● On]  │
├─────────────────────────────────────────────────────────┤
│ Primary Provider:   [Groq Main ▾]                       │
│ Primary Model:      [llama-4-scout              ]       │
│                     (free text — overrides provider default) │
│ Temperature:        [0.3    ]                           │
│ Max Tokens:         [800    ]                           │
│ Timeout (sec):      [25     ]                           │
│                                                         │
│ Fallback Provider:  [OpenRouter Fallback ▾]             │
│ Fallback Model:     [google/gemini-2.5-flash    ]       │
│                     (free text)                         │
│                                                         │
│ Cache Duration:     [30     ] days                      │
│ Cache Variants:     [5      ] (quiz variants per verse) │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ Quiz Generation                                 [● On]  │
├─────────────────────────────────────────────────────────┤
│ Primary Provider:   [Groq Main ▾]                       │
│ Primary Model:      [llama-4-scout              ]       │
│ Temperature:        [0.5    ]  (higher = more varied)   │
│ Max Tokens:         [600    ]                           │
│ Fallback Provider:  [OpenRouter Fallback ▾]             │
│ Fallback Model:     [google/gemini-2.5-flash    ]       │
│ Cache Duration:     [30     ] days                      │
│ Cache Variants:     [5      ]                           │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ Speaking Score                                  [● On]  │
├─────────────────────────────────────────────────────────┤
│ Transcription Provider: [Groq Main ▾]                   │
│ Transcription Model:    [whisper-large-v3-turbo ]       │
│                         (free text)                     │
│ Scoring Provider:       [Groq Main ▾]                   │
│ Scoring Model:          [llama-4-scout          ]       │
│ Fallback on failure:    [Award partial XP ▾]            │
│ Partial XP on failure:  [20    ]                        │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ Writing Score (Advanced only)                   [● On]  │
├─────────────────────────────────────────────────────────┤
│ Primary Provider:   [Groq Main ▾]                       │
│ Primary Model:      [llama-4-scout              ]       │
│ Temperature:        [0.2    ]                           │
│ Fallback Provider:  [OpenRouter Fallback ▾]             │
│ Fallback Model:     [google/gemini-2.5-flash    ]       │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ Personalized Feedback                           [● On]  │
├─────────────────────────────────────────────────────────┤
│ Primary Provider:   [Groq Main ▾]                       │
│ Primary Model:      [llama-4-scout              ]       │
│ Temperature:        [0.7    ]  (warmer tone)            │
│ Max Tokens:         [150    ]                           │
│ Fallback Provider:  [OpenRouter Fallback ▾]             │
│ Fallback Model:     [google/gemini-2.5-flash    ]       │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ Tomorrow's Preview                              [● On]  │
├─────────────────────────────────────────────────────────┤
│ Primary Provider:   [Groq Main ▾]                       │
│ Primary Model:      [llama-4-scout              ]       │
│ Temperature:        [0.8    ]                           │
│ Max Tokens:         [80     ]                           │
│ Cache Duration:     [30     ] days                      │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ Spam / Abuse Detection                          [● On]  │
├─────────────────────────────────────────────────────────┤
│ (Checks writing submissions for inappropriate content)  │
│ Primary Provider:   [Groq Main ▾]                       │
│ Primary Model:      [llama-4-scout              ]       │
└─────────────────────────────────────────────────────────┘

[Save All AI Feature Settings]
```

---

### 13.6 Settings → Learning

```
LEVEL SETTINGS
──────────────────────────────────────────

Beginner
  XP Multiplier:          [1.2  ]
  Quiz Options:           [3    ]  (number of answer choices)
  TTS Speed:              [0.8  ]  (0.5 to 1.5)
  Speaking Pass Threshold:[60   ]% minimum to pass speaking exercise
  Max Verse Word Count:   [15   ]  (beginner verse pool filter)

Intermediate
  XP Multiplier:          [1.0  ]
  Quiz Options:           [4    ]
  TTS Speed:              [1.0  ]
  Speaking Pass Threshold:[70   ]%
  Min Verse Word Count:   [10   ]
  Max Verse Word Count:   [30   ]

Advanced
  XP Multiplier:          [0.9  ]
  Quiz Options:           [4    ]
  TTS Speed:              [1.0  ]
  Speaking Pass Threshold:[85   ]%
  Min Verse Word Count:   [20   ]
  Include Grammar Analysis Question: [● Yes ○ No]
  Include Cross-reference Question:  [● Yes ○ No]

──────────────────────────────────────────
LEVEL PROGRESSION

Level Up Triggers (2 of 3 required):
  Quiz accuracy > [85]% for [7] consecutive days
  Speaking score > [80]% for [5] of last [7] attempts
  Avg lesson time < [3] minutes for [5] consecutive days

Level Down Trigger:
  Quiz accuracy < [50]% for [5] consecutive days

──────────────────────────────────────────
VOCABULARY SETTINGS

Words per lesson:         [5    ]
Cache vocabulary results: [● Yes ○ No]
Cache duration (days):    [30   ]

──────────────────────────────────────────
LESSON SETTINGS

Max recording length (seconds): [30  ]
Min recording length (seconds): [3   ]
Quiz time limit (seconds):      [0   ]  (0 = no limit)
Show correct answer after wrong: [● Yes ○ No]
Allow lesson replay same day:   [● Yes ○ No]
Streak grace period (hours):    [2   ]  (if lesson completed slightly past midnight)
```

---

### 13.7 Settings → Competition

```
LEAGUE SETTINGS
──────────────────────────────────────────
Leagues Enabled:          [● Yes ○ No]
Users per league:         [20    ] to [30    ]
Week start day:           [Monday ▾]
Promotion spots:          [5     ]  (top N promoted each week)
Relegation spots:         [5     ]  (bottom N relegated each week)

Division names (editable):
  Tier 1 (entry):  [Genesis    ]
  Tier 2:          [Psalms     ]
  Tier 3:          [Proverbs   ]
  Tier 4:          [John       ]
  Tier 5:          [Romans     ]
  Tier 6 (top):    [Revelation ]

──────────────────────────────────────────
CHAMPIONSHIP SETTINGS
──────────────────────────────────────────
Championships Enabled:      [● Yes ○ No]
Championship frequency:     [Monthly ▾]
Qualifiers per division:    [3     ]  (top N from weekly leagues qualify)
Grand Championship enabled: [● Yes ○ No]
Grand Championship qualifiers per level: [3  ]

──────────────────────────────────────────
XP SETTINGS
──────────────────────────────────────────
Daily verse read:           [10   ]
Vocabulary completed:       [10   ]
Listening completed:        [10   ]
Quiz completed:             [15   ]
Perfect quiz bonus:         [10   ]
Speaking attempted:         [20   ]
Speaking >80% bonus:        [15   ]
Writing completed:          [15   ]
Writing >80% bonus:         [10   ]
Streak bonus (per day):     [5    ]
Streak bonus max:           [50   ]
Early bird bonus:           [5    ]
Verse share bonus:          [10   ]
Referral bonus:             [50   ]
```

---

### 13.8 Settings → Voice / TTS

```
TEXT-TO-SPEECH
──────────────────────────────────────────
TTS Enabled:              [● Yes ○ No]

Primary TTS Provider:     [● Google Cloud TTS  ○ ElevenLabs  ○ Browser Only]

Google Cloud TTS:
  API Key:                [•••••••••••••••••••••] [Show]
  Voice (Beginner):       [en-US-Standard-C ▾]
  Voice (Int/Adv):        [en-US-Standard-D ▾]
  Free tier limit chars:  [4000000]  per month

ElevenLabs (premium fallback):
  API Key:                [•••••••••••••••••••••] [Show]
  Voice ID:               [EXAVITQu4vr4xnSDxMaL ]  (free text)
  Enabled:                [○ Yes ● No]

Fallback order:
  [Google TTS] → [Browser Web Speech API] → [Disabled]

──────────────────────────────────────────
SPEECH RECOGNITION
──────────────────────────────────────────
Speaking Exercises:       [● Enabled ○ Disabled]
Transcription via:        [Groq Whisper ▾]
  (configured in AI Providers — uses Groq API key)

Max audio file size (MB): [5    ]
Allowed audio formats:    [webm, mp4, ogg, wav]
```

---

### 13.9 Settings → Bible Content

```
BIBLE API
──────────────────────────────────────────
Primary Bible API:        [bible-api.com ▾]
API Base URL:             [https://bible-api.com/]
Default Translation:      [KJV ▾]
Local Verse Cache:        [● Enabled ○ Disabled]
Cache Duration (days):    [365 ]

──────────────────────────────────────────
VERSE SEQUENCING
──────────────────────────────────────────
Beginner Starting Book:   [John ▾]
Intermediate Starting Book:[Psalms ▾]
Advanced Starting Book:   [Romans ▾]

Delivery Order:           [● Sequential  ○ Random  ○ Admin-curated]

──────────────────────────────────────────
DIFFICULTY TAGGING
──────────────────────────────────────────
Auto-tag on:              [● Activation  ○ Manual only]
Beginner max word count:  [15   ]
Advanced min word count:  [20   ]

[Re-run Verse Classification]  [Export Verse List]
```

---

### 13.10 Settings → Notifications

```
NOTIFICATION SETTINGS
──────────────────────────────────────────
Notifications Enabled:    [● Yes ○ No]
Default lesson time:      [07:00]
Reminder enabled:         [● Yes ○ No]
Reminder time:            [20:00]  (if lesson not completed)
Reminder only after N days streak: [3   ]  (don't remind brand new users)

Weekend notifications:    [● Same schedule  ○ Disabled on weekends]

──────────────────────────────────────────
MESSAGE TEMPLATES (editable)
──────────────────────────────────────────
Daily Lesson Template:
[📖 Your daily verse is ready!

{book} {chapter}:{verse}
"{verse_preview}..."

Tap to start today's lesson 👇]

Reminder Template:
[⏰ Don't break your {streak}-day streak!

Today's lesson takes less than 5 minutes.
[Open Lesson]]

League Result Template:
[📊 Your {division} League results:

You finished #{rank} with {xp} XP.
New league starts Monday. Come back strong! 💪]

Level Up Offer Template:
[🎉 You've been crushing your lessons this week!

Ready for {next_level} level? Want to move up?]
```

---

### 13.11 Settings → Security

```
SECURITY SETTINGS
──────────────────────────────────────────
Webhook Secret:           [••••••••••••] [Regenerate] [Copy]
JWT Expiry (minutes):     [60   ]
REST Rate Limit (req/min):[60   ]
Max Upload Size (MB):     [10   ]
Allowed Audio MIME Types: [audio/webm, audio/mp4, audio/ogg, audio/wav]
Admin Telegram IDs:       [123456789        ]  (comma-separated)
Audit Logging:            [● Enabled ○ Disabled]
Ban on abuse:             [● Auto  ○ Manual only]
Abuse threshold:          [10   ] failed auth attempts per hour
```

---

### 13.12 Dashboard

Display at a glance:

```
BIBLE ENGLISH DASHBOARD
──────────────────────────────────────────
Total Users:          12,847    ↑ 234 this week
Active Today:          3,421    ↑ 12% vs yesterday
Current Streaks:       8,234 users with active streaks
Avg Streak Length:     14 days

Today's Lesson:       John 3:17 — sent to 3,421 users

──────────────────────────────────────────
LEVEL BREAKDOWN
Beginner:     6,234 users (48%)
Intermediate: 4,891 users (38%)
Advanced:     1,722 users (14%)

──────────────────────────────────────────
AI USAGE TODAY
Groq Main:         847 requests  [████████░░] 60% of daily limit
OpenRouter:        203 requests  [██░░░░░░░░] 14% of daily limit
Fallback used:     12 times
Failures:          3

──────────────────────────────────────────
COMPETITION
Active leagues:     427
Active championship: July 2026 — 14 days remaining
Current Grand Champion: @ethiopian_user_123

──────────────────────────────────────────
SYSTEM HEALTH
WordPress:     ✅   Database:    ✅   REST API:    ✅
Telegram:      ✅   Webhook:     ✅   Cron:        ✅
AI Primary:    ✅   AI Fallback: ✅   Bible API:   ✅
TTS:           ✅   Whisper:     ✅
```

---

### 13.13 System → Health

```
SYSTEM HEALTH CHECK
──────────────────────────────────────────
WordPress version:        6.5.2        ✅
PHP version:              8.2.1        ✅
Database:                 Connected    ✅
REST API:                 Accessible   ✅
Telegram Bot API:         Reachable    ✅
Telegram Webhook:         Active       ✅
Mini App URL:             Accessible   ✅
AI Primary (Groq):        Responding   ✅
AI Fallback (OpenRouter): Responding   ✅
Google TTS:               Configured   ✅
Groq Whisper:             Responding   ✅
Bible API:                Responding   ✅
Action Scheduler:         Running      ✅
Upload Directory:         Writable     ✅
Database Version:         v1.0.0       ✅

──────────────────────────────────────────
ACTIONS
[Test Telegram Bot]       [Test Primary AI]
[Test Fallback AI]        [Test Bible API]
[Test TTS]                [Test Whisper]
[Test REST API]           [Run DB Check]
[Send Test Notification]  [Clear All Cache]
[Retry Failed Notifications]
```

---

### 13.14 System → AI Logs

Filterable table:

| Time | Feature | Provider | Model | User | Status | Tokens | Latency |
|------|---------|---------|-------|------|--------|--------|---------|
| 14:23 | vocab_gen | Groq | llama-4-scout | - | ✅ | 412 | 843ms |
| 14:22 | quiz_gen | Groq | llama-4-scout | - | ✅ | 388 | 721ms |
| 14:21 | speaking_score | Groq | llama-4-scout | #1234 | ✅ | 201 | 534ms |
| 14:20 | personalized_feedback | OpenRouter | gemini-2.5-flash | #1235 | ⚠️ fallback | 156 | 1243ms |

Filters: Feature, Provider, Status, Date range, User ID
Export: [Download CSV]

---

### 13.15 System → Cron Jobs

```
SCHEDULED JOBS
──────────────────────────────────────────
Daily verse fetch & cache:     Daily 01:00 UTC    ✅ Last run: 01:00
AI content pre-generation:     Daily 02:00 UTC    ✅ Last run: 02:00
Lesson notifications:          Dynamic per user   ✅ Running
Lesson reminders:              Dynamic per user   ✅ Running
Streak expiry check:           Daily 00:05 UTC    ✅ Last run: 00:05
League rotation (weekly):      Monday 00:00 UTC   ✅ Next: Monday
XP weekly reset:               Monday 00:01 UTC   ✅ Next: Monday
Level progression check:       Daily 08:00 UTC    ✅ Last run: 08:00
Badge award check:             Daily 08:05 UTC    ✅ Last run: 08:05
Championship processing:       1st of month       ✅ Next: Aug 1
Notification retry:            Every 15 min       ✅ Last run: 14:15
AI cache cleanup:              Daily 03:00 UTC    ✅ Last run: 03:00

[Run Job Now ▾]  [Clear Failed Jobs]
```

---

## 14. MINI APP — FRONTEND

### 14.1 Tech Stack

- Vanilla JavaScript (no heavy framework — must load fast on low-end Android)
- Telegram Mini App SDK (window.Telegram.WebApp)
- CSS custom properties for theming (auto-adapts to Telegram's light/dark theme)
- No external CSS frameworks
- All API calls to WordPress REST endpoints

### 14.2 Home Screen

```
┌─────────────────────────────────┐
│  Bible English         🔔  👤   │
│─────────────────────────────────│
│                                 │
│  Good morning, Abebe! 👋        │
│                                 │
│  🔥 14-day streak               │
│  ⭐ 847 XP this week            │
│  📖 Intermediate · Psalms       │
│                                 │
│  ┌─────────────────────────┐   │
│  │  Today's verse ready!   │   │
│  │  John 3:17              │   │
│  │  [Start Lesson →]       │   │
│  └─────────────────────────┘   │
│                                 │
│  Your league: #4 of 25         │
│  47 XP behind #3               │
│  [View League]                  │
│                                 │
│─────────────────────────────────│
│  🏠 Home  📖 Lesson  🏆 League  │
│         👤 Profile              │
└─────────────────────────────────┘
```

### 14.3 Lesson Flow Screens

**Screen 1 — Verse:**
- Large verse text display
- Book/chapter/verse reference
- [Continue →]

**Screen 2 — Vocabulary:**
- Word displayed prominently
- Simple definition
- Example sentence
- Pronunciation hint
- [🔊 Hear it] button (TTS)
- Swipe or tap through 5 words
- Progress dots: ●●○○○

**Screen 3 — Listening:**
- "Listen to the full verse"
- [▶ Play verse] (TTS audio)
- Play button, progress bar
- Replay available
- [I'm ready →]

**Screen 4 — Quiz:**
- Question displayed
- Answer options as large tap targets
- Immediate visual feedback: ✅ green or ❌ red
- Brief explanation shown after each answer
- Progress: Question 1 of 3
- No time limit (unless admin-configured)

**Screen 5 — Speaking:**
- "Now say the verse aloud"
- Verse displayed for reference
- [🎤 Start Recording] button
- Recording indicator (animated waveform)
- [⏹ Stop] button
- Upload + processing state
- Result: score, correct words (green), missed words (red), one tip

**Screen 6 — Writing:** (varies by level)
- Beginner: copy the verse
- Intermediate: fill the blanks
- Advanced: text area for paraphrase

**Screen 7 — Feedback + Results:**
- XP breakdown (animated count-up)
- AI tutor message (personalized)
- Streak updated
- Tomorrow's preview
- Badge awarded (if applicable, animated celebration)
- [Share Result] → generates verse card image for Telegram groups

---

## 15. FILE STRUCTURE

```
bible-english/
├── bible-english.php              (plugin bootstrap)
├── uninstall.php
├── composer.json
│
├── config/
│   └── defaults.php
│
├── includes/
│   ├── Core/
│   │   ├── class-plugin.php
│   │   ├── class-loader.php
│   │   └── class-activator.php
│   ├── Database/
│   │   ├── class-migrator.php
│   │   └── migrations/
│   ├── REST/
│   │   └── controllers/
│   ├── Users/
│   ├── Lessons/
│   ├── AI/
│   │   ├── interface-ai-provider.php
│   │   ├── class-ai-manager.php
│   │   ├── class-ai-cache.php
│   │   ├── adapters/
│   │   │   ├── class-groq-adapter.php
│   │   │   ├── class-openrouter-adapter.php
│   │   │   ├── class-gemini-adapter.php
│   │   │   └── class-custom-adapter.php
│   │   └── features/
│   │       ├── class-vocabulary-generator.php
│   │       ├── class-quiz-generator.php
│   │       ├── class-speaking-scorer.php
│   │       ├── class-writing-scorer.php
│   │       ├── class-feedback-generator.php
│   │       └── class-preview-generator.php
│   ├── Bible/
│   │   ├── class-bible-api.php
│   │   └── class-verse-sequencer.php
│   ├── Voice/
│   │   ├── class-tts-service.php
│   │   └── class-whisper-service.php
│   ├── Competition/
│   │   ├── class-league-manager.php
│   │   ├── class-championship-manager.php
│   │   └── class-xp-manager.php
│   ├── Badges/
│   ├── Groups/
│   ├── Notifications/
│   ├── Telegram/
│   │   ├── class-bot-api.php
│   │   ├── class-webhook-handler.php
│   │   ├── class-auth-service.php
│   │   └── class-mini-app-service.php
│   └── Security/
│
├── admin/
│   ├── class-admin.php
│   ├── views/
│   │   ├── dashboard.php
│   │   ├── settings-general.php
│   │   ├── settings-telegram.php
│   │   ├── settings-ai-providers.php
│   │   ├── settings-ai-features.php
│   │   ├── settings-learning.php
│   │   ├── settings-competition.php
│   │   ├── settings-notifications.php
│   │   ├── settings-voice.php
│   │   ├── settings-bible.php
│   │   ├── settings-security.php
│   │   ├── system-health.php
│   │   ├── system-logs.php
│   │   ├── system-cron.php
│   │   ├── users.php
│   │   ├── leagues.php
│   │   └── championships.php
│   └── assets/
│
├── mini-app/
│   ├── index.html
│   ├── app.js
│   ├── styles.css
│   └── screens/
│       ├── home.js
│       ├── lesson.js
│       ├── quiz.js
│       ├── speaking.js
│       ├── writing.js
│       ├── results.js
│       ├── profile.js
│       ├── league.js
│       └── groups.js
│
└── assets/
    ├── css/
    └── js/
```

---

## 16. CODING RULES FOR CLAUDE CODE

Before writing any code:

1. Inspect the full repository structure
2. Identify WordPress and PHP versions available
3. Identify any existing plugins that may conflict
4. Create an implementation map before writing files
5. Implement incrementally — one subsystem at a time
6. Test each subsystem before moving to the next

Rules:
- PHP 8.1+ minimum
- Use WordPress REST API — never `admin-ajax`
- Use custom database tables — never wp_posts/postmeta as primary data
- Use `$wpdb` with prepared statements always
- OOP architecture with interfaces
- All admin settings use WordPress Settings API
- Capability checks on every admin and REST endpoint
- Validate and sanitize all input
- Escape all output
- Never hard-code API keys, model IDs, or category slugs
- Never expose bot token or API keys in frontend code
- Never trust client-supplied user_id, level, XP, streak, badge status
- Resolve all identity and permissions server-side
- JWT expiry must be enforced server-side
- Rate limit all public REST endpoints
- Never log API keys, bot tokens, or user audio content
- Degrade gracefully when AI fails — never show raw errors to users
- Use Action Scheduler for reliable background jobs (not plain WP-Cron)
- Cache Bible API responses locally — never make live calls during a user lesson
- AI content cache must be keyed by verse reference + level + feature + variant
- Model ID fields must always be free-text inputs — never hard-coded dropdowns
- Admin must be able to configure every AI feature independently
- Keep Telegram layer separate from business logic
- Keep AI provider layer separate from feature logic
- Keep payment infrastructure out entirely (MVP)

When a reasonable implementation choice is not specified, choose the simplest production-safe implementation and document the decision rather than stopping for clarification.

---

## 17. ACCEPTANCE TEST

The system is not complete until this scenario passes end-to-end:

**New user:**
1. Opens bot → taps Start → Mini App opens
2. Telegram auth validated server-side
3. Placement quiz shown → user answers → level assigned (Intermediate)
4. Home screen shows: streak 0, league pending, today's verse
5. User taps Start Lesson
6. Verse displayed (John 3:16, Intermediate)
7. Vocabulary shown (5 words, AI-generated, cached)
8. TTS audio plays the verse
9. Quiz shown (4 options, AI-generated) → user answers 2/3 correctly
10. Speaking exercise: user records audio → Whisper transcribes → AI scores → result shown
11. Writing exercise: user fills blanks
12. Results screen: XP counted up, AI feedback shown, streak updated to 1
13. Tomorrow's preview shown
14. User placed in Beginner League (auto on Monday)

**Admin:**
1. Dashboard shows new user count updated
2. AI logs show vocab generation (Groq), quiz generation (Groq), speaking score (Groq)
3. System health shows all green
4. Settings → AI Providers → can type any model ID in free-text field
5. Settings → AI Features → can set different provider per feature
6. Can send test Telegram message
7. Can view today's lesson notifications queue

---

## 18. FINAL PRINCIPLE

The product is:
> One verse. Every morning. AI-powered vocabulary, listening, speaking, and writing — all free. Compete with friends. Never miss a day.

The engagement metric is:
> Day-7 retention above 40%.

Build toward that metric. Everything else is secondary.
