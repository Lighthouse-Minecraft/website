---
title: 'Managing Application Questions'
visibility: officer
order: 3
summary: 'How to create, edit, and organize the questions applicants see.'
---

## Overview

Application questions are the form fields that applicants fill out when they apply for a staff position. You can customize these questions from the Admin Control Panel to ask exactly what you need to evaluate candidates.

## Who Can Do This

- **Admins**
- **Command Department Officers**

## Where to Find It

1. Go to the [Admin Control Panel]({{url:/acp}})
2. Click the **Application Questions** tab

You'll see a table of all configured questions with their sort order, text, type, category, linked position (if any), and active status.

## Question Types

| Type | What the Applicant Sees |
|---|---|
| **Short Text** | A single-line text input |
| **Long Text** | A multi-line text area |
| **Yes / No** | Two radio buttons (Yes and No) |
| **Dropdown** | A select menu with options you define |

## Question Categories

Categories control **which applicants see which questions**:

| Category | Who Sees It |
|---|---|
| **Core** | Everyone, regardless of what position they're applying for |
| **Officer** | Only applicants applying for an Officer-rank position |
| **Crew Member** | Only applicants applying for a Crew Member-rank position |
| **Position Specific** | Only applicants applying for one specific position (you pick which one) |

An applicant always sees all Core questions, plus the questions that match the rank of the position they're applying for, plus any Position Specific questions tied to that position.

## Creating a Question

1. Click **Add Question** in the top right
2. Fill out the form:
   - **Question Text** -- the question the applicant will see (minimum 5 characters)
   - **Type** -- Short Text, Long Text, Yes/No, or Dropdown
   - **Category** -- Core, Officer, Crew Member, or Position Specific
   - **Position** -- only appears if you choose Position Specific; select which position this question belongs to
   - **Dropdown Options** -- only appears if you choose Dropdown; enter options separated by commas (e.g., "Option 1, Option 2, Option 3")
   - **Sort Order** -- controls where this question appears relative to others (lower numbers appear first)
   - **Active** -- uncheck to hide this question from future applications without deleting it
3. Click **Create Question**

## Editing a Question

1. Click the **pencil icon** next to the question you want to edit
2. Make your changes in the edit modal
3. Click **Save Changes**

Editing a question only affects future applications. Existing applications that already have answers to this question are not changed.

## Deleting a Question

1. Click the **trash icon** next to the question
2. Confirm the deletion

Deleting a question removes it permanently. Answers that already reference this question will show "(Question removed)" in existing applications.

## Important Notes

- **Sort order matters** -- Questions with the same sort order are displayed in the order they were created. Use distinct numbers to guarantee a specific order.
- **Deactivate instead of delete** -- If you might want a question back later, uncheck Active instead of deleting it. Inactive questions don't appear in new applications.
- **All questions are required** -- Applicants must answer every active question before they can submit. There's no way to make individual questions optional.
- The system ships with 13 default questions across Core, Officer, and Crew Member categories. You can edit or deactivate these as needed.
