var path = require('path');
var webpack = require('webpack');

module.exports = {
  watch: true,
  entry: [
    './js-es2015/entry.js'
  ],
  output: {
    path: path.resolve(__dirname, 'js'),
    filename: 'app.bundle.js'
  },
  module: {
    rules: [
      {
        test: /\.js$/,
        loader: 'babel-loader',
        query: {
          presets: ['es2015']
        }
      }
    ]
  },
  plugins: [
    // new webpack.ProvidePlugin({
    //   Velocity: "velocity-animate",
    // }),
  ],
  stats: {
    colors: true
  },
  devtool: 'source-map'
};
