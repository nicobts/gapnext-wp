## ADDED Requirements

### Requirement: One step per section group
The system SHALL render each level-1 clause group as a separate navigable step in the form wizard. Each step SHALL contain only the level-2 questions belonging to that section.

#### Scenario: Standard with 8 sections
- **WHEN** the loaded standard has 8 level-1 clauses (e.g., clauses 4 through 11)
- **THEN** the form SHALL display 8 checklist steps (plus mode selector, company info, and review steps) for a total of 11 steps

#### Scenario: Section step content
- **WHEN** the user navigates to a section step (e.g., "5. Leadership")
- **THEN** the step SHALL display the section heading and only the level-2 questions belonging to that section, with their answer toggles, notes, and evidence upload fields

### Requirement: Step navigation controls
Each section step SHALL have Previous and Next buttons to navigate between steps. The first section step SHALL link back to Company & Contacts. The last section step SHALL link forward to Review & Submit.

#### Scenario: Navigate forward through sections
- **WHEN** the user clicks "Next" on a section step
- **THEN** the system SHALL trigger an autosave and advance to the next section step

#### Scenario: Navigate backward through sections
- **WHEN** the user clicks "Previous" on a section step
- **THEN** the system SHALL navigate to the previous section step without triggering a save

#### Scenario: Last section navigates to review
- **WHEN** the user clicks "Next" on the last section step
- **THEN** the system SHALL advance to the Review & Submit step

### Requirement: Step indicator with progress
The system SHALL display a step indicator showing all form steps. Each checklist section step SHALL display a completion count (answered/total questions in that section).

#### Scenario: Step indicator displays per-section progress
- **WHEN** a section step has 5 questions and the user has answered 3
- **THEN** the step indicator for that section SHALL display "3/5" or equivalent visual progress

#### Scenario: Step indicator highlights active step
- **WHEN** the user is on a specific section step
- **THEN** that step SHALL be visually highlighted as active in the step indicator

#### Scenario: Completed section visual state
- **WHEN** all questions in a section have been answered
- **THEN** the step indicator for that section SHALL show a completed/checkmark state

#### Scenario: Step indicator handles many sections
- **WHEN** a standard has more than 8 sections
- **THEN** the step indicator SHALL remain usable (scrollable or compact layout) without overflowing the viewport

### Requirement: Direct step navigation
The system SHALL allow users to click on any step in the step indicator to jump directly to that step.

#### Scenario: Click to jump to a section
- **WHEN** the user clicks on a section step in the step indicator
- **THEN** the form SHALL navigate directly to that section step

#### Scenario: Jump back to company info
- **WHEN** the user clicks on the Company & Contacts step in the indicator
- **THEN** the form SHALL navigate to the Company & Contacts step

### Requirement: Enhanced autosave with visual feedback
The system SHALL provide clear visual feedback about the draft save state and SHALL trigger saves on step navigation.

#### Scenario: Save on step navigation
- **WHEN** the user clicks Next to advance to another step
- **THEN** the system SHALL trigger an immediate autosave before transitioning

#### Scenario: Saving state displayed
- **WHEN** an autosave request is in progress
- **THEN** the system SHALL display a "Saving..." indicator visible to the user

#### Scenario: Saved state displayed
- **WHEN** an autosave request completes successfully
- **THEN** the system SHALL display "Saved at HH:MM" with the save timestamp

#### Scenario: Save error displayed
- **WHEN** an autosave request fails
- **THEN** the system SHALL display "Save failed" and SHALL retry on the next interval

### Requirement: Draft resume to last active step
The system SHALL remember the last step the user was on when their draft was saved, and restore them to that step when they return.

#### Scenario: Resume to last step
- **WHEN** the user returns to an audit form that has a saved draft with step metadata
- **THEN** the form SHALL skip the mode selector, pre-populate all fields, and navigate to the last active step

#### Scenario: Draft banner on resume
- **WHEN** the form loads with a restored draft
- **THEN** the system SHALL display a dismissible banner indicating the draft was restored and showing the last save time

#### Scenario: No draft exists
- **WHEN** the form loads for an audit with no existing draft
- **THEN** the form SHALL start at the mode selection step (Step 0)
