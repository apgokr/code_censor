/**
 * @file
 * Contains functions to start code censor.
 */

// Load all applicable test cases.
function startCodeCensor() {
    var coreTestsFile = chrome.runtime.getURL('lib/core.tests.ser');
    fetch(coreTestsFile)
        .then((response) => response.text())
        .then((response) => executeCoreTests(response));
}

function executeCoreTests(response) {
  let coreTests = JSON.parse(response);

  // Read current Pull Request.
  const currentPullRequest = readPullRequest();

  // Parse current Pull Request.
  parsePullRequest(currentPullRequest, coreTests);
}

const url = window.location.toString();
let filesUrl = new RegExp("\/files");
if (filesUrl.test(url)) {
    // Execute the test script now.
    startCodeCensor();
}
