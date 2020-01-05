const testFolder = './test_cases/regex/';
const fs = require('fs');

let test_cases = {};

let getSingleTestCase = function(testFolder, file) {
    return new Promise((resolve, reject) => {
        fs.readFile(testFolder + file, 'utf8', function (err, data) {
            if (err) reject(err);
            else {
                obj = JSON.parse(data);
                obj.file_types.forEach(file_type => {
                    if (test_cases[file_type] !== undefined) {
                        let test_cases_length = Object.keys(test_cases[file_type]).length;
                        test_cases[file_type][test_cases_length] = obj;
                    } else {
                        test_cases[file_type] = {0: obj};
                    }
                });
                resolve(test_cases);
            }
        });
    })
};

files = fs.readdirSync(testFolder);

let promises = files.map(file => {
    return getSingleTestCase(testFolder, file)
        .then(result => {
            return result;
        })
});

Promise.all(promises)
    .then(response =>
        fs.writeFile('core.regex_tests.ser', JSON.stringify(response.pop(), null, 2), (err) => {
            if (err) throw err;
            console.log('The file has been saved!');
        })
    );
