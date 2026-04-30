// wp-plugin/scripts/lib/php-formatter.js

/**
 * Escapes a string for use inside single-quoted PHP strings.
 */
function esc(str) {
  return (str ?? '')
    .replace(/\r?\n|\r/g, ' ')
    .replace(/\\/g, '\\\\')
    .replace(/'/g, "\\'");
}

/**
 * Renders one clause as a PHP array entry.
 */
function renderClause(c, indent = '        ') {
  return `${indent}[
${indent}    'reference'      => '${esc(c.reference)}',
${indent}    'clause_ref'     => '${esc(c.clause_ref ?? c.reference)}',
${indent}    'section_it'     => '${esc(c.section_it)}',
${indent}    'section_en'     => '${esc(c.section_en)}',
${indent}    'title_it'       => '${esc(c.title_it)}',
${indent}    'title_en'       => '${esc(c.title_en)}',
${indent}    'description_it' => '${esc(c.description_it)}',
${indent}    'description_en' => '${esc(c.description_en)}',
${indent}    'help_text_it'   => '${esc(c.help_text_it)}',
${indent}    'help_text_en'   => '${esc(c.help_text_en)}',
${indent}    'level'          => ${parseInt(c.level, 10) || 1},
${indent}],`;
}

/**
 * Generates the full PHP file content for a standard.
 * @param {object} standard - { id, name_it, name_en, clauses[] }
 * @returns {string}
 */
export function generatePhpFile(standard) {
  const clausesPhp = standard.clauses.map(c => renderClause(c)).join('\n');

  return `<?php
/**
 * GapNext WP — Standard Data
 * Standard: ${standard.name_en} / ${standard.name_it}
 * Generated: ${new Date().toISOString()}
 * DO NOT EDIT MANUALLY — regenerate with scripts/extract-checklists.js
 */

if ( ! defined( 'ABSPATH' ) ) exit;

return [
    'id'      => '${esc(standard.id)}',
    'name_it' => '${esc(standard.name_it)}',
    'name_en' => '${esc(standard.name_en)}',
    'clauses' => [
${clausesPhp}
    ],
];
`;
}
