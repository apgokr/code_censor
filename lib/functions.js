/**
 * Returns current PullRequest and the constituting objects {PrFile(s) and PrLine(s)}.
 */
function parsePullRequest() {
  var currentPullRequest = new PullRequest()
  var reviewFiles = document.querySelectorAll('div.js-details-container.file')
  reviewFiles.forEach(function (reviewFile) {
    const dataPath = reviewFile.querySelector('.file-header').getAttribute('data-path')
    const patternMatches = dataPath.match('\/(.*$)')
    let fileName, fileType
    if (patternMatches) {
      fileName = patternMatches[1]
      const fileTypePatternMatches = fileName.match('^[^.]*(.*)')
      if (fileTypePatternMatches) {
        fileType = fileTypePatternMatches[1];
      }
    }

    const currentFile = new PrFile(fileType, fileName, dataPath)
    currentPullRequest.files.push(currentFile)
    const fileContent = reviewFile.querySelectorAll(
      'div.js-file-content table.diff-table tr'
    )
    // Start from line 1 and perform suggestion checks.
    for (let lineNumber = 1; lineNumber < fileContent.length; lineNumber++) {
      let lineContent = fileContent[lineNumber].querySelector('span.blob-code-inner span.pl-s')
      if (lineContent) {
        lineContent = lineContent.innerHTML
        const currentLine = new PrLine(lineNumber, lineContent, currentFile)
        currentFile.lines.push(currentLine)
      }
    }
  })

  return currentPullRequest
}
