"""Fill K:M in review copies, preserving the original XLSX package and workbooks."""
import hashlib
import json
import pathlib
import re
import zipfile
from xml.sax.saxutils import escape

ROOT = pathlib.Path(__file__).resolve().parent
BOOKS = json.loads((ROOT / 'workbook-extract.json').read_text(encoding='utf-8'))
RESULTS = {row['id']: row for row in json.loads((ROOT / 'case-results.json').read_text(encoding='utf-8'))}
OUTPUT = ROOT / 'excel-results'
OUTPUT.mkdir(exist_ok=True)
manifest = []
written = set()

def column_number(address):
    value = 0
    for char in re.match('[A-Z]+', address)[0]:
        value = value * 26 + ord(char) - 64
    return value

def xml_text(value):
    value = re.sub(r'\x1b\[[0-?]*[ -/]*[@-~]', '', str(value))
    value = ''.join(char for char in value if ord(char) in (9, 10, 13)
                    or 0x20 <= ord(char) <= 0xD7FF or 0xE000 <= ord(char) <= 0xFFFD
                    or 0x10000 <= ord(char) <= 0x10FFFF)
    return escape(value[:32767])

def fill_row(match, values):
    opening, content, closing = match.groups()
    cells = list(re.finditer(r'<c\b[^>]*(?:/>|>.*?</c>)', content, re.S))
    by_address = {re.search(r'\br="([A-Z]+\d+)"', cell[0])[1]: cell[0] for cell in cells}
    for address, text in values.items():
        previous = by_address.get(address, '')
        style = re.search(r'\bs="[^"]+"', previous)
        style = ' ' + style[0] if style else ''
        by_address[address] = f'<c r="{address}"{style} t="inlineStr"><is><t xml:space="preserve">{xml_text(text)}</t></is></c>'
    prefix = content[:cells[0].start()] if cells else content
    suffix = content[cells[-1].end():] if cells else ''
    return opening + prefix + ''.join(by_address[key] for key in sorted(by_address, key=column_number)) + suffix + closing

for book in BOOKS:
    source = pathlib.Path.home() / 'Downloads' / book['file']
    assert hashlib.sha256(source.read_bytes()).hexdigest() == book['sha256'], f'Original changed: {source.name}'
    # Resolve worksheet paths without rewriting namespaces, drawings, styles or relationships.
    import xml.etree.ElementTree as ET
    ns = {'m': 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'}
    target = OUTPUT / (source.stem + '_Results.xlsx')
    temporary = target.with_suffix('.xlsx.tmp')
    count = 0
    with zipfile.ZipFile(source) as archive:
        relationships = {r.attrib['Id']: r.attrib['Target'] for r in ET.fromstring(archive.read('xl/_rels/workbook.xml.rels'))}
        paths = {}
        for sheet in ET.fromstring(archive.read('xl/workbook.xml')).findall('m:sheets/m:sheet', ns):
            path = relationships[sheet.attrib['{http://schemas.openxmlformats.org/officeDocument/2006/relationships}id']]
            paths[sheet.attrib['name']] = path.lstrip('/') if path.startswith('/') else 'xl/' + path
        replacements = {}
        for sheet in book['sheets']:
            if not sheet['rows'] or next(iter(sheet['rows'][0].values())) != 'Test Case ID':
                continue
            xml = archive.read(paths[sheet['name']]).decode('utf-8')
            for row in sheet['rows'][1:]:
                address, case_id = next(iter(row.items()))
                number = re.search(r'\d+', address)[0]
                case = RESULTS[case_id]
                remarks = f"Evidence: {'; '.join(item['source'] for item in case['evidence']) or 'No completed execution evidence.'}"
                if case['status'] != 'PASS':
                    remarks = f"NOT CERTIFIED. {case['status']}: {case['actual']}\n" + remarks
                values = {f'K{number}': case['actual'], f'L{number}': case['status'], f'M{number}': remarks}
                pattern = rf'(<row\b[^>]*\br="{number}"[^>]*>)(.*?)(</row>)'
                xml, changed = re.subn(pattern, lambda match: fill_row(match, values), xml, flags=re.S)
                assert changed == 1, (case_id, changed)
                written.add(case_id)
                count += 1
            replacements[paths[sheet['name']]] = xml.encode('utf-8')
        with zipfile.ZipFile(temporary, 'w') as output:
            for entry in archive.infolist():
                output.writestr(entry, replacements.get(entry.filename, archive.read(entry.filename)))
    with zipfile.ZipFile(temporary) as check:
        assert check.testzip() is None
        for path in replacements:
            tree = ET.fromstring(check.read(path))
            rows = tree.findall('m:sheetData/m:row', ns)[1:]
            for row in rows:
                number = row.attrib['r']
                statuses = row.findall(f"m:c[@r='L{number}']/m:is/m:t", ns)
                if statuses:
                    assert statuses[0].text in {'PASS', 'FAIL', 'PARTIAL', 'UNVERIFIED'}
    temporary.replace(target)
    assert hashlib.sha256(source.read_bytes()).hexdigest() == book['sha256']
    manifest.append({'original': source.name, 'original_sha256': book['sha256'], 'result': target.name,
                     'result_sha256': hashlib.sha256(target.read_bytes()).hexdigest(), 'cases': count})

assert written == set(RESULTS) and len(written) == 469
(OUTPUT / 'manifest.json').write_text(json.dumps(manifest, indent=2), encoding='utf-8')
print(f'Wrote verified XLSX packages: {len(manifest)} workbooks / {len(written)} result rows; originals unchanged.')
