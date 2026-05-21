## ADDED Requirements

### Requirement: Demo access mode in audit creation
The system SHALL allow admins to select "Demo" as an access mode when creating a new audit link.

#### Scenario: Demo option in access mode dropdown
- **WHEN** an admin creates a new audit link
- **THEN** the access mode dropdown SHALL include a "Demo" option alongside "Public" and "Login Required"

#### Scenario: Demo audit created successfully
- **WHEN** an admin selects "Demo" access mode and creates the audit
- **THEN** the system SHALL save the audit with `access_mode = 'demo'` and the audit link SHALL serve the demo experience

### Requirement: Demo question limit setting
The system SHALL provide an admin setting to configure how many questions appear in demo audits.

#### Scenario: Default demo question limit
- **WHEN** the plugin is activated for the first time
- **THEN** the `gapnext_demo_question_limit` option SHALL default to `15`

#### Scenario: Admin changes demo question limit
- **WHEN** the admin sets the demo question limit to 20 and saves settings
- **THEN** all new demo audit page loads SHALL show 20 questions

### Requirement: Demo badge in submissions list
The system SHALL display a visual "Demo" badge next to demo submissions in the admin submissions table.

#### Scenario: Demo submission shows badge
- **WHEN** the admin views the submissions list
- **THEN** submissions with `status = 'demo'` SHALL display a "Demo" badge in the status column, visually distinct from "Submitted" and "Draft"

### Requirement: Demo access mode in audit settings dropdown
The system SHALL include "Demo" as an option in the default access mode setting.

#### Scenario: Default access mode includes demo
- **WHEN** an admin views the settings page
- **THEN** the Default Access Mode dropdown SHALL include "Demo" as an option
