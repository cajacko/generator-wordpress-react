// Enables sourcemaps

module.exports = (isProduction) => {
  const devtool = (() => {
    if (isProduction) {
      return 'source-map';
    }

    return 'cheap-module-eval-source-map';
  })();

  return devtool;
};
