
const testFolder = './test_cases/';
const fs = require('fs');

let test_cases = {};
let master_test_cases = [];

let getTestCases = function(testFolder) {
  return new Promise((resolve, reject) => {
    fs.readdir(testFolder, (err, files) => {
      if (err) {
        reject(err);
      }
      else {
        files.forEach(file => {
          getSingleTestCase(testFolder, file)
            .then(test_cases => console.log(test_cases))
            .catch(err => console.log(err));
        });
      }
    });
  })
};

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

getTestCases(testFolder)
  .then(test_cases => console.log(test_cases));

// File should be generated which should contain all test cases in serialized format.
