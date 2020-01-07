/**
 * @file
 * Contains pull request level test_cases.
 */

var codeCensorPullRequest = function(){
    // @todo Get this sorted on filetype basis.
    function getAllFunctions(){
        var pullRequestFunctions = [];
        for (var l in this){
            if (this.hasOwnProperty(l) &&
                this[l] instanceof Function &&
                !/pullRequestFunctions/i.test(l)){
                pullRequestFunctions.push(this[l]);
            }
        }
        return pullRequestFunctions;
    }

    /**
     * Checks package information is present or not.
     */
    function pullRequestModuleHelpCheck(currentPullRequest) {
        let result = [];

        let newModuleInfoFiles = [];
        let moduleFilesWithoutHelp = [];
        currentPullRequest.files.forEach(function (currentPrFile) {
            if (currentPrFile instanceof PrFile) {
                if (currentPrFile.type === 'info.yml') {
                    // Check if module is newly defined.
                    let checks = {name: false, description: false, core: false};
                    currentPrFile.lines.forEach(function (currentPrLine) {
                        if (currentPrLine.content.indexOf('name') >= 0) {
                           checks.name = true;
                        }
                        if (currentPrLine.content.indexOf('description') >= 0) {
                           checks.description = true;
                        }
                        if (currentPrLine.content.indexOf('core') >= 0) {
                           checks.core = true;
                        }
                    });

                    if (checks.name && checks.description && checks.core) {
                        // It's a new project, add to the list.
                        const regex = /\/(\S+).info.yml/;
                        let matches = regex.exec(currentPrFile.name);
                        if ((matches !== undefined) && (matches[1] !== undefined)) {
                            newModuleInfoFiles.push(matches[1]);
                        }
                    }
                }

                if (currentPrFile.type === 'module') {
                    let moduleHelp = false;
                    currentPrFile.lines.forEach(function (currentPrLine) {
                        if (currentPrLine.content.indexOf('hook_help') >= 0) {
                            moduleHelp = true;
                        }
                    });

                    if (!moduleHelp) {
                        moduleFilesWithoutHelp.push(currentPrFile);
                    }
                }
            }
        });

        moduleFilesWithoutHelp.forEach(function (moduleFile) {
            // Check if info file exists for a new module confirmation.
            const regex = /\/(\S+).module/;
            let matches = regex.exec(moduleFile.name);
            if ((matches !== undefined) && (matches[1] !== undefined)) {
                if (newModuleInfoFiles.indexOf(matches[1]) >= 0) {
                    result.push(
                        {
                            line: moduleFile.lines[0],
                            test: {
                                category : ['best_practices'],
                                title : 'Looks like module hook_help is missing',
                                description: 'Always implement hook_help in newly created custom module',
                            }
                        }
                    );
                }
            }
        });

        return result;
    }

    /**
     * Checks module config files have config schema.
     */
    function pullRequestConfigSchema(currentPullRequest) {
        let result = [];

        let modulesWithInstallConfigs = [];
        let moduleConfigSchemaFiles = [];
        let moduleNameConfigMapping = [];
        currentPullRequest.files.forEach(function (prFile) {
            if (prFile.type.indexOf('.yml') && prFile.name.indexOf('config/install') >= 0) {
               let modulename = '';
               prFile.name.split("/").forEach(function (nameComponent) {
                   if (nameComponent === 'config' && modulesWithInstallConfigs.indexOf(modulename) === -1) {
                       modulesWithInstallConfigs.push(modulename);
                       moduleNameConfigMapping[modulename] = prFile.lines[prFile.lines.length - 1];
                   }
                   modulename = nameComponent;
               });
           }

           // Collect all schema yml files.
           if (prFile.type === 'schema.yml' && prFile.name.indexOf('config')) {
               let modulename = '';
               prFile.name.split("/").forEach(function (nameComponent) {
                   if (nameComponent === 'config' && modulesWithInstallConfigs.indexOf(modulename) === -1) {
                       moduleConfigSchemaFiles.push(modulename);
                   }
                   modulename = nameComponent;
               });
           }
        });

        // Foreach modules with install configs check schema file is present.
        modulesWithInstallConfigs.forEach(function (moduleName) {
           if (moduleConfigSchemaFiles.indexOf(moduleName)) {
               result.push(
                   {
                       line: moduleNameConfigMapping[moduleName],
                       test: {
                           category : ['functionality'],
                           title : 'Adding/updating config schema is recommended for config installs',
                           description: 'The primary use case schema files were introduced for is multilingual support.',
                           links: [
                               {
                                   title: "Configuration Schema/Metadata",
                                   link: "https://www.drupal.org/docs/8/api/configuration-api/configuration-schemametadata",
                               },
                           ]
                       }
                   }
               );
           }
        });

        return result;
    }
    return {
        getAllFunctions: getAllFunctions,
        pullRequestModuleHelpCheck: pullRequestModuleHelpCheck,
        pullRequestConfigSchema: pullRequestConfigSchema,
    };
}();
