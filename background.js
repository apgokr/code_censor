chrome.tabs.onUpdated.addListener(
    function(tabId, changeInfo, tab) {
        if (changeInfo.url) {
            chrome.tabs.sendMessage( tabId, {
                message: 'invokeCodeCensor',
                url: changeInfo.url
            })
        }
    }
);

chrome.runtime.onInstalled.addListener(function() {

    chrome.storage.sync.set({code_censor: true});
    chrome.storage.sync.set({best_practices: true});
    chrome.storage.sync.set({functionality: true});
    chrome.storage.sync.set({security: true});
    chrome.storage.sync.set({performance: true});

    chrome.declarativeContent.onPageChanged.removeRules(undefined, function() {
        chrome.declarativeContent.onPageChanged.addRules([{
            conditions: [new chrome.declarativeContent.PageStateMatcher({
                pageUrl: {hostEquals: 'developer.chrome.com'},
            })
            ],
            actions: [new chrome.declarativeContent.ShowPageAction()]
        }]);
    });
});
