## ADDED Requirements

### Requirement: Mode selection screen
The system SHALL display a mode selection screen as the first step of the form, before company info. The screen SHALL present two options: "Self-Assessment" (company fills alone) and "Consultant-Assisted" (company with consultant present). Each option SHALL display a brief description of what it means.

#### Scenario: User selects self-assessment mode
- **WHEN** the user clicks the "Self-Assessment" option
- **THEN** the system stores `filling_mode = 'self'` in a hidden form field and advances to the Company & Contacts step

#### Scenario: User selects consultant-assisted mode
- **WHEN** the user clicks the "Consultant-Assisted" option
- **THEN** the system stores `filling_mode = 'assisted'` in a hidden form field and advances to the Company & Contacts step

#### Scenario: Returning to a draft with mode already selected
- **WHEN** the form loads with an existing draft that has a filling mode set
- **THEN** the mode selection step SHALL be skipped and the user SHALL land on the Company & Contacts step (or their last active step)

### Requirement: Conditional consultant fields
The system SHALL show or hide the consultant fieldset on the Company & Contacts step based on the selected filling mode.

#### Scenario: Self-assessment mode hides consultant fields
- **WHEN** filling mode is "self"
- **THEN** the consultant fieldset (name, company, email, phone) SHALL be hidden and consultant fields SHALL NOT be required for form submission

#### Scenario: Consultant-assisted mode shows consultant fields
- **WHEN** filling mode is "assisted"
- **THEN** the consultant fieldset SHALL be visible and consultant name and email SHALL be required for submission

### Requirement: Mode stored with submission
The system SHALL persist the filling mode value in the `gapnext_submissions` table in a `filling_mode` column.

#### Scenario: Self-assessment submission saved
- **WHEN** a submission is saved (draft or final) with self-assessment mode
- **THEN** the `filling_mode` column SHALL contain the value `'self'`

#### Scenario: Consultant-assisted submission saved
- **WHEN** a submission is saved (draft or final) with consultant-assisted mode
- **THEN** the `filling_mode` column SHALL contain the value `'assisted'`

#### Scenario: Existing submissions default to assisted
- **WHEN** the database is migrated and existing submissions have no `filling_mode` value
- **THEN** the column SHALL default to `'assisted'`

### Requirement: Adapted labels per mode
The system SHALL adjust form section labels based on the selected mode.

#### Scenario: Self-assessment label adjustments
- **WHEN** filling mode is "self"
- **THEN** the "Internal Contact" section heading SHALL display as "Your Details" (EN) / "I tuoi dati" (IT) and the submit button context SHALL not reference a consultant

#### Scenario: Consultant-assisted labels unchanged
- **WHEN** filling mode is "assisted"
- **THEN** all labels SHALL remain as they currently are (Internal Contact, Consultant sections)
