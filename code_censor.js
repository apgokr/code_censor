/**
 * @file
 * Contains functions to start code censor.
 */

// Load all applicable test cases.
function startCodeCensor() {
    var coreTestsFile = chrome.runtime.getURL('lib/core.regex_tests.ser');
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

// Execution starts here.
const url = window.location.toString();
let filesUrl = new RegExp("\/files");
if (filesUrl.test(url)) {
    // Execute the test script now.
    startCodeCensor();
}

let debounce;
// Observe progressively loaded diff content
Array.from(document.querySelectorAll(`
  .js-diff-progressive-container,
  .js-diff-load-container`
)).forEach(target => {
  new MutationObserver(mutations => {
    mutations.forEach(mutation => {
      // preform checks before adding code wrap to minimize function calls
      const tar = mutation.target;
      if (tar && (
        tar.classList.contains("js-diff-progressive-container") ||
        tar.classList.contains("js-diff-load-container") ||
        tar.classList.contains("blob-wrapper")
      )
      ) {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
          startCodeCensor();
        }, 500);
      }
    });
  }).observe(target, {
    childList: true,
    subtree: true
  });
});
