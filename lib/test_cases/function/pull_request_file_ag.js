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

  return {
    getAllFunctions: getAllFunctions,
    pullRequestFileCheckedSecuredInput: pullRequestFileCheckedSecuredInput,
  };
}();
