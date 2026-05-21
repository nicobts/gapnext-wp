## ADDED Requirements

### Requirement: Limit questions for demo audits
The system SHALL render only the first N level-2 questions of the standard when serving a demo audit, where N is the configured demo question limit.

#### Scenario: Demo audit shows limited questions
- **WHEN** a user opens a checklist page for an audit with `access_mode = 'demo'`
- **THEN** the system SHALL display only the first N level-2 questions (where N = `gapnext_demo_question_limit` option, default 15) along with their parent level-1 section headers

#### Scenario: Level-1 headers without included questions are excluded
- **WHEN** the Nth question falls before a section boundary
- **THEN** the system SHALL NOT render any level-1 section headers that have zero included level-2 questions

#### Scenario: Section metadata reflects demo slice
- **WHEN** the form JS receives `sections_meta` for a demo audit
- **THEN** the metadata SHALL only contain sections that have at least one included question, with accurate question counts

### Requirement: Force self-assessment mode for demo audits
The system SHALL automatically set `filling_mode = 'self'` for demo audits and skip the mode selector step.

#### Scenario: Demo audit skips mode selector
- **WHEN** a user opens a demo audit checklist
- **THEN** the system SHALL hide the mode selector (step 0), set `filling_mode` to `'self'`, hide consultant fields, and start directly at the company info step

### Requirement: Demo banner on checklist form
The system SHALL display a visible "Demo" banner on the checklist form when serving a demo audit.

#### Scenario: Demo banner displayed
- **WHEN** a user views the demo checklist form
- **THEN** the system SHALL display a banner indicating this is a demo version with limited questions, and the total number of questions in the full standard

### Requirement: Demo submission status
The system SHALL store demo form submissions with `status = 'demo'` instead of `'submitted'`.

#### Scenario: Demo form submitted
- **WHEN** a user submits a demo checklist form
- **THEN** the system SHALL save the submission with `status = 'demo'` and `filling_mode = 'self'`

#### Scenario: Demo draft saved
- **WHEN** a demo checklist form is autosaved
- **THEN** the system SHALL save with `status = 'draft'` as normal (draft behavior is unchanged)
