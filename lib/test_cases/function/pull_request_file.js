/**
 * @file
 * Contains pull request file level test_cases.
 */

String.prototype.toUpperCamelCase = function(s) {
    return s.replace(/(\w)(\w*)/g,
        function(g0,g1,g2){return g1.toUpperCase() + g2.toLowerCase();});
};

var codeCensorPullRequestFile = function(){
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
    function pullRequestFileModuleInfoPackageCheck(prFile) {
        let result = [];
        if (prFile.type === 'info.yml') {
            let packageInfo = undefined;
            let targetPrLine = undefined;
            prFile.lines.forEach(function (currentPrLine) {
                if (currentPrLine instanceof PrLine) {
                    const pattern = new RegExp("\\s*package\\s*:");
                    let content = currentPrLine.content;

                    if (pattern.test(content)) {
                        packageInfo = true;
                    }

                    targetPrLine = currentPrLine;
                }
            });

            if (packageInfo === undefined && targetPrLine !== undefined ) {
                result.push(
                    {
                        line: targetPrLine,
                        test: {
                            category : ['best_practices'],
                            title : 'Add package information in custom module info file',
                            description: 'Generally project specific name as package should be added',
                            links: [
                                {
                                    title: "Custom Module Development",
                                    link: "https://www.drupal.org/docs/8/creating-custom-modules/let-drupal-8-know-about-your-module-with-an-infoyml-file",
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
     * Checks for unnecessary module name prefix in src php files.
     */
    function pullRequestFileModuleSrcPhpModuleName(prFile) {
        let result = [];
        if (prFile.type === 'php') {
            let filePath = prFile.path;
            let filePathArray = filePath.split('/');
            let moduleName = '';

            for (var i = 0; i < filePathArray.length; i++){
               if (filePathArray[i] === 'src' && filePathArray[i - 1] !== undefined) {
                   moduleName = filePathArray[i - 1];
                   break;
               }
            }

            moduleName = moduleName.replace(/_/g, ' ');
            moduleName = moduleName.toUpperCamelCase(moduleName);
            moduleName = moduleName.replace(/\s/g, '');
            let fileName = prFile.name;
            if (fileName.indexOf(moduleName) > 0) {
                result.push(
                    {
                        line: prFile.lines[0],
                        test: {
                            category : ['best_practices'],
                            title : 'Avoid unnecessary appending module name in src php files',
                            description: 'Module specific plugins and controllers are already in module namespace and hence we can avoid this',
                            links : [],
                        }
                    }
                );
            }
        }

        return result;
    }

    /**
     * Checks for params in route have been regex validated.
     */
    function pullRequestFileRoutingParamsRegexCheck(prFile) {
        let result = [];
        if (prFile.type === 'routing.yml') {
            let pathHasParam = false;

            // Check if yml has param.
            prFile.lines.forEach(function (currentPrLine) {
                if (currentPrLine instanceof PrLine) {
                    const pattern = new RegExp("path:.*{");
                    let content = currentPrLine.content;

                    if (pattern.test(content)) {
                        pathHasParam = true;

                        const regex = /{([a-zA-Z]+)}/gm;
                        let params = [];
                        let m;
                        while ((m = regex.exec(content)) !== null) {
                            if (m.index === regex.lastIndex) {
                                regex.lastIndex++;
                            }
                            m.forEach((match, groupIndex) => {
                                if (groupIndex === 1) {
                                    params.push(match);
                                }
                            });
                        }

                        // Validate if param validation has been added.
                        params.forEach(function (param) {

                            let paramValidatorAdded = false;
                            var nextPrLine = prFile.lines[currentPrLine.number];
                            do {
                                let nextPrLineContent = nextPrLine.content;
                                if (nextPrLineContent.indexOf(param + ':') > 0) {
                                    paramValidatorAdded = true;
                                }
                                nextPrLine = prFile.lines[nextPrLine.number];
                            }
                            while (nextPrLine !== undefined && nextPrLine.content);

                            if (!paramValidatorAdded) {
                                result.push(
                                    {
                                        line: currentPrLine,
                                        test: {
                                            category : ['security'],
                                            title : 'Validate route parameters using regular expressions, if possible',
                                            description: '',
                                            links: [
                                                {
                                                    title: "Validate Route Parameters",
                                                    link: "https://www.drupal.org/docs/8/api/routing-system/parameters-in-routes/validate-route-parameters",
                                                },
                                            ]
                                        }
                                    }
                                );
                            }
                        });
                    }
                }
            });
        }

        return result;
    }

    return {
        getAllFunctions: getAllFunctions,
        pullRequestFileModuleInfoPackageCheck: pullRequestFileModuleInfoPackageCheck,
        pullRequestFileModuleSrcPhpModuleName: pullRequestFileModuleSrcPhpModuleName,
        pullRequestFileRoutingParamsRegexCheck: pullRequestFileRoutingParamsRegexCheck,
    };
}();
