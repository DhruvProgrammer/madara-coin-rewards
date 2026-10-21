/**
 * Assembles the final, self-contained preview document.
 *
 * The filtered CSS is inlined and the template indentation is removed.
 *
 * Run with: node tools/assemble-preview.js <html> <css1> <css2> <out>
 */
const fs = require("fs");

const [, , htmlFile, cssA, cssB, outFile] = process.argv;

if (!htmlFile || !cssA || !cssB || !outFile) {
	console.error("usage: node tools/assemble-preview.js <html> <css1> <css2> <out>");
	process.exit(1);
}

let html = fs.readFileSync(htmlFile, "utf8");
const css = fs.readFileSync(cssA, "utf8") + "\n" + fs.readFileSync(cssB, "utf8");

html = html.replace(/<link rel="stylesheet"[^>]*>/g, "");
html = html.replace("</style>", css + "\n</style>");

// The PHP templates leave their indentation behind.
html = html.replace(/\n[\t ]+/g, "\n");

fs.writeFileSync(outFile, html, "utf8");

console.log("final preview: " + html.length + " characters");
