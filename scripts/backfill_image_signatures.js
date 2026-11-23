// Local MySQL image signature backfill. Preview by default; pass --write to save.
const { spawnSync } = require('node:child_process');
const path = require('node:path');
const result = spawnSync('php', [path.join(__dirname, 'backfill_image_signatures.php'), ...process.argv.slice(2)], { stdio: 'inherit' });
if (result.error) console.error(result.error.message);
process.exit(result.status ?? 1);