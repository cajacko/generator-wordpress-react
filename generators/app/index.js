/* @flow */

const Generator = require('yeoman-generator');
const path = require('path');
const checkOutOfDatePackages = require('check-out-of-date-packages');

module.exports = class extends Generator {
  initializing() {
    const cwd = path.join(__dirname, '../../');
    return checkOutOfDatePackages(cwd, 'Charlie Jackson');
  }

  writing() {
    this.fs.copyTpl(
      this.templatePath('./**/*'),
      this.destinationPath('./'),
      { variable: 'value' }
    );

    this.fs.copyTpl(
      this.templatePath('./**/.*'),
      this.destinationPath('./'),
      { variable: 'value' }
    );
  }

  readme() {
    this.composeWith(require.resolve('generator-readme-cj/generators/app'), {
      tag: 'generator-generator-wordpress-react',
      markdown: this.fs.read(this.templatePath('README.md'))
    });
  }
};
