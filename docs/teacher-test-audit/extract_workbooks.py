"""Read the supplied XLSX files without changing their contents."""
import json
import hashlib
import pathlib
import xml.etree.ElementTree as ET
import zipfile

NS = {'m': 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'}
books = []
for path in sorted((pathlib.Path.home() / 'Downloads').glob('Blackbox_Test_Cases*.xlsx')):
    with zipfile.ZipFile(path) as archive:
        strings = []
        if 'xl/sharedStrings.xml' in archive.namelist():
            strings = [''.join(t.text or '' for t in item.findall('.//m:t', NS))
                       for item in ET.fromstring(archive.read('xl/sharedStrings.xml')).findall('m:si', NS)]
        relationships = {r.attrib['Id']: r.attrib['Target'] for r in
                         ET.fromstring(archive.read('xl/_rels/workbook.xml.rels'))}
        sheets = []
        for sheet in ET.fromstring(archive.read('xl/workbook.xml')).findall('m:sheets/m:sheet', NS):
            target = relationships[sheet.attrib['{http://schemas.openxmlformats.org/officeDocument/2006/relationships}id']]
            target = target.lstrip('/') if target.startswith('/') else 'xl/' + target
            rows = []
            for row in ET.fromstring(archive.read(target)).findall('m:sheetData/m:row', NS):
                cells = {}
                for cell in row.findall('m:c', NS):
                    value = cell.find('m:v', NS)
                    value = value.text if value is not None else ''
                    if cell.attrib.get('t') == 's':
                        value = strings[int(value)] if value else ''
                    elif cell.attrib.get('t') == 'inlineStr':
                        value = ''.join(t.text or '' for t in cell.findall('.//m:t', NS))
                    if value:
                        cells[cell.attrib['r']] = value
                if cells:
                    rows.append(cells)
            sheets.append({'name': sheet.attrib['name'], 'rows': rows})
        books.append({'file': path.name, 'sha256': hashlib.sha256(path.read_bytes()).hexdigest(), 'sheets': sheets})
output = pathlib.Path(__file__).parent / 'workbook-extract.json'
output.write_text(json.dumps(books, ensure_ascii=False, indent=2), encoding='utf-8')
for book in books:
    print(book['file'])
    for sheet in book['sheets']:
        print('SHEET', sheet['name'], 'ROWS', len(sheet['rows']))
        if sheet['rows'] and next(iter(sheet['rows'][0].values())) == 'Test Case ID':
            print('CASES', len(sheet['rows']) - 1)
