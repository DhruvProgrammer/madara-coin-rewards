/**
 * Removes duplicate function declarations across the test stub files.
 *
 * Run with: node tools/dedupe-all-stubs.js
 *
 * bootstrap.php and wp-stubs.php both grew to provide WordPress functions.
 * When a function is declared in both, PHP fatals with "Cannot redeclare".
 * The first declaration wins; the later one is removed.
 */
const fs = require("fs");
const path = require("path");

const dir = path.resolve(__dirname, "..", "tests");
const files = ["bootstrap.php", "wp-stubs.php", "admin-test.php", "frontend-test.php"];

const declared = new Map(); // name -> file that owns it.
const removed = [];

/**
 * Removes every declaration of a function EXCEPT the first.
 *
 * @param {string} src  Source.
 * @param {string} name Function name.
 * @returns {string} The source without the duplicate declarations.
 */
function stripFunction(src, name) {
	const eol = src.includes("\r\n") ? "\r\n" : "\n";
	const lines = src.split(/\r?\n/);
	const out = [];
	let skipping = false;
	let seen = false;

	for (let i = 0; i < lines.length; i++) {
		const line = lines[i];

		if (!skipping && new RegExp(`^function\\s+${name}\\s*\\(`).test(line)) {
			if (!seen) {
				// The first declaration is kept.
				seen = true;
				out.push(line);
				continue;
			}

			while (out.length && /^\s*\/\*\*/.test(out[out.length - 1])) {
				out.pop();
			}

			skipping = true;
			continue;
		}

		if (skipping) {
			if (/^\}/.test(line)) {
				skipping = false;
			}
			continue;
		}

		out.push(line);
	}

	return out.join(eol);
}

for (const file of files) {
	const full = path.join(dir, file);

	if (!fs.existsSync(full)) continue;

	let src = fs.readFileSync(full, "utf8");
	const re = /^function\s+(\w+)\s*\(/gm;
	let m;

	// Collect this file's declarations.
	const names = [];
	while ((m = re.exec(src)) !== null) {
		names.push(m[1]);
	}

	for (const name of names) {
		if (declared.has(name)) {
			src = stripFunction(src, name);
			removed.push(name + " (from " + file + ", already in " + declared.get(name) + ")");
		} else {
			declared.set(name, file);
		}
	}

	fs.writeFileSync(full, src, "utf8");
}

console.log(
	removed.length
		? "Removed " + removed.length + " duplicate declaration(s):\n  " + removed.join("\n  ")
		: "No duplicate declarations found."
);
