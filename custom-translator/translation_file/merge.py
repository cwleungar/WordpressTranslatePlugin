import json

data={}
with open('en.json',encoding="utf-8") as f:
    data['en'] = json.load(f)
with open('zh.json',encoding="utf-8") as f:
    data['zh'] = json.load(f)
with open('jp.json',encoding="utf-8") as f:
    data['jp'] = json.load(f)

for key in data['en']:
    if not key in data['zh']:
        data['zh'][key] = data['en'][key]
    if not key in data['jp']:
        data['jp'][key] = data['en'][key]

with open('zh.json', 'w') as f:
    json.dump(data['zh'], f)
with open('jp.json', 'w') as f:
    json.dump(data['jp'], f)
