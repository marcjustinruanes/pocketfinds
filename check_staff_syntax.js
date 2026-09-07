const fs = require('fs');
const content = fs.readFileSync('resources/views/auth/register-logistics-staff.blade.php', 'utf8');
const scripts = content.match(/<script[\s\S]*?<\/script>/gi) || [];
let hasError = false;
scripts.forEach((s, idx) => {
    if (s.includes('application/json') || s.includes('src=')) return;
    let code = s.replace(/<script[^>]*>/i, '').replace(/<\/script>/i, '');
    code = code.replace(/@json\([^)]*\)/g, '{}');
    code = code.replace(/\{\{[^}]*\}\}/g, '""');
    code = code.replace(/\{!![^!]*!!\}/g, '""');
    try {
        new Function(code);
        console.log('Script tag ' + idx + ' passed syntax validation.');
    } catch (e) {
        hasError = true;
        console.error('Script tag ' + idx + ' syntax error:', e.message);
    }
});
if (!hasError) console.log('ALL EMBEDDED JAVASCRIPT VALIDATED CLEANLY!');

