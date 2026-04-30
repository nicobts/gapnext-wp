// wp-plugin/scripts/extract-checklists.js
import path from 'path';
import fs from 'fs';
import { fileURLToPath } from 'url';
import { parseChecklistPair } from './lib/md-parser.js';
import { generatePhpFile } from './lib/php-formatter.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const MD_DIR = path.resolve(
  __dirname,
  '../../resources/materiale per webapp-20260209T000714Z-1-001/materiale per webapp/checklists/md'
);

const OUTPUT_DIR = path.resolve(
  __dirname,
  '../gapnext-wp/includes/data'
);

// Map each standard to its IT and EN markdown filenames
const STANDARDS = [
  {
    id:      'en-9100-2018',
    name_it: 'EN 9100:2018',
    name_en: 'EN 9100:2018',
    it_file: 'checklist_EN9100_2018 IT.md',
    en_file: 'checklist_EN9100_2018_EN.md',
  },
  {
    id:      'iso-9001-2015',
    name_it: 'ISO 9001:2015',
    name_en: 'ISO 9001:2015',
    it_file: 'Checklist_ISO_9001_2015_IT.md',
    en_file: 'Checklist_ISO_9001_2015_EN.md',
  },
  {
    id:      'iso-13485-2016',
    name_it: 'ISO 13485:2016',
    name_en: 'ISO 13485:2016',
    it_file: 'Checklist_ISO_13485_2016_IT.md',
    en_file: 'Checklist_ISO_13485_2016_EN.md',
  },
  {
    id:      'iso-27001-2022',
    name_it: 'ISO 27001:2022',
    name_en: 'ISO 27001:2022',
    it_file: 'Checklist_ISO_27001_2022_IT.md',
    en_file: 'Checklist_ISO_27001_2022_EN.md',
  },
  {
    id:      'iso-45001-2018',
    name_it: 'ISO 45001:2018',
    name_en: 'ISO 45001:2018',
    it_file: 'Checklist_ISO_45001_2018_IT.md',
    en_file: 'Checklist_ISO_45001_2018_EN.md',
  },
  {
    id:      'iso-42001-2023',
    name_it: 'ISO 42001:2023',
    name_en: 'ISO 42001:2023',
    it_file: 'Checklist_ISO_42001_2023_IT.md',
    en_file: 'Checklist_ISO_42001_2023_EN.md',
  },
  {
    id:      'iso-22163-2023',
    name_it: 'ISO 22163:2023',
    name_en: 'ISO 22163:2023',
    it_file: 'Checklist_ISO_22163_2023_IT.md',
    en_file: 'Checklist_ISO_22163_2023_EN.md',
  },
  {
    id:      'iatf-16949-2016',
    name_it: 'IATF 16949:2016',
    name_en: 'IATF 16949:2016',
    it_file: 'Checklist_IATF_16949_2016_IT.md',
    en_file: 'Checklist_IATF_16949_2016_EN.md',
  },
];

function processStandard(standard) {
  const itPath = path.join(MD_DIR, standard.it_file);
  const enPath = path.join(MD_DIR, standard.en_file);

  // Skip if either file doesn't exist yet
  if (!fs.existsSync(itPath) || !fs.existsSync(enPath)) {
    console.log(`[${standard.id}] Skipping — markdown files not found`);
    return;
  }

  console.log(`[${standard.id}] Parsing markdown files...`);
  const parsed = parseChecklistPair(itPath, enPath);

  const phpContent = generatePhpFile({
    id:      standard.id,
    name_it: parsed.standardName_it || standard.name_it,
    name_en: parsed.standardName_en || standard.name_en,
    clauses: parsed.clauses,
  });

  const outputPath = path.join(OUTPUT_DIR, `${standard.id}.php`);
  fs.writeFileSync(outputPath, phpContent, 'utf8');
  console.log(`[${standard.id}] Written: ${parsed.clauses.length} clauses → ${outputPath}`);
}

function main() {
  fs.mkdirSync(OUTPUT_DIR, { recursive: true });

  for (const standard of STANDARDS) {
    try {
      processStandard(standard);
    } catch (err) {
      console.error(`[${standard.id}] FAILED: ${err.message}`);
    }
  }

  console.log('\nDone.');
}

main();
