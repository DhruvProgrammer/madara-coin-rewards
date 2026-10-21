/**
 * Builds the compact preview of the rewards interface.
 *
 * The stylesheets are referenced by ABSOLUTE FILE PATH, which the preview
 * tool inlines. The headless browser cannot reach the local web server.
 *
 * Run with: node tools/build-ui-preview.js <page.html> <out.html>
 */
const fs = require("fs");
const path = require("path");

const [, , inFile, outFile] = process.argv;

if (!inFile || !outFile) {
	console.error("usage: node tools/build-ui-preview.js <page.html> <out.html>");
	process.exit(1);
}

const pluginRoot = path.resolve(__dirname, "..");
const page = fs.readFileSync(inFile, "utf8");

/**
 * Extracts the element that carries the given marker, with its
 * descendants, by counting the tags.
 *
 * @param {string} html   The page.
 * @param {string} marker The marker to find.
 * @return {string} The element, or an empty string.
 */
function extractElement(html, marker) {
	const start = html.indexOf(marker);

	if (start === -1) {
		return "";
	}

	let open = html.lastIndexOf("<div", start);

	while (open !== -1) {
		const close = html.indexOf(">", open);

		if (/^<div\b/.test(html.slice(open, close + 1))) {
			break;
		}

		open = html.lastIndexOf("<div", open - 1);
	}

	if (open === -1) {
		return "";
	}

	const tagRe = /<(\/?)div\b[^>]*>/g;
	let depth = 0;
	let match;
	tagRe.lastIndex = open;

	while ((match = tagRe.exec(html)) !== null) {
		if (match[0].startsWith("</")) {
			depth--;

			if (depth === 0) {
				return html.slice(open, match.index + match[0].length);
			}
		} else {
			depth++;
		}
	}

	return "";
}

let markup = extractElement(page, 'class="mcr-scope mcr-page mcr-page--checkin"');

if (!markup) {
	console.error("FAIL: the plugin's markup was not found in the page.");
	process.exit(1);
}

/*
 * The coin sprite is a large block of SVG that the preview does not need
 * to prove anything, so it is replaced by a simple disc. The real page
 * keeps the real sprite.
 */
markup = markup.replace(
	/<svg class="mcr-coin-sprite"[\s\S]*?<\/svg>/,
	'<svg class="mcr-coin-sprite" width="0" height="0" aria-hidden="true"></svg>'
);

const css = (name) =>
	"file:///" + path.join(pluginRoot, "assets", "css", name).replace(/\\/g, "/");

const doc = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Madara Coin &amp; Rewards - Daily Rewards</title>
<link rel="stylesheet" href="${css("mcr-design-system.css")}" />
<link rel="stylesheet" href="${css("mcr-rewards.css")}" />
<style>
	body { margin: 0; background: #f6f6f6; color: #333; font-family: "Lato", "Segoe UI", sans-serif; }
	.mcr-preview-shell { max-width: 1120px; margin: 0 auto; padding: 30px 22px 56px; }
	.mcr-preview-bar { display: flex; align-items: center; justify-content: space-between; gap: 16px;
		margin-bottom: 20px; padding: 13px 20px; border-radius: 5px; background: #eb3349; color: #fff;
		font-family: "Montserrat", "Segoe UI", sans-serif; font-weight: 700; }
	.mcr-preview-bar a { color: #fff; text-decoration: none; opacity: .9; }
</style>
</head>
<body>
<div class="mcr-preview-shell">
	<div class="mcr-preview-bar"><span>Madara &mdash; Daily Rewards</span><span><a href="#">Home</a></span></div>
	${markup}
</div>
</body>
</html>
`;

fs.writeFileSync(outFile, doc, "utf8");

console.log("wrote " + doc.length + " characters to " + outFile);
