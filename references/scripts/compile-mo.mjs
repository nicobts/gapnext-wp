// compile-mo.mjs — compile .po → .mo using gettext-parser
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { po, mo } from 'gettext-parser';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const poPath = path.resolve(__dirname, '../gapnext-wp/languages/gapnext-wp-it_IT.po');
const moPath = path.resolve(__dirname, '../gapnext-wp/languages/gapnext-wp-it_IT.mo');

const poContent = fs.readFileSync(poPath);
const parsed = po.parse(poContent);
const moBuffer = mo.compile(parsed);
fs.writeFileSync(moPath, moBuffer);

console.log(`Compiled: ${moPath}`);
