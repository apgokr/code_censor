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
        if (prFile.type === 'info.yml' && prFile.name.indexOf('theme') < 0) {
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
        if (prFile.type === 'php' && prFile.name.indexOf('src') > 0) {
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
            const fileNameRegex = /\/([a-zA-Z0-9]+).php$/;
            let matches = fileNameRegex.exec(fileName);
            if ((matches !== undefined) && (matches[1] !== undefined)) {
                fileName = matches[1];
            }
            if (fileName.indexOf(moduleName) >= 0) {
                result.push(
                    {
                        line: prFile.lines[0],
                        test: {
                            category : ['best_practices'],
                            title : 'Avoid unnecessary appending module name in src php files',
                            description: 'Module specific plugins and controllers are already in module namespace and hence we can avoid this',
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
            prFile.lines.forEach(function (currentPrLine, index) {
                if (currentPrLine instanceof PrLine) {
                    const pattern = new RegExp("path:.*{");
                    let content = currentPrLine.content;

                    if (pattern.test(content)) {
                        pathHasParam = true;

                        const regex = /{([a-zA-Z_-]+)}/gm;
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
                            let paramValidationNeeded = true;
                            var nextPrLine = currentPrLine;
                            let indexPointer = index;
                            do {
                                let previousPrLineNumber = nextPrLine.number;
                                indexPointer = indexPointer + 1;
                                nextPrLine = prFile.lines[indexPointer];

                                // Check continuity.
                                if (nextPrLine.number === previousPrLineNumber + 1) {
                                    let nextPrLineContent = nextPrLine.content;
                                    if (nextPrLineContent.indexOf(param + ':') >= 0) {
                                        paramValidatorAdded = true;
                                    }
                                }
                                else {
                                    paramValidationNeeded = false;
                                    nextPrLine = undefined;
                                }
                            }
                            while (typeof prFile.lines[indexPointer + 1] !== 'undefined' && typeof nextPrLine !== "undefined");

                            if (paramValidationNeeded && !paramValidatorAdded) {
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

    /**
     * Check comments in Pr File.
     */
    function pullRequestFileCheckComments(prFile) {
        let result = [];
        let threshold = 20;
        let applicableFileTypes = ['php', 'module', 'theme'];
        if (applicableFileTypes.find(function(item){
            return item === prFile.type;
        })) {
            let commentNotFoundLineCount = 0;
            prFile.lines.forEach(function (prLine, index) {
                if (typeof prFile.lines[index + 1] !== 'undefined') {
                    const pattern = new RegExp("\\/\\/|\\/\\*\\*");
                    let currentPrLineNumber = prLine.number;
                    let nextPrLineNumber = prFile.lines[index + 1].number;
                    if (!pattern.test(prLine.content) && (nextPrLineNumber === currentPrLineNumber + 1)) {
                        commentNotFoundLineCount++;
                    }
                    else {
                        // Reset if comment found.
                        commentNotFoundLineCount = 0;
                    }
                }

                if (commentNotFoundLineCount > threshold) {
                    result.push(
                        {
                            line: prLine,
                            test: {
                                category : ['best_practices'],
                                title : 'Add comments for better readability',
                                description: 'Kindly validate proper comments are added for code readability and hence maintainability',
                            }
                        }
                    );

                    commentNotFoundLineCount = 0;
                }
            });
        }

        return result;
    }

    /**
     * Checks if content.field is validated before printing.
     */
    function pullRequestFileTwigContentField(prFile) {
        let result = [];

        if (prFile.type === 'html.twig') {
            prFile.lines.forEach(function (prLine, index) {
                const pattern = new RegExp('{{\\s*content.field_');

                if (pattern.test(prLine.content)) {
                    const regex = /{{\s*content.field_(\S+)\s*}}/;
                    let matches = regex.exec(prLine.content);
                    let contentField;
                    if ((matches !== undefined) && (matches[1] !== undefined)) {
                        contentField = 'content.field_' + matches[1];
                    }

                    // If content.field is printed, look for if condition.
                    let fieldIfCondition = false;
                    let previousPrLineNumber = prLine.number;
                    for (let i = index - 1; i >= 0; i--) {
                        if (prFile.lines[i] instanceof PrLine && previousPrLineNumber - 1 === prFile.lines[i].number) {
                            const regex = new RegExp("\\{\\%\\s*if\\s*" + contentField);
                            if (regex.test(prFile.lines[i].content)) {
                                fieldIfCondition = true;
                            }
                            previousPrLineNumber = prFile.lines[i].number;
                        }
                        else {
                            break;
                        }
                    }

                    if (!fieldIfCondition) {
                        result.push(
                            {
                                line: prLine,
                                test: {
                                    category : ['functionality'],
                                    title : 'Check if not empty content.field before printing',
                                    description: 'Kindly validate content field value before printing',
                                }
                            }
                        );
                    }
                }
            });
        }

        return result;
    }

    function pullRequestFileRenderArrayCache(prFile) {
        let result = [];

        let applicableFileTypes = ['php', 'module', 'theme'];
        if (applicableFileTypes.find(function(item){
            return item === prFile.type;
        })) {
            prFile.lines.forEach(function (prLine, index) {
                // Find #theme or #type or $build implementations.
                const renderArrayRegex = new RegExp('\'#theme\'|\'#type\'');
                if (renderArrayRegex.test(prLine.content)) {
                    let targetPrLine = prLine;
                    let targetPrLineIndex = index;
                    let cacheNeeded = true;
                    let cachefound = false;
                    do {
                        // See if return or form found.
                        if (targetPrLine.content.indexOf('form') >= 0) {
                            cacheNeeded = false;
                        }

                        // Try to find cache.
                        if (targetPrLine.content.indexOf('cache') >= 0 || targetPrLine.content.indexOf('Cache') >= 0) {
                            cachefound = true;
                        }

                        // Check Continuity.
                        if (prFile.lines[targetPrLineIndex + 1] instanceof PrLine
                            && prFile.lines[targetPrLineIndex].number + 1 === prFile.lines[targetPrLineIndex + 1].number) {
                            targetPrLine = prFile.lines[targetPrLineIndex + 1];
                            targetPrLineIndex = targetPrLineIndex + 1;
                        }
                        else {
                            break;
                        }
                    }
                    while (!cachefound && cacheNeeded && targetPrLine.content.indexOf('return') < 0);

                    if (cacheNeeded && !cachefound) {
                        result.push(
                            {
                                line: prLine,
                                test: {
                                    category : ['performance'],
                                    title : 'Looks like cache is missing',
                                    description: 'Cache contexts, tags and max-age must always be set in renderrable arrays',
                                    links: [
                                        {
                                            title: "Cacheability of render arrays",
                                            link: "https://www.drupal.org/docs/8/api/render-api/cacheability-of-render-arrays",
                                        }
                                    ]
                                }
                            }
                        );
                    }
                }

            });
        }

        return result;
    }

    /**
     * Checks for Functions with multiple return points.
     */
    function pullRequestFileFunctionReturns(prFile) {
        let result = [];

        let applicableFileTypes = ['php', 'module', 'theme'];
        if (applicableFileTypes.find(function(item){
            return item === prFile.type;
        })) {
            prFile.lines.forEach(function (prLine, index) {
                // Find a function definition.
                const functionRegex = new RegExp('function\\s+.+\\(');
                const returnRegex = new RegExp('return.*;');
                if (functionRegex.test(prLine.content)) {
                    // Check number of returns;
                    let numOfReturns = 0;
                    for (let i = index + 1; typeof prFile.lines[i] !== 'undefined' ; i++) {
                        let nextPrLine = prFile.lines[i];

                        if (functionRegex.test(nextPrLine.content)) {
                            break;
                        }

                        if (returnRegex.test(nextPrLine.content)) {
                            numOfReturns++;
                        }
                    }

                    if (numOfReturns > 1) {
                        result.push(
                            {
                                line: prLine,
                                test: {
                                    category : ['performance'],
                                    title : 'Many Return Statements Are a Bad Idea in OOP',
                                    description: 'Looks like the function has more than one return statements',
                                    links: [
                                        {
                                            title: "Many Return Statements Are a Bad Idea in OOP",
                                            link: "https://www.yegor256.com/2015/08/18/multiple-return-statements-in-oop.html"
                                        }
                                    ]
                                }
                            }
                        );
                    }
                }
            });
        }

        return result;
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
                    if (typeof (assigned_variable) !== 'undefined') {
                        if (content.includes(assigned_variable) && content.includes("Drupal.checkPlain")) {
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
                            category: ['security'],
                            title: 'Use Drupal.checkPlain when showing some input text in JS',
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
                        } else {
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
                                category: ['best_practices'],
                                title: 'Avoid code duplicacy in switch cases',
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
                            category: ['functionality'],
                            title: 'Add name tags for subscriber services.',
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
                    const pattern = new RegExp("function [a-z_0-9]+\\(.+\\)", "gm");
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
                        if ((fnEndLine - fnStartLine) > 30) {
                            result.push(
                                {
                                    line: targetPrLine,
                                    test: {
                                        category: ['functionality'],
                                        title: 'Avoid long functions if possible.',
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

    /**
     * Check for internal URLs.
     */
    function pullRequestFileCheckInternalUrls(prFile) {
        let result = [];
        if (prFile.type === 'js') {
            prFile.lines.forEach(function (currentPrLine) {
                if (currentPrLine instanceof PrLine) {
                    const pattern = new RegExp("[a-z\\-0-9]+\\/");
                    let content = currentPrLine.content.trim();
                    if (pattern.test(content)) {
                        if (!content.includes("Drupal.url")) {
                            result.push(
                                {
                                    line: currentPrLine,
                                    test: {
                                        category: ['best_practices'],
                                        title: 'Use Drupal.url().',
                                        description: 'Create internal links/relative links using Drupal.url().',
                                        links: [
                                            {
                                                "title": "Drupal JS API.",
                                                "link": "http://read.theodoreb.net/drupal-jsapi/Drupal.html#.url"
                                            }
                                        ],
                                    },

                                }
                            );
                        }
                    }
                }
            })
        }

        return result;
    }

    /**
     * Check for raw string in t() file.
     *
     * @param prFile
     * @returns {Array}
     */
    function pullRequestFileCheckRawStringInT(prFile) {
        let result = [];
        if (prFile.type === 'php' || prFile.type === 'module' || prFile.type === 'theme') {
            prFile.lines.forEach(function (currentPrLine) {
                if (currentPrLine instanceof PrLine) {
                    const pattern = new RegExp("^t\\(.+\\)");
                    let content = currentPrLine.content.trim();
                    if (pattern.test(content)) {
                        let markup_pattern = new RegExp("\\<(\\/|)[a-z]+>");
                        if (markup_pattern.test(content))  {
                            result.push(
                                {
                                    line: currentPrLine,
                                    test: {
                                        category: ['best_practices'],
                                        title: 'Use raw string inside t() function.',
                                        description: 'Use raw string inside Drupal t() function. No markup should be used.',
                                        links: [
                                            {
                                                "title": "Drupal JS API.",
                                                "link": "http://read.theodoreb.net/drupal-jsapi/Drupal.html#.url"
                                            }
                                        ],
                                    },

                                }
                            );
                        }
                    }
                }
            })
        }

        return result;
    }

    /**
     * Check for alt and title in image tags.
     *
     * @param prFile
     * @returns {Array}
     */
    function pullRequestFileCheckImageTags(prFile) {
        let result = [];
        if (prFile.type === 'php' || prFile.type === 'module' || prFile.type === 'theme' || prFile.type === 'html.twig') {
            prFile.lines.forEach(function (currentPrLine) {
                if (currentPrLine instanceof PrLine) {
                    const pattern = new RegExp("\<img.+\>");
                    let content = currentPrLine.content;
                    if (pattern.test(content)) {
                        let match = pattern.exec(content)[0];
                        if (!match.includes("alt") || !match.includes("title")) {
                            result.push(
                                {
                                    line: currentPrLine,
                                    test: {
                                        category: ['best_practices'],
                                        title: 'Image accessibilty..',
                                        description: 'Use alt and title with image tags.',
                                        links: [
                                            {
                                                "title": "Images Concepts.",
                                                "link": "https://www.w3.org/WAI/tutorials/images/"
                                            }
                                        ],
                                    },

                                }
                            );
                        }
                    }
                }
            })
        }

        return result;
    }

    return {
        getAllFunctions: getAllFunctions,
        pullRequestFileModuleInfoPackageCheck: pullRequestFileModuleInfoPackageCheck,
        pullRequestFileModuleSrcPhpModuleName: pullRequestFileModuleSrcPhpModuleName,
        pullRequestFileRoutingParamsRegexCheck: pullRequestFileRoutingParamsRegexCheck,
        pullRequestFileCheckComments: pullRequestFileCheckComments,
        pullRequestFileTwigContentField: pullRequestFileTwigContentField,
        pullRequestFileRenderArrayCache: pullRequestFileRenderArrayCache,
        pullRequestFileFunctionReturns: pullRequestFileFunctionReturns,
        pullRequestFileCheckedSecuredInput: pullRequestFileCheckedSecuredInput,
        pullRequestFileCheckSwitchDuplicateCode: pullRequestFileCheckSwitchDuplicateCode,
        pullRequestFileCheckSubscriberServiceTags: pullRequestFileCheckSubscriberServiceTags,
        pullRequestFileCheckLongFunctions: pullRequestFileCheckLongFunctions,
        pullRequestFileCheckInternalUrls: pullRequestFileCheckInternalUrls,
        pullRequestFileCheckRawStringInT:pullRequestFileCheckRawStringInT,
        pullRequestFileCheckImageTags:pullRequestFileCheckImageTags
    };
}();
