
var suggestions = {};
suggestions.init = {
    regex : 'function\\s*\\S*_init\\(\\)',
    category : 'performance',
    file_types : ['module'],
    title : 'Avoid using hook_init',
    description : 'hook_init executes at the beginning of every single page request and hence affects performance'
};
suggestions.preprocess_field = {
    regex : 'function\\s*\\S*_preprocess_field\\(',
    category : 'performance',
    file_types : ['module', 'theme'],
    title : 'Avoid using hook_preprocess_field',
    description : 'If possible go with specific preprocessor like <a href="https://api.drupal.org/api/drupal/core!modules!system!templates!field.html.twig/8.2.x">hook_preprocess_field__field_type</a>'
};

var categories = {};
categories.performance = {
    short : 'P',
};

var suggestionColor = '#eae173';

/**
 * @todo Sort suggestions based on file types to be more selective.
 */
var applicableSuggestions;

// Implement code review
var reviewFiles = document.querySelectorAll('div.js-details-container.file');
reviewFiles.forEach(function(reviewFile) {
    let fileType = reviewFile.getAttribute('data-file-type');
    let fileContent = reviewFile.querySelectorAll('div.js-file-content table.diff-table tr'); 

    // Start from line 1 and perform suggestion checks.
    for (let lineNumber = 1; lineNumber < fileContent.length; lineNumber++) {
        performSuggestionChecks(fileContent[lineNumber], fileType, lineNumber);
    }
});

function performSuggestionChecks(data, fileType, lineNumber) {
    let content = data.querySelector('span.blob-code-inner').innerHTML;
    
    // Perform review.
    for (const identifier in suggestions) {
        let suggestion = suggestions[identifier];
        
        if (suggestions.hasOwnProperty(identifier)) {
            let pattern = new RegExp(suggestion.regex)
            if (pattern.test(content)) {
                console.log('Match found');
                console.log(content + ' for ' + identifier);
                highlightRow(data, suggestion, lineNumber);
            }
        }
    }
}

// Highlight reviews
function highlightRow(data, suggestion, lineNumber) {
    data.className += " cc-suggest cc-tooltip";
    // data.querySelectorAll('td').forEach(function(el) {
    //     el.style.backgroundColor = suggestionColor;
    // });
    let emptyCell = data.querySelector('td.blob-num.empty-cell');
    emptyCell.innerText = categories[suggestion.category].short;

    // Create tooltip to show suggestion.
    let tooltipData = document.createElement('div');
    tooltipData.className = 'top cc-tooltip-text';
    tooltipDataTitle = document.createElement('h3');
    tooltipDataTitle.innerText = suggestion.title;
    tooltipDataDesc = document.createElement('p');
    tooltipDataDesc.innerHTML = suggestion.description;
    tooltipData.appendChild(tooltipDataTitle);    
    tooltipData.appendChild(tooltipDataDesc);
    tooltipData.appendChild(document.createElement('i'));
    data.innerHTML += tooltipData.outerHTML;

    // Position the tooltip.
    data.addEventListener('mouseover', function(event) {
        let tooltip = data.querySelector('.cc-tooltip-text');
        tooltip.style.top = lineNumber*20 + ' px';
    });
}
