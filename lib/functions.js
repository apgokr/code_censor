/**
 * Returns current PullRequest and the constituting objects {PrFile(s) and PrLine(s)}.
 */
function readPullRequest() {
    let currentPullRequest = new PullRequest();
    let reviewFiles = document.querySelectorAll('div.js-details-container.file');
    reviewFiles.forEach(function (reviewFile) {
        let dataPath = reviewFile.querySelector('.file-header').getAttribute('data-path');
        let patternMatches = dataPath.match('\/?(.*$)');
        let fileName, fileType;
        if (patternMatches) {
            fileName = patternMatches[1];
            let fileTypePatternMatches = fileName.match('^[^.]*\.(.*)');
            if (fileTypePatternMatches) {
                fileType = fileTypePatternMatches[1];
            }
        }

        let currentFile = new PrFile(fileType, fileName, dataPath);
        currentPullRequest.files.push(currentFile);
        let fileContent = reviewFile.querySelectorAll(
            'div.js-file-content table.diff-table tr'
        );

        // Start from line 1.
        for (let lineNumber = 1; lineNumber < fileContent.length; lineNumber++) {
            let lineContent = fileContent[lineNumber].querySelector('td.blob-code-addition > span.blob-code-inner');
            if (lineContent) {
                let lineDom = lineContent;
                lineContent = lineContent.innerText;
                // Completely Empty the empty lines.
                lineContent = lineContent.replace(/(\r\n|\n|\r)/gm, "");
                let currentLine = new PrLine(lineNumber, lineContent, currentFile, lineDom);
                currentFile.lines.push(currentLine);
            }
        }
    });

    return currentPullRequest;
}

/**
 * Parse Pull request object as per applicable tests.
 *
 * @param currentPullRequest
 * @param coreTests
 */
function parsePullRequest(currentPullRequest, coreTests) {
    if (currentPullRequest instanceof PullRequest) {
        // Perform PR Level function based tests.
        performPullRequestChecks(currentPullRequest);

        // Executes tests for all PR Files.
        currentPullRequest.files.forEach(function (currentPrFile) {
            if (currentPrFile instanceof PrFile) {
                performTestsCheck(currentPrFile, coreTests);
            }
        })
    }
}

/**
 * Performs checks for function based test_cases on PR level.
 *
 * @param currentPullRequest
 */
function performPullRequestChecks(currentPullRequest) {
    codeCensorPullRequestFunctions = codeCensorPullRequest.getAllFunctions();

    if (codeCensorPullRequestFunctions.length > 1) {
        for (let i = 1; i < codeCensorPullRequestFunctions.length; i++) {
            let results = codeCensorPullRequestFunctions[i](currentPullRequest);

            if (results !== undefined && results.length) {
                results.forEach(function (result) {
                    if (result.line instanceof PrLine && result.test !== undefined) {
                        highlightRow(result.line, result.test);
                    }
                });
            }
        }
    }
}

/**
 * For PR File perform applicable tests.
 *
 * @param currentPrFile
 * @param coreTests
 */
function performTestsCheck(currentPrFile, coreTests) {
    if (currentPrFile instanceof PrFile) {
        // Perform file level function based tests.
        performPullRequestFileChecks(currentPrFile);

        // Proceed for regex based tests.
        let currentPrFileType = currentPrFile.type;
        if (coreTests.hasOwnProperty(currentPrFileType)) {
            let applicableTests = coreTests[currentPrFileType];
            currentPrFile.lines.forEach(function (currentPrLine) {
                if (currentPrLine instanceof PrLine) {
                    performRegexCheck(currentPrLine, applicableTests);
                }
            })
        }
    }
}

/**
 * Performs checks for function based test_cases on file level.
 *
 * @param currentPrFile
 */
function performPullRequestFileChecks(currentPrFile) {
    // @todo Get this sorted on filetype basis.
    codeCensorPullRequestFileFunctions = codeCensorPullRequestFile.getAllFunctions();

    if (codeCensorPullRequestFileFunctions.length > 1) {
        for (let i = 1; i < codeCensorPullRequestFileFunctions.length; i++) {
            let results = codeCensorPullRequestFileFunctions[i](currentPrFile);

            if (results !== undefined && results.length) {
                results.forEach(function (result) {
                    if (result.line instanceof PrLine && result.test !== undefined) {
                        highlightRow(result.line, result.test);
                    }
                });
            }
        }
    }
}

/**
 * For PR Line perform regex check.
 *
 * @param currentPrLine
 * @param applicableTests
 */
function performRegexCheck(currentPrLine, applicableTests) {
    let content = currentPrLine.content;

    // Perform test check.
    for (const identifier in applicableTests) {
        const applicableTest = applicableTests[identifier];
        const pattern = new RegExp(applicableTest.regex);
        if (pattern.test(content)) {
            highlightRow(currentPrLine, applicableTest);
            // Currently supporting one suggestion per line.
            // @todo Plan multiple suggestions per line.
            break;
        }
    }
}

/**
 * Process Highlighting of PR Line.
 *
 * @param currentPrLine
 * @param applicableTest
 */
function highlightRow(currentPrLine, applicableTest) {
    let executed = false;
    // Check chrome.storage for configurations.
    applicableTest.category.forEach(function (cat) {
       chrome.storage.sync.get(cat, function (obj) {
          if (!executed && obj[cat]) {
              executeHighlightRow(currentPrLine, applicableTest);
              executed = true;
          }
       });
    });
}

/**
 * Executes highlighting process of row.
 *
 * @param currentPrLine
 * @param applicableTest
 */
function executeHighlightRow(currentPrLine, applicableTest) {
    const data = currentPrLine.domElement.parentNode.parentNode;
    if (data == null) {
        return;
    }

    let id = data.querySelector('tr > td.js-linkable-line-number').id;
    if (data.querySelector('div.top.cc-tooltip-text')) {
        // Return if the row already has tooltip.
        return;
    }

    // Add suggestion icon.
    addSuggestionIcon(data, applicableTest);

    // Create tooltip to show suggestion.
    data.innerHTML += prepareToolTip(applicableTest, id);

    // Position the tooltip.
    data.addEventListener('mouseover', function (event) {
        const tooltip = data.querySelector('.cc-tooltip-text');
        if (tooltip) {
            tooltip.style.top = currentPrLine.number * 20 + ' px';
        }
    });

    // Prepare comment on click.
    data.querySelector('.cc-add-comment').addEventListener('click', function () {
        data.querySelector('button.js-add-line-comment').click();
        setTimeout(
            function () {
                let inlineCommentLink = data.nextElementSibling;
                let inlineCommentForm = inlineCommentLink.querySelector('form.js-inline-comment-form');
                let inlineCommentValue = '**' + applicableTest.title + '**\n' + applicableTest.description;
                if (applicableTest.links !== undefined && applicableTest.links.length > 0) {
                    inlineCommentValue = inlineCommentValue + '\n\n **References:**';

                    applicableTest.links.forEach( function (link) {
                            inlineCommentValue = inlineCommentValue + '\n [' + link.title + '](' + link.link + ')';
                        }
                    );
                }

                inlineCommentForm.querySelector('textarea[name="comment[body]"]').value = inlineCommentValue;
                inlineCommentForm.querySelector('button[name="single_comment"]').disabled = false;
            }, 500
        );
    });

}

/**
 * Add suggestion icon based on test category.
 *
 * @param data
 * @param applicableTest
 */
function addSuggestionIcon(data, applicableTest) {
    data.className += ' cc-suggest cc-tooltip';
    const emptyCell = data.querySelector('td.blob-num.empty-cell');
    if (emptyCell.firstElementChild === null) {
        // Display icon image
        const suggestionIcon = document.createElement('img');
        if (applicableTest.category.length > 0) {
            let suggestionCat = '';
            if (applicableTest.category.length > 1) {
                suggestionCat = 'miscellaneous';
            }
            else {
                suggestionCat = applicableTest.category[0];
            }
            var imgURL = chrome.extension.getURL('assets/icons/' + suggestionCat + '.png');
            suggestionIcon.className = 'cc-reset cc-icon cc-' + suggestionCat;
            suggestionIcon.src = imgURL;
            suggestionIcon.alt = suggestionCat;
            emptyCell.appendChild(suggestionIcon);
        }
    }
}

/**
 * Prepare tooltip to display suggestion.
 *
 * @param applicableTest
 * @param id
 * @returns {string}
 */
function prepareToolTip(applicableTest, id) {
    const tooltipData = document.createElement('div');
    tooltipData.className = 'top cc-reset cc-tooltip-text';
    tooltipData.id = id;
    let tooltipDataTitle = document.createElement('h4');
    tooltipDataTitle.innerText = applicableTest.title;
    let tooltipDataCat = document.createElement('div');
    tooltipDataCat.className = 'cc-category';
    tooltipDataCat.innerHTML = '<em>Category: ' + applicableTest.category.join(', ') + '</em>';
    let tooltipDataDesc = document.createElement('p');
    tooltipDataDesc.innerHTML = applicableTest.description;
    tooltipData.appendChild(tooltipDataTitle);
    tooltipData.appendChild(tooltipDataCat);
    tooltipData.appendChild(tooltipDataDesc);

    if (applicableTest.links !== undefined) {
        let tooltipDataRef =document.createElement('div');
        tooltipDataRef.className = 'cc-links';
        tooltipDataRef.innerHTML = '<b>Reference Links:</b>' + '<ul>';
        applicableTest.links.forEach( function (link) {
                tooltipDataRef.innerHTML =  tooltipDataRef.innerHTML + '<li><a target="_blank" href="' + link.link + '">' + link.title + '</a></li>';
            }
        );
        tooltipDataRef.innerHTML = tooltipDataRef.innerHTML + '</ul>';
        tooltipData.appendChild(tooltipDataRef);
    }

    let tooltipDataCommentLink = document.createElement('div');
    tooltipDataCommentLink.innerHTML = '<a href = "javascript:void(0);" class="cc-add-comment"><b>Add Comment</b></a>';
    tooltipData.appendChild(tooltipDataCommentLink);

    tooltipData.appendChild(document.createElement('i'));
    return tooltipData.outerHTML;
}
