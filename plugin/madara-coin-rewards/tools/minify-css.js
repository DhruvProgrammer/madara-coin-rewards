/**
 * Shrinks a stylesheet without changing what it renders.
 *
 * Comments are dropped and runs of whitespace are collapsed, but only
 * outside a string, so a `content: "a  b"` keeps its two spaces.
 *
 * Run with: node tools/minify-css.js <in.css> <out.css>
 */
const fs = require("fs");

const [, , inFile, outFile] = process.argv;

if (!inFile || !outFile) {
	console.error("usage: node tools/minify-css.js <in.css> <out.css>");
	process.exit(1);
}

/**
 * Minifies a stylesheet.
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

const css = fs.readFileSync(inFile, "utf8");
const min = minifyCss(css);

fs.writeFileSync(outFile, min, "utf8");

console.log(
	inFile + ": " + css.length + " -> " + min.length + " characters"
);
