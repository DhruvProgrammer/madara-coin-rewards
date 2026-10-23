#!/usr/bin/env node
/**
 * Generates languages/madara-coin-rewards.pot from the plugin source.
 *
 * Run with: node tools/build-pot.js
 *
 * A small, dependency-free extractor. It finds the translatable calls the
 * plugin uses and writes a standard gettext POT file.
 */
const fs = require("fs");
const path = require("path");

const root = path.resolve(__dirname, "..");
const outFile = path.join(root, "languages", "madara-coin-rewards.pot");
const domain = "madara-coin-rewards";

const skipDirs = new Set([".git", "node_modules", "vendor", "tests", "docs", "tools"]);

function walk(dir, out = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (skipDirs.has(entry.name)) continue;
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) walk(full, out);
    else if (entry.isFile() && full.endsWith(".php")) out.push(full);
  }
  return out;
}

// Functions that take a translatable string.
const FUNCTIONS = [
  { name: "__", arg: 0, context: false },
  { name: "_e", arg: 0, context: false },
  { name: "esc_html__", arg: 0, context: false },
  { name: "esc_html_e", arg: 0, context: false },
  { name: "esc_attr__", arg: 0, context: false },
  { name: "esc_attr_e", arg: 0, context: false },
  { name: "_x", arg: 0, context: true },
  { name: "esc_html_x", arg: 0, context: true },
  { name: "esc_attr_x", arg: 0, context: true },
  { name: "_n", arg: 0, context: false, plural: 1 },
];

function unescapePhp(s) {
  return s
    .replace(/\\(['"])/g, "$1")
    .replace(/\\n/g, "\n")
    .replace(/\\t/g, "\t")
    .replace(/\\\\/g, "\\");
}

function extractFile(file) {
  const src = fs.readFileSync(file, "utf8");
  const rel = path.relative(root, file).replace(/\\/g, "/");
  const entries = [];

  for (const fn of FUNCTIONS) {
    const re = new RegExp(
      "\\b" + fn.name + "\\s*\\(\\s*('(?:[^'\\\\]|\\\\.)*')",
      "g"
    );
    let m;
    while ((m = re.exec(src)) !== null) {
      let literal = m[1].slice(1, -1);
      // Skip translator comments for the same call.
      const line = src.slice(0, m.index).split("\n").length;
      const ctxIdx = fn.context ? 1 : 0;
      const domainIdx = fn.context ? 2 : 1;

      // Determine the domain argument.
      const after = src.slice(re.lastIndex);
      const args = [];
      let depth = 0;
      let buf = "";
      for (let i = 0; i < after.length; i++) {
        const ch = after[i];
        if (ch === "(") { depth++; continue; }
        if (ch === ")") {
          if (depth === 0) break;
          depth--;
          continue;
        }
        if (ch === "," && depth === 0) {
          args.push(buf.trim());
          buf = "";
          continue;
        }
        buf += ch;
      }
      args.push(buf.trim());

      let ctx = "";
      let plural = "";
      if (fn.context) {
        const c = args[ctxIdx] || "";
        if (c.startsWith("'")) ctx = unescapePhp(c.slice(1, -1));
      }
      if (fn.plural !== undefined) {
        const p = args[fn.plural] || "";
        if (p.startsWith("'")) plural = unescapePhp(p.slice(1, -1));
      }
      const dom = args[domainIdx] || "";
      const domValue = dom.startsWith("'") ? unescapePhp(dom.slice(1, -1)) : "";
      if (domValue && domValue !== domain) continue;

      // Collect the translator comment above the call.
      const lines = src.split("\n");
      let comment = "";
      for (let i = line - 2; i >= 0 && i >= line - 6; i--) {
        const l = (lines[i] || "").trim();
        if (l.startsWith("* translators:")) {
          comment = l.replace(/^\*\s*translators:\s*/i, "");
        } else if (l.startsWith("/*") || l.startsWith("*") || l === "") {
          continue;
        } else {
          break;
        }
      }

      entries.push({
        msgid: unescapePhp(literal),
        plural,
        context: ctx,
        comment,
        refs: [`${rel}:${line}`],
      });
    }
  }
  return entries;
}

const all = [];
for (const file of walk(root)) {
  all.push(...extractFile(file));
}

// Merge duplicates.
const map = new Map();
for (const e of all) {
  const key = `${e.context}${e.msgid}${e.plural}`;
  if (!map.has(key)) map.set(key, e);
  else map.get(key).refs.push(...e.refs);
}

function esc(s) {
  return s
    .replace(/\\/g, "\\\\")
    .replace(/"/g, '\\"')
    .replace(/\n/g, "\\n")
    .replace(/\t/g, "\\t");
}

let out = `# Copyright (C) 2026 Madara Coin & Rewards
# This file is distributed under the GPL-2.0-or-later license.
msgid ""
msgstr ""
"Project-Id-Version: Madara Coin & Rewards 1.0.0\\n"
"Report-Msgid-Bugs-To: https://example.com/madara-coin-rewards\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"X-Domain: ${domain}\\n"
"Language-Team: LANGUAGE <LL@li.org>\\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\\n"
`;

const sorted = [...map.values()].sort((a, b) =>
  a.msgid.localeCompare(b.msgid) || a.context.localeCompare(b.context)
);

for (const e of sorted) {
  if (e.comment) out += `#. ${e.comment}\n`;
  for (const r of e.refs) out += `#: ${r}\n`;
  if (e.context) out += `msgctxt "${esc(e.context)}"\n`;
  out += `msgid "${esc(e.msgid)}"\n`;
  if (e.plural) {
    out += `msgid_plural "${esc(e.plural)}"\n`;
    out += `msgstr[0] ""\n`;
    out += `msgstr[1] ""\n`;
  } else {
    out += `msgstr ""\n`;
  }
  out += "\n";
}

fs.mkdirSync(path.dirname(outFile), { recursive: true });
fs.writeFileSync(outFile, out, "utf8");

console.log(`Wrote ${path.relative(root, outFile)} with ${sorted.length} strings.`);
