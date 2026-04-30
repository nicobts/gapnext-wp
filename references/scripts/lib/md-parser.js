// wp-plugin/scripts/lib/md-parser.js
import fs from 'fs';

/**
 * Parses a bilingual pair of markdown checklist files.
 *
 * @param {string} itPath - path to Italian markdown file
 * @param {string} enPath - path to English markdown file
 * @returns {object} { standardName_it, standardName_en, clauses[] }
 *
 * Each clause:
 * {
 *   reference:      string,  // sequential number e.g. "1", "42" (unique, used as form field name)
 *   clause_ref:     string,  // standard clause reference e.g. "4.1", "7.1.5.2" (for display)
 *   section_it:     string,  // Italian section heading e.g. "Contesto dell'Organizzazione"
 *   section_en:     string,  // English section heading e.g. "Context of the Organization"
 *   title_it:       string,  // Italian question text
 *   title_en:       string,  // English question text
 *   description_it: string,  // always '' (question text IS the title)
 *   description_en: string,
 *   help_text_it:   string,
 *   help_text_en:   string,
 *   level:          number,  // 1 = section header, 2 = question
 * }
 */
export function parseChecklistPair(itPath, enPath) {
  const itContent = fs.readFileSync(itPath, 'utf8');
  const enContent = fs.readFileSync(enPath, 'utf8');

  const itData = parseMarkdown(itContent);
  const enData = parseMarkdown(enContent);

  // Build a map of seq number → english data for fast lookup
  const enMap = new Map();
  for (const q of enData.questions) {
    enMap.set(q.seq, q);
  }

  // Build section name map: section_number → english section title
  const enSectionMap = new Map();
  for (const s of enData.sections) {
    enSectionMap.set(s.num, s.title);
  }

  // Merge: build flat clauses array with level-1 section headers + level-2 questions
  const clauses = [];
  let lastSectionNum = null;

  for (const itQ of itData.questions) {
    // Emit a level-1 section header when section changes
    if (itQ.sectionNum !== lastSectionNum) {
      const itSection = itData.sections.find(s => s.num === itQ.sectionNum);
      const enSectionTitle = enSectionMap.get(itQ.sectionNum) ?? itSection?.title ?? '';
      clauses.push({
        reference:      itQ.sectionNum,
        clause_ref:     itQ.sectionNum,
        section_it:     itSection?.title ?? '',
        section_en:     enSectionTitle,
        title_it:       itSection?.title ?? '',
        title_en:       enSectionTitle,
        description_it: '',
        description_en: '',
        help_text_it:   '',
        help_text_en:   '',
        level:          1,
      });
      lastSectionNum = itQ.sectionNum;
    }

    const enQ = enMap.get(itQ.seq);
    clauses.push({
      reference:      String(itQ.seq),
      clause_ref:     itQ.clauseRef,
      section_it:     itQ.sectionTitle,
      section_en:     enQ?.sectionTitle ?? itQ.sectionTitle,
      title_it:       itQ.text,
      title_en:       enQ?.text ?? itQ.text,
      description_it: '',
      description_en: '',
      help_text_it:   '',
      help_text_en:   '',
      level:          2,
    });
  }

  return {
    standardName_it: itData.standardName,
    standardName_en: enData.standardName,
    clauses,
  };
}

/**
 * Parse a single markdown file into structured data.
 * @param {string} content
 * @returns {{ standardName: string, sections: Array, questions: Array }}
 */
function parseMarkdown(content) {
  const lines = content.split('\n');

  let standardName = '';
  const sections = [];   // { num: '4', title: 'Contesto...' }
  const questions = [];  // { seq: 1, clauseRef: '4.1', text: '...', sectionNum: '4', sectionTitle: '...' }

  let currentSection = null;
  let inTable = false;
  let headerParsed = false; // true once we've seen the table header separator

  // Section heading: ## 4. Title  or  ## Sezione 4: Title  or  ## Section 4: Title
  const sectionPattern = /^##\s+(?:[A-Za-z]+\s+)?(\d+)[.:]\s+(.+)/;
  // Table data row: | 1 | 4.1 | text | | |
  // Columns: seq | clauseRef | text | (response) | (notes)
  const rowPattern = /^\|\s*(\d+)\s*\|\s*([^|]+?)\s*\|\s*([^|]+?)\s*\|/;
  // Separator row: |---|---|---|
  const separatorPattern = /^\|[\s\-|]+\|$/;

  for (const rawLine of lines) {
    const line = rawLine.trim();

    // Standard name from H1
    if (line.startsWith('# ') && !standardName) {
      standardName = line.slice(2).trim();
      continue;
    }

    // Section heading H2
    const sectionMatch = line.match(sectionPattern);
    if (sectionMatch) {
      currentSection = { num: sectionMatch[1], title: sectionMatch[2].trim() };
      sections.push(currentSection);
      inTable = false;
      headerParsed = false;
      continue;
    }

    // Non-numbered H2 (e.g. "## Metodologia") — reset table state, skip
    if (line.startsWith('## ')) {
      currentSection = null;
      inTable = false;
      headerParsed = false;
      continue;
    }

    // Table separator row — marks end of header, start of data
    if (currentSection && separatorPattern.test(line)) {
      inTable = true;
      headerParsed = true;
      continue;
    }

    // Table header row — skip (it's text like "| # | Riferimento | ...")
    if (currentSection && line.startsWith('|') && !headerParsed) {
      continue;
    }

    // Table data row
    if (currentSection && inTable && line.startsWith('|')) {
      const rowMatch = line.match(rowPattern);
      if (rowMatch) {
        const seq = parseInt(rowMatch[1], 10);
        const clauseRef = rowMatch[2].trim();
        const text = rowMatch[3].trim();
        if (!isNaN(seq) && clauseRef && text) {
          questions.push({
            seq,
            clauseRef,
            text,
            sectionNum:   currentSection.num,
            sectionTitle: currentSection.title,
          });
        }
      }
      continue;
    }

    // End of table (empty line or ---)
    if (inTable && (!line || line === '---')) {
      inTable = false;
      headerParsed = false;
    }
  }

  return { standardName, sections, questions };
}
