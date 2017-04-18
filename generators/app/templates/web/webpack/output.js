const path = require('path');

module.exports = (isProduction) => {
  const output = {
    // Out put with cache buster names in production
    filename: (() => {
      if (isProduction) {
        return '[chunkhash].[name].js';
      }

      // No hash for dev as it adds to compliation time
      return '[name].js';
    })(),

    // Change build path for production and dev, makes it more obvious when
    // production build needs to happen
    path: (() => {
      if (isProduction) {
        return path.resolve(__dirname, '../dist/prod');
      }

      return path.resolve(__dirname, '../dist/dev');
    })()
  };

  return output;
};
