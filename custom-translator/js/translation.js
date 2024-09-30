const languageOptions = [
  ['🇭🇰 中文', 'zhHK'],
  ['🇬🇧 English', 'en'],
  ['🇯🇵 日本語', 'jp']
];

// Get the current URL and extract the language code
const currentUrl = new URL(window.location.href);
let languageCode = currentUrl.pathname.split('/')[1]; // Extract language code from path

// Set default language to 'en' if not provided
if (!languageOptions.some(option => option[1] === languageCode)) {
  languageCode = 'en';
}

// Update the URL to include the language code in the path
const url = new URL(window.location.href);
url.pathname = '/' + languageCode + url.pathname.substring(3); // Remove the old language code and set the new one


// Function to load and apply translations
function applyTranslations() {
  replaceLanguageSwitch("language-switcher");
  // Fetch translations for the current language
  fetch(`/wp-content/plugins/custom-translator/translation_file/${languageCode}.json`)
    .then(response => response.json())
    .then(translations => {
      const hreflangLink = document.createElement('link');
      hreflangLink.rel = 'alternate';
      hreflangLink.hreflang = languageCode;
      hreflangLink.href = url.toString();
      document.head.appendChild(hreflangLink);
      
      // Translate all text elements on the page
      document.body.childNodes.forEach(node => {
        translateNode(node, translations);
      });
      //document.body.classList.add('translations-complete'); 
	  document.body.classList.add('loaded');
    })
    .catch(error => {
      console.error('Error loading default translation file:', error);
      // Fallback to default language (en)
//       const fallbackUrl = new URL(window.location.href);
//       fallbackUrl.pathname = '/en' + fallbackUrl.pathname.substring(3);
//       window.location.replace(fallbackUrl.toString());
    });
}

function translateNode(node, translations) {
  if (node.nodeType != Node.TEXT_NODE && node.nodeName != 'INPUT') {
    node.childNodes.forEach(childNode => {
      translateNode(childNode, translations);
    });
    return;
  }

  let originalText = node.textContent.split(';')[0].trim();
  if (node.nodeName == 'INPUT') {
    originalText = node.value;
  }

  if (translations[originalText] !== undefined) {
    let translatedText = translations[originalText];
    const variables = originalText.match(/\$\d+/g);
    if (variables) {
      const temp = node.textContent.split(';');
      variables.forEach((variable, index) => {
        const value = temp[index + 1].trim();
        translatedText = translatedText.replace(variable, value);
      });
    }
    
    if (node.nodeName != 'INPUT') {
      const translatedNode = document.createElement('span');
      translatedNode.textContent = translatedText;
      node.parentNode.replaceChild(translatedNode, node);
    } else {
      node.value = translatedText;
    }
  }
}

// Call the applyTranslations function when the page is loaded
window.addEventListener('load', applyTranslations);

function replaceLanguageSwitch(sclassName) {
    const kentaHeaderButton = document.querySelectorAll("." + sclassName);
    let nowText = "";

    for (let i = 0; i < languageOptions.length; i++) {
        if (languageOptions[i][1] === languageCode) {
            nowText = languageOptions[i][0];
            languageOptions.splice(i, 1);
            break;
        }
    }

    if (kentaHeaderButton.length > 0) {
        for (let i = 0; i < kentaHeaderButton.length; i++) {
            const bootstrapDropdown = `<div class="dropdown">
                <a class="dropdown-toggle" type="button" id="LanguageDropdownMenuButton${i}" data-bs-toggle="dropdown" aria-expanded="false">
                    ${nowText} 
                </a>
                <ul class="dropdown-menu LanguageDropdown-menu" aria-labelledby="LanguageDropdownMenuButton${i}">
                </ul>
            </div>`;
            kentaHeaderButton[i].outerHTML = bootstrapDropdown;
        }

        const dropdownMenu = document.querySelectorAll('.LanguageDropdown-menu');
        for (let i = 0; i < dropdownMenu.length; i++) {
            languageOptions.forEach(([name, langCode]) => {
                const listItem = document.createElement('li');
                const link = document.createElement('a');
                link.classList.add('dropdown-item');
                link.href = `/${langCode}/`; // Use path-based URLs
                link.textContent = name;
                listItem.appendChild(link);
                dropdownMenu[i].appendChild(listItem);
            });
        }

        // Add event listener to toggle the dropdown
        const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
        dropdownToggles.forEach((toggle, index) => {
            toggle.addEventListener('click', function() {
                const menu = dropdownMenu[index];
                menu.classList.toggle('show'); // Toggle the 'show' class
            });
        });

        // Close the dropdown if clicked outside
        window.addEventListener('click', function(event) {
            dropdownToggles.forEach((toggle, index) => {
                const menu = dropdownMenu[index];
                if (!toggle.contains(event.target) && !menu.contains(event.target)) {
                    menu.classList.remove('show'); // Hide the dropdown
                }
            });
        });
    }
}