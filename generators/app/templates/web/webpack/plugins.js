const webpack = require('webpack');
const WebpackCleanupPlugin = require('webpack-cleanup-plugin');
const BundleAnalyzerPlugin = require('webpack-bundle-analyzer').BundleAnalyzerPlugin;
const ManifestPlugin = require('webpack-manifest-plugin');
const WebpackChunkHash = require('webpack-chunk-hash');
const HtmlWebpackPlugin = require('html-webpack-plugin');

module.exports = isProduction => [
  // Minify JS and create sourcemaps
  (() => {
    if (isProduction) {
      return new webpack.optimize.UglifyJsPlugin({
        sourceMap: true,
        beautify: false,
        mangle: {
          screw_ie8: true,
          keep_fnames: true
        },
        compress: {
          screw_ie8: true,
          warnings: false
        },
        comments: false
      });
    }

    return () => {};
  })(),

  // Replace any production checks
  (() => {
    if (isProduction) {
      return new webpack.DefinePlugin({
        'process.env.NODE_ENV': JSON.stringify('production')
      });
    }

    return () => {};
  })(),

  // Support plugins that are not webpack 2 enabled yet
  (() => {
    if (isProduction) {
      return new webpack.LoaderOptionsPlugin({
        minimize: true,
        debug: false
      });
    }

    return () => {};
  })(),

  // Bundle analyzer lets you observe what each webpack budle is made up of
  // Generates a report.html file in the output folder
  (() => {
    if (isProduction) {
      return new BundleAnalyzerPlugin({
        analyzerMode: 'static',
        openAnalyzer: false
      });
    }

    return () => {};
  })(),

  // Remove old budles before creating new ones
  new WebpackCleanupPlugin(),

  // Create a static html page for testing
  new HtmlWebpackPlugin(),

  // Split all node modules into seperate bundle
  new webpack.optimize.CommonsChunkPlugin({
    name: 'vendor',
    minChunks: (module) => {
      if (!module.context) {
        return false;
      }

      if (module.context.indexOf('node_modules') !== -1) {
        return true;
      }

      return false;
    }
  }),

  // Split out the manifest
  // TODO: This doesn't seem to change often, can this be included in
  // vendor?
  new webpack.optimize.CommonsChunkPlugin({
    names: ['manifest'] // Specify the common bundle's name.
  }),

  // Improves logging in the console
  new webpack.HashedModuleIdsPlugin(),

  // Better hashing than the standard
  new WebpackChunkHash(),

  // Capture the manifest in a json file
  // This outputs a json file with each easy bundle name mapped to its hash
  // name. Then our templating engine can require the correct file
  // Only in production though, as no hashes are used otherwise
  (() => {
    if (isProduction) {
      return new ManifestPlugin();
    }

    return () => {};
  })()
];
