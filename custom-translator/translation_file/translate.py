from concurrent.futures import ThreadPoolExecutor
from googletrans import Translator
import json
def is_traditional_chinese(text):
    # Implement your check for traditional Chinese characters here
    pass

def translate_value(key_value_pair):
    key, value = key_value_pair
    if isinstance(value, str) and not is_traditional_chinese(value):
        translator = Translator()
        translated_value = translator.translate(value, dest='zh-TW').text
        return key, translated_value
    return key, value

def translate_values_to_traditional_chinese(data):
    with ThreadPoolExecutor() as executor:
        # Create a list of key-value pairs for translation
        items_to_translate = [(key, value) for key, value in data.items() if value]

        # Use map to execute translations in parallel
        translated_items = executor.map(translate_value, items_to_translate)

        # Update the original dictionary with translated values
        for key, value in translated_items:
            data[key] = value


def main():
    # Load JSON data from a file
    with open('zh.json', 'r', encoding='utf-8') as f:
        data = json.load(f)
    
    # Translate values
    translate_values_to_traditional_chinese(data)

    # Save the translated data back to the JSON file
    with open('translated_data.json', 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=4)

if __name__ == "__main__":
    main()