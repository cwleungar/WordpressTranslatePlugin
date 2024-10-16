const languageOptions = [
  ['🇭🇰 中文', 'zhHK'],
  ['🇬🇧 English', 'en'],
  ['🇯🇵 日本語', 'jp']
];

const hrefLangCode = {
  "en": ["en","x-default"],
  "zhHK": ["zh-HK", "zh-TW", "zh-Hant"],
  "jp":"jp"
};

// Get the current URL and extract the language code
const currentUrl = new URL(window.location.href);
let languageCode = currentUrl.pathname.split('/')[1]; // Extract language code from path

// Set default language to 'en' if not provided
if (!languageOptions.some(option => option[1] === languageCode)) {
  languageCode = 'en';
}

// Update the URL to include the language code in the path
// Remove the old language code and set the new one
undefinedTranslation={};
// Function to load and apply translations
function applyTranslations() {
	console.log("started Translation");
  replaceLanguageSwitch("language-switcher");
  
  const canonicalUrl = new URL(window.location.href);
  const pathSegments = canonicalUrl.pathname.split('/');
  
  // Remove the language code segment
  if (pathSegments.length > 1) {
    pathSegments[1] = ''; // Clear the language code
  }
  
  canonicalUrl.pathname = pathSegments.join('/').replace(/\/{2,}/g, '/'); // Clean up any double slashes

  const canonicalLink = document.createElement('link');
  canonicalLink.rel = 'canonical';
  canonicalLink.href = canonicalUrl.toString();
  document.head.appendChild(canonicalLink);
  
  // Create alternate links based on hrefLangCode
  Object.entries(hrefLangCode).forEach(([lang, codes]) => {
    const langCodes = Array.isArray(codes) ? codes : [codes]; // Ensure it's an array
    langCodes.forEach(code => {
      const alternateLink = document.createElement('link');
      alternateLink.rel = 'alternate';
      alternateLink.hreflang = code;
      alternateLink.href = lang!="en"?`/${lang}/`:"/";
      document.head.appendChild(alternateLink);
    });
  });

  // Fetch translations for the current language
  fetch(`/wp-content/plugins/custom-translator/translation_file/${languageCode}.json`)
    .then(response => response.json())
    .then(translations => {
      
      // Translate all text elements on the page
      document.body.childNodes.forEach(node => {
        translateNode(node, translations);
      });
	  

	  if(translations[document.title] !== undefined){
		  document.title =translations[document.title]
	  }else{
		  undefinedTranslation[document.title]=document.title;
	  }
		 
      

      let metaDescription = document.querySelector('meta[name="description"]');
      if (!metaDescription) {
        metaDescription = document.createElement('meta');
        metaDescription.name = 'description';
        document.head.appendChild(metaDescription);
      }
	  if(translations[metaDescription.content] !== undefined){
		  metaDescription.content=translations[metaDescription.content];
	  }else{
		  undefinedTranslation[metaDescription.content]=metaDescription.content;
	  }
	  console.log("undefined Translation",undefinedTranslation);
	  
      document.body.classList.add('loaded');
    })
    .catch(error => {
      console.error('Error loading translation file:', error);
    });
}

function translateNode(node, translations) {
  if (node.nodeType !== Node.TEXT_NODE && node.nodeName !== 'INPUT') {
    node.childNodes.forEach(childNode => {
      translateNode(childNode, translations);
    });
    return;
  }

  let originalText = node.nodeType === Node.TEXT_NODE ? node.textContent.split(';')[0].trim() : node.value;

  if (translations[originalText] !== undefined) {
    let translatedText = translations[originalText];
    const variables = originalText.match(/\$\d+/g);
    if (variables) {
      const temp = originalText.split(';');
      variables.forEach((variable, index) => {
        const value = temp[index + 1]?.trim();
        if (value) {
          translatedText = translatedText.replace(variable, value);
        }
      });
    }

    // Replace newlines with <br> tags to preserve spacing
    translatedText = translatedText.replace(/\r?\n/g, '<br>');

    if (node.nodeName !== 'INPUT') {
      const translatedNode = document.createElement('span');
      translatedNode.innerHTML = translatedText; // Use innerHTML to preserve <br> tags
      node.parentNode.replaceChild(translatedNode, node);
    } else {
      node.value = translatedText; // Inputs don't need <br> replacements
    }
  } else {
    undefinedTranslation[originalText] = originalText;
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
    const currentPath = window.location.pathname;

    // Remove the current language code from the path
    const pathSegments = currentPath.split('/');
    pathSegments[1] = ''; // Clear the language code segment

    for (let i = 0; i < dropdownMenu.length; i++) {
      languageOptions.forEach(([name, langCode]) => {
        const listItem = document.createElement('li');
        const link = document.createElement('a');
        link.classList.add('dropdown-item');
		link.classList.add('language-switch-item');
        // Construct the URL based on the current path
        if (langCode === 'en') {
          link.href = `${pathSegments.slice(1).join('/')}`; // For English, no language code
        } else {
          link.href = `/${langCode}/${pathSegments.slice(2).join('/')}`; // Add language code
        }

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


function updateLinksToLanguageVersion() {
  const currentHost = window.location.host;
  const newLangCode = languageCode === 'en' ? '' : languageCode; // Prepare new language code
  const links = document.querySelectorAll('a');

  links.forEach(link => {
    let linkUrl;

    // Try to create a URL object from the link's href
    try {
      // If the link href is relative, create a full URL
      linkUrl = new URL(link.href, window.location.origin);
    } catch (error) {
      console.warn(`Invalid URL: ${link.href}`); // Log invalid URLs
      return; // Skip to the next link
    }

    // Only update links that point to the current host
    const linkHost = linkUrl.host;

    // Check if the link is from the current host and not part of the language switcher
    if (linkHost === currentHost && !link.classList.contains('language-switch-item')) {
      // Check if the current pathname includes the new language code
      if (!linkUrl.pathname.startsWith(`/${newLangCode}`) && newLangCode) {
        // Update the pathname to include the new language code
        linkUrl.pathname = `/${newLangCode}${linkUrl.pathname}`;
      }

      // Update the link href if it has changed
      if (link.href !== linkUrl.href) {
        link.href = linkUrl.href; // Update to the new URL
      }
    }
  });
}

// Call the function as needed
setInterval(updateLinksToLanguageVersion, 1000); // 1000 milliseconds = 1 second

