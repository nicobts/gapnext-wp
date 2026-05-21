## ADDED Requirements

### Requirement: Demo banner on results page
The system SHALL display a prominent demo banner on the results page for demo submissions.

#### Scenario: Demo submission results show banner
- **WHEN** the results page loads a submission with `status = 'demo'`
- **THEN** the system SHALL display a banner at the top stating this is a demo report with only N of X total questions evaluated

#### Scenario: Non-demo submissions show no banner
- **WHEN** the results page loads a submission with `status = 'submitted'`
- **THEN** the system SHALL NOT display any demo banner

### Requirement: Upgrade CTA on demo results
The system SHALL display a call-to-action section encouraging the user to request the full audit.

#### Scenario: CTA displayed after score summary
- **WHEN** a demo results page is rendered
- **THEN** the system SHALL display an upgrade CTA section with a message encouraging the visitor to contact the consultant for the full gap analysis, including a contact link if available

### Requirement: Demo results limited to demo questions
The system SHALL only display results for the questions that were part of the demo.

#### Scenario: Results table shows only demo questions
- **WHEN** a demo results page renders the detailed checklist breakdown
- **THEN** the system SHALL display only the questions that were included in the demo submission (based on the answers stored), not the full standard
