/**
 * Turns a fetched WordPress page into a self-contained document.
 *
 * Every local stylesheet is downloaded and inlined, so the preview
 * renders with no server and no network.
 *
 * Run with: node tools/build-preview.js <page.html> <out.html> [base-url]
 */
const fs = require("fs");

const [, , inFile, outFile, base] = process.argv;

if (!inFile || !outFile) {
	console.error("usage: node tools/build-preview.js <page.html> <out.html> [base-url]");
	process.exit(1);
}

/**
 * Shrinks a stylesheet without changing what it renders.
 *
 * Comments are dropped and runs of whitespace are collapsed, but only
 * outside a string, so a `content: "a  b"` keeps its two spaces.
 *
 * @param {string} css The stylesheet.
 * @return {string} The smaller stylesheet.
 */
function minifyCss(css) {
	let out = "";
	let i = 0;
	const len = css.length;

	while (i < len) {
		const ch = css[i];

		// A comment.
		if (ch === "/" && css[i + 1] === "*") {
			const end = css.indexOf("*/", i + 2);
			i = end === -1 ? len : end + 2;
			continue;
		}

		// A string.
		if (ch === '"' || ch === "'") {
			const quote = ch;
			out += ch;
			++i;

			while (i < len) {
				out += css[i];

				if (css[i] === "\\") {
					out += css[i + 1] || "";
					i += 2;
					continue;
				}

				if (css[i] === quote) {
					++i;
					break;
				}

				++i;
			}

			continue;
		}

		// Whitespace.
		if (/\s/.test(ch)) {
			let j = i;

			while (j < len && /\s/.test(css[j])) {
				++j;
			}

			// A space is only needed between two word characters.
			const before = out[out.length - 1] || "";
			const after = css[j] || "";

			if (/[a-zA-Z0-9_\-%]/.test(before) && /[a-zA-Z0-9_\-%]/.test(after)) {
				out += " ";
			}

			i = j;
			continue;
		}

		out += ch;
		++i;
	}

	return out;
}

(async () => {
	const root = base || "http://127.0.0.1:8899";
	let html = fs.readFileSync(inFile, "utf8");

const styles = [];
const tagRe = /<link\b[^>]*>/gi;
let match;

while ((match = tagRe.exec(html)) !== null) {
	const tag = match[0];

	if (!/rel=["']stylesheet["']/i.test(tag)) {
		continue;
	}

	const href = (tag.match(/href=["']([^"']+)["']/i) || [])[1];

	if (!href || !href.startsWith(root)) {
		continue;
	}

	styles.push({ tag, href });
}

let inlined = 0;

for (const { tag, href } of styles) {
	let css = "";

	try {
		const response = await fetch(href);
		css = await response.text();
	} catch (e) {
		css = "";
	}

	if (!css) {
		continue;
	}

	html = html.replace(tag, "<style>\n" + minifyCss(css) + "\n</style>");
	inlined++;
}

// The preview must not try to reach the local server for the scripts.
html = html.replace(/<script\b[^>]*src=["'][^"']*wp-content[^"']*["'][^>]*><\/script>/gi, "");
html = html.replace(/<link\b(?![^>]*rel=["']stylesheet["'])[^>]*>/gi, "");

fs.writeFileSync(outFile, html, "utf8");

console.log(
	"inlined " +
		inlined +
		" of " +
		styles.length +
		" stylesheet(s), wrote " +
		html.length +
		" characters to " +
		outFile
);
})();
