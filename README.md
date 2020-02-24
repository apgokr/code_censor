# Code Censor

## What is Code Censor?
Code Censor is a chrome extension to semi-automate PR (Pull Request) Reviews (specific to Drupal 8 and Github, for now) to ensure AN INFORMED DECISION is made by a human at every (seeming) violation of best practices.

The tool intends to make it easy for the reviewer to find violations in code through visual highlighting, along with prompts on how to remediate the code, and a way to quickly insert suggestions as a comment on the PR. 

## How do I enable Code Censor?
1. Download and unzip the latest stable extension from https://github.com/apgokr/code_censor/releases
2. In Chrome browser, go to chrome://extensions/.
3. Enable Developer mode by ticking the checkbox in the upper-right corner.
4. Click on the "Load unpacked extension..." button.
5. Select the directory containing your unpacked extension.

## What would Code Censor do on my Pull Request page?
1. Highlight code violations for your review
2. Allow inserting a remediation suggestion quickly as a Github comment by double clicking on the highlighted violation
![alt text](https://github.com/apgokr/code_censor/raw/develop/code-censor.gif "Demo")

## What type of reviews are performed?
Code Censor as of now is especially designed and developed for Drupal 8 projects hosted on github and flags issues related to 
#### Security
Cases like raw twig filter, route access and param validations, proper usage of request data and similar have been successfully integrated in Code Censor.
#### Performance
Checks around Drupal module hooks, preprocessors, caching and entity loading strategies, PHP code practices that impacts the performance directly.
#### Best Practices
Drupal being a flexible, versatile CMS, follows certain best practices which are quite generic like inclusion of alt attribute with img tag, avoiding hard coded api URLs, apt php filenames, avoiding multiple returns and ensuring code readability.
#### Functionality
Checks like proper usage of event system, subscriber services, access validators, routing which are specific to Drupal have been addressed.


## What does Code Censor NOT do?
Code Censor does not perform the job of code-sniffer tools like PHPCS. Like syntactical formatting or similar. 
It only addresses the issues that you watch for while doing code review on PRs. 
*Like - hey, is this function too long? is dependency injection being used the right way here? is this a hard coded URL that should sit in config instead? are there too many returns in the function? should you use a $user object instead of $account object here? isn't Drupal::entityManager() method deprecated? is this image field markup missing alt and title?*


## How to create more tests?
Raise a Pull Request on this repository. 
Sample tests - 
- Regex based (Line level) - https://github.com/apgokr/code_censor/blob/develop/lib/test_cases/regex/17.json
- File Level - https://github.com/apgokr/code_censor/blob/develop/lib/test_cases/function/pull_request_file.js#L645
- PR Level - https://github.com/apgokr/code_censor/blob/develop/lib/test_cases/function/pull_request.js#L94

## How can I add my own tests to Code Sniffer?
Once your test is created, you can modify your chrome extension locally to include new tests. 

## How can I contribute the tests to Code Sniffer?
You can raise a Pull Request against this repo. 
