"""Lists every translatable string used in the app, so lang/pt.json can be checked against it.

    python scripts/extract-strings.py          # print the keys
    python scripts/extract-strings.py --missing  # only keys missing in lang/pt.json
"""
import glob
import json
import re
import sys

PATTERN = re.compile(r"""(?:__|trans_choice)\(\s*(?:'((?:[^'\\]|\\.)*)'|"((?:[^"\\]|\\.)*)")""")

files = glob.glob('app/**/*.php', recursive=True) + glob.glob('resources/views/**/*.blade.php', recursive=True) + glob.glob('database/seeders/*.php')
keys = set()
for path in files:
    text = open(path, encoding='utf-8').read()
    for match in PATTERN.finditer(text):
        key = match.group(1) if match.group(1) is not None else match.group(2)
        keys.add(key.replace("\\'", "'"))

# Keys built dynamically in views
keys.update(['Bank account', 'Card', 'Cash', 'Savings'])

if '--missing' in sys.argv:
    have = json.load(open('lang/pt.json', encoding='utf-8'))
    keys = {k for k in keys if k not in have}

for key in sorted(keys):
    print(key)
