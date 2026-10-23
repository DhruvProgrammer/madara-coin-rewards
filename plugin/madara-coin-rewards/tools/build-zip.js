/**
 * Packages the plugin into a WordPress-installable ZIP.
 *
 * Run with: node tools/build-zip.js
 *
 * The ZIP specification requires forward slashes as the path separator.
 * PowerShell's Compress-Archive writes backslashes, which some unzip
 * implementations (including WordPress's) mishandle, so the archive is
 * built here with explicit entries.
 */
const fs = require("fs");
const path = require("path");
const { execFileSync } = require("child_process");

const root = path.resolve(__dirname, "..");
const outDir = process.argv[2] || path.join(root, "..", "..", "..", "..");
const outFile = path.join(outDir, "madara-coin-rewards.zip");

// The installable package carries the plugin and its tests. The internal
// review notes and the one-off patchers are development history, not
// something a site should receive.
const SKIP = new Set([".git", "node_modules", "vendor", "docs", "patches"]);

function walk(dir, base = "", out = []) {
	for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
		if (SKIP.has(entry.name)) continue;

		const full = path.join(dir, entry.name);
		const rel = base ? `${base}/${entry.name}` : entry.name;

		if (entry.isDirectory()) {
			walk(full, rel, out);
		} else {
			out.push({ full, rel });
		}
	}

	return out;
}

const files = walk(root);

if (files.length === 0) {
	console.error("FAIL: no files found.");
	process.exit(1);
}

// Build the archive with the `zip` CLI when available, because it writes
// spec-compliant forward-slash entries and directory records.
let built = false;

try {
	execFileSync("tar", ["--version"], { stdio: "ignore" });
} catch (e) {
	// tar is not available either.
}

if (!built) {
	// Fall back to a hand-written ZIP writer: local file headers + central
	// directory, with deflate via zlib.
	const zlib = require("zlib");
	const chunks = [];
	const central = [];
	let offset = 0;

	const crcTable = (() => {
		const table = new Int32Array(256);

		for (let n = 0; n < 256; n++) {
			let c = n;

			for (let k = 0; k < 8; k++) {
				c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
			}

			table[n] = c;
		}

		return table;
	})();

	const crc32 = (buf) => {
		let c = 0 ^ -1;

		for (let i = 0; i < buf.length; i++) {
			c = (c >>> 8) ^ crcTable[(c ^ buf[i]) & 0xff];
		}

		return (c ^ -1) >>> 0;
	};

	for (const file of files) {
		const name = Buffer.from("madara-coin-rewards/" + file.rel, "utf8");
		const data = fs.readFileSync(file.full);
		const deflated = zlib.deflateRawSync(data, { level: 9 });
		const useDeflate = deflated.length < data.length;
		const payload = useDeflate ? deflated : data;
		const method = useDeflate ? 8 : 0;
		const crc = crc32(data);

		const local = Buffer.alloc(30);
		local.writeUInt32LE(0x04034b50, 0);
		local.writeUInt16LE(20, 4);
		local.writeUInt16LE(0x0800, 6); // UTF-8 names.
		local.writeUInt16LE(method, 8);
		local.writeUInt16LE(0, 10); // time.
		local.writeUInt16LE(0x21, 12); // date (1996-01-01).
		local.writeUInt32LE(crc, 14);
		local.writeUInt32LE(payload.length, 18);
		local.writeUInt32LE(data.length, 22);
		local.writeUInt16LE(name.length, 26);
		local.writeUInt16LE(0, 28);

		chunks.push(local, name, payload);

		const centralHeader = Buffer.alloc(46);
		centralHeader.writeUInt32LE(0x02014b50, 0);
		centralHeader.writeUInt16LE(20, 4);
		centralHeader.writeUInt16LE(20, 6);
		centralHeader.writeUInt16LE(0x0800, 8);
		centralHeader.writeUInt16LE(method, 10);
		centralHeader.writeUInt16LE(0, 12);
		centralHeader.writeUInt16LE(0x21, 14);
		centralHeader.writeUInt32LE(crc, 16);
		centralHeader.writeUInt32LE(payload.length, 20);
		centralHeader.writeUInt32LE(data.length, 24);
		centralHeader.writeUInt16LE(name.length, 28);
		centralHeader.writeUInt16LE(0, 30);
		centralHeader.writeUInt16LE(0, 32);
		centralHeader.writeUInt16LE(0, 34);
		centralHeader.writeUInt16LE(0, 36);
		centralHeader.writeUInt32LE(0, 38);
		centralHeader.writeUInt32LE(offset, 42);

		central.push(centralHeader, name);

		offset += local.length + name.length + payload.length;
	}

	const centralBuffer = Buffer.concat(central);
	const end = Buffer.alloc(22);
	end.writeUInt32LE(0x06054b50, 0);
	end.writeUInt16LE(0, 4);
	end.writeUInt16LE(0, 6);
	end.writeUInt16LE(files.length, 8);
	end.writeUInt16LE(files.length, 10);
	end.writeUInt32LE(centralBuffer.length, 12);
	end.writeUInt32LE(offset, 16);
	end.writeUInt16LE(0, 20);

	fs.writeFileSync(outFile, Buffer.concat([...chunks, centralBuffer, end]));
	built = true;
}

const size = fs.statSync(outFile).size;

console.log(
	`Wrote ${path.basename(outFile)} (${files.length} files, ${Math.round(size / 1024)} KB)`
);
console.log(`  ${outFile}`);
