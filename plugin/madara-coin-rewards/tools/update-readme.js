/**
 * Updates the testing section of the README.
 *
 * Run with: node tools/update-readme.js
 */
const fs = require("fs");
const path = require("path");

const file = path.resolve(__dirname, "..", "README.md");

let src = fs.readFileSync(file, "utf8");

const oldBlock = [
	"php tests/run-tests.php          # 105 unit assertions",
	"php tests/run-ledger-tests.php   #  47 ledger / idempotency assertions",
	"php tests/boot-test.php          #  16 boot + activation assertions",
	"php tests/audit-ux.php           #  23 accessibility & UX assertions",
	"php tests/parse-check.php        # parses every PHP file, checks for BOMs",
	"php tests/lint-plugin.php        # unprepared SQL, raw echoes, missing guards",
	"php tests/check-consistency.php  # class/method/hook/schema cross-references",
	"php tests/check-global-classes.php # namespace resolution of global classes",
].join("\n");

const newBlock = [
	"php tests/run-tests.php          # 105 unit assertions",
	"php tests/run-ledger-tests.php   #  54 ledger / idempotency assertions",
	"php tests/boot-test.php          #  16 boot assertions",
	"php tests/activation-test.php    #  11 activation-order assertions",
	"php tests/admin-test.php         #  11 admin screen assertions",
	"php tests/frontend-test.php      #  13 shortcode rendering assertions",
	"php tests/audit-ux.php           #  23 accessibility & UX assertions",
	"php tests/parse-check.php        # parses every PHP file, checks for BOMs",
	"php tests/lint-plugin.php        # unprepared SQL, raw echoes, missing guards,",
	"                                 #   interpolated format strings",
	"php tests/check-consistency.php  # class/method/hook/schema cross-references",
	"php tests/check-global-classes.php # namespace resolution of global classes",
].join("\n");

if (!src.includes(oldBlock)) {
	console.error("FAIL: the test list was not found.");
	process.exit(1);
}

src = src.replace(oldBlock, newBlock);

const oldNote = [
	"All eight exit `0` on a healthy tree. The boot test is the one that",
	"catches the class of bug the others cannot: it loads the plugin the way",
	"WordPress does and asserts that it comes up without a fatal error.",
].join("\n");

const newNote = [
	"All eleven exit `0` on a healthy tree.",
	"",
	"The three smoke tests matter most, because they reproduce the",
	"**order in which WordPress actually runs things** - and that order is",
	"different from the order the plugin's own code implies:",
	"",
	"| Test | Reproduces |",
	"|---|---|",
	"| `boot-test.php` | A normal request: the plugin file is included, then `plugins_loaded` fires. |",
	"| `activation-test.php` | Activation: `plugins_loaded` has **already** fired before the plugin file is included, so a class autoloaded only from `plugins_loaded` cannot be resolved when the activation hook fires. |",
	"| `admin-test.php` | An admin request: every admin screen is rendered, which is where a missing method shows up as a fatal. |",
	"| `frontend-test.php` | A frontend request: every shortcode is rendered, which is where a broken template shows up as a fatal. |",
].join("\n");

if (!src.includes(oldNote)) {
	console.error("FAIL: the closing note was not found.");
	process.exit(1);
}

src = src.replace(oldNote, newNote);

fs.writeFileSync(file, src, "utf8");
console.log("Updated the testing section of README.md");
