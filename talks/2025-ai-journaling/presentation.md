---
marp: true
theme: gaia
size: 16:9
paginate: true
style: |
    h1, h2, h3, strong {
        color: #0066cc;
    }
    small {
        font-size: 0.57em;
    }
---

<!-- _class: lead -->

# AI-Powered Journaling

---

## 👋 Who am I?

**Timo Bakx**
Senior Backend (PHP) Developer @ Linku

<!-- 
Vertel over de conferentie jaren geleden:
Closing keynote over aphantasia
-->

---

## 👋 Who am I?

**Timo Bakx**
Senior Backend (PHP) Developer @ Linku

**My quirks:**

---

## 👋 Who am I?

**Timo Bakx**
Senior Backend (PHP) Developer @ Linku

**My quirks:**
- No visual imagination (aphantasia)

---

## 👋 Who am I?

**Timo Bakx**
Senior Backend (PHP) Developer @ Linku

**My quirks:**
- No visual imagination (aphantasia)
- No internal monologue/dialogue

---

## 👋 Who am I?

**Timo Bakx**
Senior Backend (PHP) Developer @ Linku

**My quirks:**
- No visual imagination (aphantasia)
- No internal monologue/dialogue
- Cannot relive memories

---

## 😰 Which results in...

---

## 😰 Which results in...

- Difficult to articulate growth

---

## 😰 Which results in...

- Difficult to articulate growth
- Cannot see patterns over time

---

## 😰 Which results in...

- Difficult to articulate growth
- Cannot see patterns over time
- Challenging performance reviews

---

## 😰 Which results in...

- Difficult to articulate growth
- Cannot see patterns over time
- Challenging performance reviews
- Evaluated as 'stagnant'

---

## 📝 Previous attempts

---

## 📝 Previous attempts

- On paper
    - Not searchable
    - Too slow
    - Not readable

---

## 📝 Previous attempts

- On paper
    - Not searchable
    - Too slow
    - Not readable
- Digital on phone
    - Typing is difficult
    - No feedback

---

## 💡 The solution: AI Coach

Not just a note-taking tool.

But rather a sparring partner / coach.

With a critical eye.

---

## 🤝 What does an AI coach do?

---

## 🤝 What does an AI coach do?

- Memory function for daily events

---

## 🤝 What does an AI coach do?

- Memory function for daily events
- Pattern recognition during evaluations

---

## 🤝 What does an AI coach do?

- Memory function for daily events
- Pattern recognition during evaluations
- Direct feedback: asking questions when unclear

---

## 🤝 What does an AI coach do?

- Memory function for daily events
- Pattern recognition during evaluations
- Direct feedback: asking questions when unclear
- Critical perspective: course correction when needed

---

## 🔍 How does it work?

The daily flow in 3 steps:

---

## 🔍 How does it work?

The daily flow in 3 steps:
1. Prepare at the start of the day

---

### 1. Prepare at the start of the day

**👤 User:**
> Good morning! Will you start a new day for me? I have no other tasks to do than those left over from last Friday.
> I had a day off yesterday, so it's Tuesday today.

---

### 1. Prepare at the start of the day

**🤖 AI Coach:**
> Good morning! ☀️ I've created your **Tuesday, November 25th** journal entry with the three tasks carried over from Friday:
> 1. Implement shipment tracking filters (Customer Portal API)
> 2. Spike: returns management state machine with Workflow component (Customer Portal API)
> 3. Review Co's authentication implementation (Internal Tools)
>
> Have a great day getting back into things after your day off!

---

## 🔍 How does it work?

The daily flow in 3 steps:
1. Prepare at the start of the day
1. Throughout the day, share what I'm doing

---

### 2. Throughout the day

**👤 User:**
> I finished implementing the shipping tracking filters. And I learned something new about API Platform filtering.

---

### 2. Throughout the day

**🤖 AI Coach:**
> Perfect! ✅ I've checked off the task and started documenting it. **What specifically did you learn about API Platform filtering?** I'd love to capture those details in your journal so you can reference them later.

---

### 2. Throughout the day

**👤 User:**
> I didn't know you could reuse the SearchFilter for multiple properties at the same time. So that you don't have to define a service for each one.

---

### 2. Throughout the day

**🤖 AI Coach:**
> Perfect! ✅ I've documented your API Platform learning. That's a great discovery - being able to reuse SearchFilter across multiple properties definitely keeps the code cleaner and reduces duplication.
>
> You still have two tasks remaining for today. Are you planning to work on one of these next?

---

### 2. Throughout the day - Journal Result

```markdown
## 📝 Daily Flow - Events

### Customer Portal API - Implementing Shipment Tracking Filters

Completed the shipment tracking filters feature today. This allows 
customers to filter their shipment history by various criteria.

**API Platform Learning:** Discovered that you can reuse the 
SearchFilter for multiple properties at the same time, rather than 
having to define a separate service for each property.
```

---

### 2. Throughout the day

**👤 User:**
> Just did the review. It was great work. There were some small things that could improved, mostly around naming.

---

### 2. Throughout the day

**🤖 AI Coach:**
> Excellent! ✅ I've documented the code review. It's great to see Co's progress - and providing constructive feedback on naming conventions is exactly the kind of mentoring that helps junior developers grow.
>
> You now have **one remaining task** for today. Are you planning to tackle that next?

---

### 2. Throughout the day - Journal Result

```markdown
### Internal Tools - Reviewing Co's Authentication Implementation

Reviewed Co's authentication implementation. The overall quality was 
great - Co is really picking things up quickly. Provided some feedback 
on small improvements, mostly around naming conventions to make the 
code more consistent with our existing codebase standards.
```

---

## 🔍 How does it work?

The daily flow in 3 steps:
1. Prepare at the start of the day
1. Throughout the day, share what I'm doing
1. At the end of the day, reflect

---

### 3. At the end of the day, reflect

**👤 User:**
> I'm about to head home. Shall we review the day?
>
> I'm proud of how fast I could implement the filters. That went really well.

---

### 3. At the end of the day, reflect

**🤖 AI Coach:**
> Perfect! 🎉 Your day review is complete. Here's what stands out:
>
> **Key Highlights**
> - ✅ Implemented shipment tracking filters (quickly!)
> - ✅ Learned about API Platform SearchFilter reusability
> - ✅ Reviewed Co's authentication code with constructive feedback

---

### 3. At the end of the day, reflect

**🤖 AI Coach:**
> **Key Insight:** Your speed on the filter implementation shows that last week's deep learning is paying dividends. You're moving from "learning" to "mastery".

---

### 3. Reflect - Journal Result

```markdown
### 🌟 What I am proud of

**Fast implementation** - The shipment tracking filters went really 
well. I was able to implement them quickly and efficiently, which 
shows I'm getting more comfortable with API Platform and the codebase.

**Learning while doing** - Not only did I complete the feature, but I 
also discovered a better way to use SearchFilter for multiple properties.

**Quality code review** - Gave Co constructive feedback on his 
authentication work. The naming suggestions I provided will help 
maintain code consistency.
```

---

### 3. Reflect - Journal Result

```markdown
### 🌱 Growth & Insights

**Learning Goal: Symfony Expertise** - Today continues the momentum 
from last week. You deepened your API Platform knowledge by discovering 
the SearchFilter multi-property pattern. More importantly, you 
implemented the filters *quickly* - speed is a sign of growing mastery.

**Mentoring Pattern** - You're continuing to develop as a mentor. Last 
week you helped Co learn authentication concepts, this week you're 
reviewing his work and guiding him on code standards.

**Efficiency Through Knowledge** - The fast filter implementation 
demonstrates something important: deep learning pays off.
```

---

## 🔍 How does it work?

The daily flow in 3 steps:
1. Prepare at the start of the day
1. Throughout the day, share what I'm doing
1. At the end of the day, reflect

---

## 📊 Weekly evaluation

**Goal:** See patterns across the week

---

## 📊 Weekly evaluation

**Goal:** See patterns across the week

AI analyzes:

---

## 📊 Weekly evaluation

**Goal:** See patterns across the week

AI analyzes:
- Recurring themes

---

## 📊 Weekly evaluation

**Goal:** See patterns across the week

AI analyzes:
- Recurring themes
- Make growth visible

---

## 📊 Weekly evaluation

**Goal:** See patterns across the week

AI analyzes:
- Recurring themes
- Make growth visible
- Signal improvement points

---

## 📊 Weekly evaluation

**Goal:** See patterns across the week

AI analyzes:
- Recurring themes
- Make growth visible
- Signal improvement points
- Highlight learnings

---

## 💼 Processing performance reviews

**From conversation to growth:**

---

## 💼 Processing performance reviews

**From conversation to growth:**

1. Add transcript of performance review

---

## 💼 Processing performance reviews

**From conversation to growth:**

1. Add transcript of performance review
1. Analyze with AI Coach against personality

---

## 💼 Processing performance reviews

**From conversation to growth:**

1. Add transcript of performance review
1. Analyze with AI Coach against personality
1. Recognize patterns

---

## 💼 Processing performance reviews

**From conversation to growth:**

1. Add transcript of performance review
1. Analyze with AI Coach against personality
1. Recognize patterns
1. Formulate learning goals

---

## 💼 Processing performance reviews

**From conversation to growth:**

1. Add transcript of performance review
1. Analyze with AI Coach against personality
1. Recognize patterns
1. Formulate learning goals
1. Define concrete actions

---

## 👥 People (optional)

Separate file per person:
- Background information
- Observations about collaboration
- Communication patterns
- What works / what doesn't
- Backlinks to journal files

**Goal:** Better collaboration by seeing patterns

---

## 📦 Projects (optional)

Separate file per project:
- General description
- Context and history
- Key learnings
- Backlinks to journal files

**Goal:** Maintain context across projects

---

## 💡 For you?

External reflection is valuable for **everyone**:

---

## 💡 For you?

External reflection is valuable for **everyone**:

- Want to become senior?

---

## 💡 For you?

External reflection is valuable for **everyone**:

- Want to become senior? → Show your growth

---

## 💡 For you?

External reflection is valuable for **everyone**:

- Want to become senior? → Show your growth
- Struggling to see patterns?

---

## 💡 For you?

External reflection is valuable for **everyone**:

- Want to become senior? → Show your growth
- Struggling to see patterns? → Document behavior

---

## 💡 For you?

External reflection is valuable for **everyone**:

- Want to become senior? → Show your growth
- Struggling to see patterns? → Document behavior
- Want to improve communication?

---

## 💡 For you?

External reflection is valuable for **everyone**:

- Want to become senior? → Show your growth
- Struggling to see patterns? → Document behavior
- Want to improve communication? → Analyze interactions

---

## 💡 For you?

External reflection is valuable for **everyone**:

- Want to become senior? → Show your growth
- Struggling to see patterns? → Document behavior
- Want to improve communication? → Analyze interactions
- Dreading performance reviews?

---

## 💡 For you?

External reflection is valuable for **everyone**:

- Want to become senior? → Show your growth
- Struggling to see patterns? → Document behavior
- Want to improve communication? → Analyze interactions
- Dreading performance reviews? → Concrete examples ready

---

## 🚀 Technical: What does it look like?

Markdown files in Git repository
- IDE as application (Cursor, AntiGravity, IntelliJ)
- AI coach (Claude, GPT, Gemini)
- Personality context (optional)
- Personal profile (optional)
- Fully customizable set of rules

---

## 🎯 Template available

**What's included?**
- Daily journal templates
- Weekly evaluation structure
- AI Coach configuration (personality, role, rules)
- Optional: people, projects, profile context

**Open source & customizable** - make it yours!

---

## ❓ Questions?

---

## 💪 Get started

<div style="display: flex; gap: 2rem;">
<div style="flex: 1;">

### English
![w:300](template-en.png)
<small>https://github.com/TimoBakx/ai-journaling-template</small>

</div>
<div style="flex: 1;">

### Dutch
![w:300](template-nl.png)
<small>https://github.com/TimoBakx/ai-journaling-template-nl</small>

</div>
</div>

---

## 🙏 Thank you!

**Reach out:**
- Mastodon: phpc.social/@timobakx
- Bluesky: @timobakx.bsky.social
- GitHub: github.com/TimoBakx
- Symfony Slack: @TimoBakx
