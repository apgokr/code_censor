let ccOptions = document.querySelectorAll('.cc-popup-option');

ccOptions.forEach(function (ccOption) {
    let ccOptionInput = ccOption.querySelector('input[type="checkbox"]');
    let ccOptionName = ccOptionInput.name;

    // Set Default Values.
    chrome.storage.sync.get(ccOptionName, function (obj) {
        ccOptionInput.checked = obj[ccOptionName];
    });

    ccOptionInput.addEventListener('click', function () {
        let ccOption = {};
        ccOption[ccOptionName] = ccOptionInput.checked;

        if (ccOptionName === 'code_censor') {
            let ccOptionsCategories = document.querySelectorAll('.cc-popup-options-category .cc-popup-option');
            if (ccOption[ccOptionName] === false) {
                ccOptionsCategories.forEach(function (ccOptionCategory) {
                    ccOptionCategory.querySelector('input[type="checkbox"]').disabled = true;
                })
            }
            else {
                ccOptionsCategories.forEach(function (ccOptionCategory) {
                    ccOptionCategory.querySelector('input[type="checkbox"]').disabled = false;
                })
            }
        }

        chrome.storage.sync.set(ccOption);

        chrome.tabs.query({active: true, currentWindow: true}, function(tabs) {
            chrome.tabs.executeScript(
                tabs[0].id,
                {code: 'resetCodeCensor();'});
        });
    });
});
