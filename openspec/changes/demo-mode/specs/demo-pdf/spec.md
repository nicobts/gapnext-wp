## ADDED Requirements

### Requirement: Demo PDF watermark
The system SHALL add a diagonal "GapNext DEMO" watermark on every page of PDF reports generated from demo submissions.

#### Scenario: Watermark on every page
- **WHEN** a PDF is generated for a submission with `status = 'demo'`
- **THEN** every page of the PDF SHALL display a semi-transparent diagonal "GapNext DEMO" watermark text

#### Scenario: No watermark on regular PDFs
- **WHEN** a PDF is generated for a submission with `status = 'submitted'`
- **THEN** the PDF SHALL NOT contain any watermark

### Requirement: Demo PDF limited to demo questions
The system SHALL only include demo questions in the detailed checklist section of demo PDFs.

#### Scenario: Checklist section shows only demo questions
- **WHEN** a demo PDF is generated
- **THEN** the detailed checklist section SHALL only list questions that have answers in the demo submission, not the full standard

### Requirement: Demo PDF cover page indicates demo
The system SHALL indicate "DEMO" on the PDF cover page.

#### Scenario: Cover page shows demo label
- **WHEN** a demo PDF cover page is rendered
- **THEN** the cover page SHALL display "DEMO" prominently below the report title
