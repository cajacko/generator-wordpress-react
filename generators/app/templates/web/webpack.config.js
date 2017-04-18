const plugins = require('./webpack/plugins');
const output = require('./webpack/output');
const entry = require('./webpack/entry');
const devtool = require('./webpack/devtool');
const webpackModule = require('./webpack/module');
const resolve = require('./webpack/resolve');

module.exports = (env = {}) => {
  let isProduction;

  if (env.production) {
    isProduction = true;
  } else {
    isProduction = false;
  }

  return {
    entry,
    resolve,
    module: webpackModule,
    output: output(isProduction),
    devtool: devtool(isProduction),
    plugins: plugins(isProduction),
  };
};
