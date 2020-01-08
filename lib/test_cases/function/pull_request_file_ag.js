/**
 * Author: Ashutosh Gupta
 *
 * @file
 * Contains pull request file level test_cases.
 */

String.prototype.toUpperCamelCase = function(s) {
  return s.replace(/(\w)(\w*)/g,
    function(g0,g1,g2){return g1.toUpperCase() + g2.toLowerCase();});
};

var codeCensorPullRequestFileAg = function(){
  // @todo Get this sorted on filetype basis.
  function getAllFunctions(){
    var pullRequestFileFunctions = [];
    for (var l in this){
      if (this.hasOwnProperty(l) &&
        this[l] instanceof Function &&
        !/pullRequestFileFunctions/i.test(l)){
        pullRequestFileFunctions.push(this[l]);
      }
    }
    return pullRequestFileFunctions;
  }

  /**
   * Checks package information is present or not.
   */
  function pullRequestFileCheckedSecuredInput(prFile) {
    let result = [];
    if (prFile.type === 'js') {
      let targetPrLine = undefined;
      let assigned_variable = undefined;
      prFile.lines.forEach(function (currentPrLine) {
        if (currentPrLine instanceof PrLine) {
          const pattern = new RegExp("text\\(\\)");
          let content = currentPrLine.content;
          let form_keywords = ['form', 'input', 'select', 'textarea'];
          if (pattern.test(content)) {
            form_keywords.forEach(function (word, index) {
              if (content.includes(word)) {
                targetPrLine = currentPrLine;
                if (!content.includes("Drupal.checkPlain")) {
                  assigned_variable = content.split("=")[0].trim().split(" ").pop();
                }
              }
            });
          }
          if (typeof(assigned_variable) !== 'undefined') {
            if (content.includes(assigned_variable) && content.includes("Drupal.checkPlain")) {
              targetPrLine = undefined;
            }
          }
        }
      });

      if (targetPrLine !== undefined ) {
        result.push(
          {
            line: targetPrLine,
            test: {
              category : ['security'],
              title : 'Use Drupal.checkPlain when showing some input text in JS',
              description: 'Generally use Drupal check plain before displaying any data, if not used.',
              links: [
                {
                  title: "Drupal 8: Sanitizing Output",
                  link: "https://www.drupal.org/docs/8/security/drupal-8-sanitizing-output",
                },
              ]
            }
          }
        );
      }
    }
    return result;
  }

  /**
   * Check duplicate code in switch case.
   */
  function pullRequestFileCheckSwitchDuplicateCode(prFile) {
    let result = [];
    let caseStart = 0;
    let case_statements = [];
    let is_duplicate_code = 0;
    if (prFile.type === 'php' || prFile.type === 'module' || prFile.type === 'theme') {
      let targetPrLine = undefined;
      prFile.lines.forEach(function (currentPrLine) {
        if (currentPrLine instanceof PrLine) {
          const pattern = new RegExp("case \'[a-zA-Z0-9]+\'\:");
          let content = currentPrLine.content;
          if (content.includes("switch")) {
            targetPrLine = currentPrLine
            case_statements = [];
          }
          if (content.includes("break;")) {
            caseStart = 0;
          }
          if (caseStart) {
            if (case_statements.indexOf(content.trim()) > -1) {
              is_duplicate_code = 1;
            }
            else {
              case_statements.push(content.trim())
            }
          }
          if (pattern.test(content)) {
            caseStart = 1;
          }
        }
        if (is_duplicate_code) {
          result.push(
            {
              line: targetPrLine,
              test: {
                category : ['best_practices'],
                title : 'Avoid code duplicacy in switch cases',
                description: 'Avoid duplicate code statements in switch case.'
              }
            }
          );
          is_duplicate_code = 0;
          targetPrLine = undefined;
        }
      });
    }

    return result;
  }

  /**
   * Check for name tags in subscriber service.
   */
  function pullRequestFileCheckSubscriberServiceTags(prFile) {
    let result = [];
    if (prFile.type === 'services.yml') {
      let targetPrLine = undefined;
      let is_pattern_present = 0;
      prFile.lines.forEach(function (currentPrLine) {
        if (currentPrLine instanceof PrLine) {
          const pattern = new RegExp("[a-z]+_subscriber\:");
          let content = currentPrLine.content;
          if (pattern.test(content)) {
            is_pattern_present = 1;
            targetPrLine = currentPrLine
          }
          if (is_pattern_present) {
            if (content.includes("tags:")) {
              targetPrLine = undefined;
            }
          }
        }
      });

      if (targetPrLine !== undefined) {
        result.push(
          {
            line: targetPrLine,
            test: {
              category : ['functionality'],
              title : 'Add name tags for subscriber services.',
              description: 'Services that are special, like event subscribers, route subscriber or access checker, should be tagged with name in services.yml.'
            }
          }
        );
      }
    }

    return result;
  }

  /**
   * Check for long functions.
   */
  function pullRequestFileCheckLongFunctions(prFile) {
    let result = [];
    if (prFile.type === 'php' || prFile.type === 'module' || prFile.type === 'theme') {
      let targetPrLine = undefined;
      let isFunction = 0;
      let countFunctionLines = 0;
      let fnStartLine, fnEndLine = 0;
      prFile.lines.forEach(function (currentPrLine) {
        if (currentPrLine instanceof PrLine) {
          const pattern = new RegExp("^function", "gm");
          let content = currentPrLine.content;
          if (pattern.test(content)) {
            isFunction = 1;
            fnStartLine = currentPrLine.number;
            targetPrLine = currentPrLine;
          }
          if (isFunction) {
            if (content.includes("{")) {
              countFunctionLines++;
            }
            if (countFunctionLines > 0 && content.includes("}")) {
              countFunctionLines--;
            }
          }
          if (countFunctionLines === 0 && isFunction) {
            isFunction = 0;
            fnEndLine = currentPrLine.number;
            if((fnEndLine - fnStartLine) > 30) {
              result.push(
                {
                  line: targetPrLine,
                  test: {
                    category : ['functionality'],
                    title : 'Avoid long functions if possible.',
                    description: 'Kindly keep the function length to 30 LOC.'
                  }
                }
              );
              targetPrLine = undefined;
            }
          }
        }
      })
    }

    return result;
  }

  return {
    getAllFunctions: getAllFunctions,
    pullRequestFileCheckedSecuredInput: pullRequestFileCheckedSecuredInput,
    pullRequestFileCheckSwitchDuplicateCode: pullRequestFileCheckSwitchDuplicateCode,
    pullRequestFileCheckSubscriberServiceTags: pullRequestFileCheckSubscriberServiceTags,
    pullRequestFileCheckLongFunctions:pullRequestFileCheckLongFunctions
  };
}();
