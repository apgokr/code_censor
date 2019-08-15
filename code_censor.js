// Load all applicable test cases.
var coreTestsFile = chrome.runtime.getURL('lib/core.tests.ser');
fetch(coreTestsFile)
    .then((response) => response.text())
    .then((response) => executeCoreTests(response));

function executeCoreTests(response) {
  let coreTests = JSON.parse(response);

  // Read current Pull Request.
  const currentPullRequest = readPullRequest();

  // Parse current Pull Request.
  parsePullRequest(currentPullRequest, coreTests);
}
