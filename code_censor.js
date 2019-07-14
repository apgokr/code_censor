var suggestions = {}
suggestions.init = {
  regex: 'function\\s*\\S*_init\\(\\)',
  category: 'performance',
  file_types: ['module'],
  title: 'Avoid using hook_init',
  description:
    'hook_init executes at the beginning of every single page request and hence affects performance'
}
suggestions.preprocess_field = {
  regex: 'function\\s*\\S*_preprocess_field\\(',
  category: 'performance',
  file_types: ['module', 'theme'],
  title: 'Avoid using hook_preprocess_field',
  description:
    'If possible go with specific preprocessor like <a href="https://api.drupal.org/api/drupal/core!modules!system!templates!field.html.twig/8.2.x">hook_preprocess_field__field_type</a>'
}

// Implement code review
var reviewFiles = document.querySelectorAll('div.js-details-container.file')
reviewFiles.forEach(function (reviewFile) {
//   let fileType = reviewFile.getAttribute('data-file-type')
  const fileContent = reviewFile.querySelectorAll(
    'div.js-file-content table.diff-table tr'
  )

  // Start from line 1 and perform suggestion checks.
  for (let lineNumber = 1; lineNumber < fileContent.length; lineNumber++) {
    performSuggestionChecks(fileContent[lineNumber], lineNumber)
  }
})

function performSuggestionChecks (data, lineNumber) {
  const content = data.querySelector('span.blob-code-inner').innerHTML

  // Perform review.
  for (const identifier in suggestions) {
    const suggestion = suggestions[identifier]

    // if (suggestions.hasOwnProperty(identifier)) {
    const pattern = new RegExp(suggestion.regex)
    if (pattern.test(content)) {
      highlightRow(data, suggestion, lineNumber)
    }
    // }
  }
}

// Highlight reviews
function highlightRow (data, suggestion, lineNumber) {
  data.className += ' cc-suggest cc-tooltip'
  // data.querySelectorAll('td').forEach(function(el) {
  //     el.style.backgroundColor = suggestionColor;
  // });
  const emptyCell = data.querySelector('td.blob-num.empty-cell')

  // Display icon image
  const suggestionIcon = document.createElement('img')
  var imgURL = chrome.extension.getURL('assets/icons/' + suggestion.category + '.png')
  suggestionIcon.className = 'cc-icon cc-' + suggestion.category
  suggestionIcon.src = imgURL
  emptyCell.appendChild(suggestionIcon)

  // Create tooltip to show suggestion.
  const tooltipData = document.createElement('div')
  tooltipData.className = 'top cc-tooltip-text'
  var tooltipDataTitle = document.createElement('h3')
  tooltipDataTitle.innerText = suggestion.title
  var tooltipDataDesc = document.createElement('p')
  tooltipDataDesc.innerHTML = suggestion.description
  tooltipData.appendChild(tooltipDataTitle)
  tooltipData.appendChild(tooltipDataDesc)
  tooltipData.appendChild(document.createElement('i'))
  data.innerHTML += tooltipData.outerHTML

  // Position the tooltip.
  data.addEventListener('mouseover', function (event) {
    const tooltip = data.querySelector('.cc-tooltip-text')
    tooltip.style.top = lineNumber * 20 + ' px'
  })
}
