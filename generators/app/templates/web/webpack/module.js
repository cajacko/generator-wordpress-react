// Babel is needed to get rid of flow type annotations

module.exports = {
  loaders: [
    { test: /\.js$/, loader: 'babel-loader', exclude: /node_modules/ },
    { test: /\.jsx$/, loader: 'babel-loader', exclude: /node_modules/ },
  ],
};
