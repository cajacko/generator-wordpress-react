const webpack = require('webpack');
const options = require('./webpack.config');
const bs = require('browser-sync').create();
const path = require('path');

const args = process.argv;
let production = false;

const skipArg = '--env.production';
const index = args.indexOf(skipArg);

if (index > -1) {
  production = true;
}

const compiler = webpack(options({ production }));

bs.init({
  server: compiler.outputPath,
}, () => {
  compiler.watch({}, (err, stats) => {
    let reload = true;

    if (err) {
      // eslint-disable-next-line
      console.error(err.stack || err);

      if (err.details) {
        // eslint-disable-next-line
        console.error(err.details);
        reload = false;
      }

      return;
    }

    const info = stats.toJson();

    if (stats.hasErrors()) {
      // eslint-disable-next-line
      console.error(info.errors);
      reload = false;
    }

    if (stats.hasWarnings()) {
      // eslint-disable-next-line
      console.warn(info.warnings);
      reload = false;
    }

    // eslint-disable-next-line
    console.log(stats.toString({
      chunks: false,
      colors: true,
    }));

    if (reload) {
      bs.reload();
    }
  });
});

bs.watch(path.resolve(compiler.outputPath, 'index.html')).on('change', bs.reload);
