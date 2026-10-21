/**
 * Keeps only the CSS rules a page actually uses.
 *
 * The plugin's design system is a large stylesheet that covers every
 * page. A preview of one page does not need all of it, and the preview
 * tool has a size limit, so the rules whose selectors match nothing in
 * the markup are dropped.
 *
 * Run with: node tools/filter-css.js <page.html> <in.css> <out.css>
 */
const fs = require("fs");

const [, , pageFile, inFile, outFile] = process.argv;

if (!pageFile || !inFile || !outFile) {
	console.error("usage: node tools/filter-css.js <page.html> <in.css> <out.css>");
	process.exit(1);
}

const page = fs.readFileSync(pageFile, "utf8");
const css = fs.readFileSync(inFile, "utf8");

// The class names the page uses.
const used = new Set();

for (const match of page.matchAll(/class="([^"]*)"/g)) {
	for (const name of match[1].split(/\s+/)) {
		if (name) {
			used.add(name);
		}
	}
}

for (const match of page.matchAll(/data-mcr-[a-z-]+/g)) {
	used.add(match[0]);
}

/**
 * Whether a selector can match something on the page.
 *
 * @param {string} selector The selector.
 * @return {boolean} True when it may match.
 */
function selectorMatches(selector) {
	const parts = selector.split(",").map((s) => s.trim());

	for (const part of parts) {
		// A pseudo-element or an attribute selector on its own.
		const bare = part.replace(/::?[a-z-]+(\([^)]*\))?/g, "").trim();

		if (!bare) {
			continue;
		}

		// Every class in the selector has to be one the page uses.
		const classes = bare.match(/\.[A-Za-z_][A-Za-z0-9_-]*/g) || [];

		if (!classes.length) {
			// An element selector: keep it, it is cheap.
			return true;
		}

		if (classes.some((c) => used.has(c.slice(1)))) {
			return true;
		}
	}

	return false;
}

/**
 * Filters a block of CSS.
 *
 * @param {string} block    The CSS.
 * @param {number} depth    The nesting depth.
 * @return {string} The kept CSS.
 */
function filter(block, depth) {
	let out = "";
	let i = 0;
	const len = block.length;

	while (i < len) {
		// A comment.
		if (block[i] === "/" && block[i + 1] === "*") {
			const end = block.indexOf("*/", i + 2);
			i = end === -1 ? len : end + 2;
			continue;
		}

		// A media query.
		if (block[i] === "@") {
			const open = block.indexOf("{", i);
			const prelude = block.slice(i, open).trim();

			if (open === -1) {
				break;
			}

			// Find the matching closing brace.
			let depthBrace = 1;
			let j = open + 1;

			while (j < len && depthBrace > 0) {
				if (block[j] === "{") {
					depthBrace++;
				} else if (block[j] === "}") {
					depthBrace--;
				}

				++j;
			}

			const inner = block.slice(open + 1, j - 1);

			if (prelude.startsWith("@media") || prelude.startsWith("@supports")) {
				const kept = filter(inner, depth + 1);

				if (kept.trim()) {
					out += prelude + "{" + kept + "}";
				}
			} else {
				out += prelude + "{" + inner + "}";
			}

			i = j;
			continue;
		}

		// A rule.
		const open = block.indexOf("{", i);

		if (open === -1) {
			break;
		}

		let depthBrace = 1;
		let j = open + 1;

		while (j < len && depthBrace > 0) {
			if (block[j] === "{") {
				depthBrace++;
			} else if (block[j] === "}") {
				depthBrace--;
			}

			++j;
		}

		const selector = block.slice(i, open).trim();
		const body = block.slice(open + 1, j - 1);

		if (selectorMatches(selector)) {
			out += selector + "{" + body + "}";
		}

		i = j;
	}

	return out;
}

const kept = filter(css, 0);

fs.writeFileSync(outFile, kept, "utf8");

console.log(
	inFile + ": " + css.length + " -> " + kept.length + " characters ("
		+ used.size +
		" classes in use)"
);
