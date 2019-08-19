chrome.runtime.onMessage.addListener(
function(request, sender, sendResponse) {
    // listen for messages sent from background.js
    if (request.message === 'invokeCodeCensor') {
        let filesUrl = new RegExp("\/files");
        if (filesUrl.test(request.url)) {
            // Execute the test script now.
            startCodeCensor();
        }
    }
});
