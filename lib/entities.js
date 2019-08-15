/**
 * Line represents line of a file in a Pull Request.
 */
class PrLine {
    constructor (number, content, file, domElement) {
        this.number = number;
        this.content = content;
        this.file = file;
        this.domElement = domElement;
    }
}

/**
 * File represent file in a Pull Request.
 */
class PrFile {
    constructor (type, name, path, lines = []) {
        this.type = type;
        this.name = name;
        this.path = path;
        this.lines = lines;
    }
}

/**
 * PullRequest represents the PR itself and has files in it.
 */
class  PullRequest {
    constructor (files = []) {
        this.files = files;
    }
}
