/**
 * Builds the SQLite db.php drop-in from the upstream db.copy.
 *
 * Run with: node tools/build-sqlite-dropin.js
 *
 * The upstream db.copy has two placeholders:
 *
 *   {SQLITE_IMPLEMENTATION_FOLDER_PATH}  the absolute path of the
 *                                        sqlite-database-integration plugin
 *   {SQLITE_PLUGIN}                      the plugin's entry file relative
 *                                        to WP_PLUGIN_DIR
 *
 * Hand-writing the drop-in is a trap: the upstream file loads the
 * implementation (wp-includes/sqlite/db.php), not the plugin bootstrap
 * (load.php). Loading load.php from db.php registers the plugin's
 * deactivation hook before WordPress has initialised $wp_plugin_paths,
 * which is a fatal error in WordPress 7.x.
 */
const fs = require("fs");
const path = require("path");

const wpRoot = process.argv[2];

if (!wpRoot) {
	console.error("usage: node tools/build-sqlite-dropin.js <wordpress-root>");
	process.exit(1);
}

const pluginDir = path.join(
	wpRoot,
	"wp-content",
	"plugins",
	"sqlite-database-integration"
);

const source = path.join(pluginDir, "db.copy");
const target = path.join(wpRoot, "wp-content", "db.php");

if (!fs.existsSync(source)) {
	console.error("FAIL: " + source + " does not exist.");
	process.exit(1);
}

let src = fs.readFileSync(source, "utf8");

src = src.split("{SQLITE_IMPLEMENTATION_FOLDER_PATH}").join(pluginDir);
src = src.split("{SQLITE_PLUGIN}").join("sqlite-database-integration/load.php");

fs.writeFileSync(target, src, "utf8");

console.log("Wrote " + target);
