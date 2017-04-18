// Create import alias' so we don't have to have tedious relative paths
// Now our inport/require statements can use require('Src/Folder/File');

const path = require('path');

module.exports = {
  alias: {
    View: path.resolve(__dirname, '../view/'),
  },
  extensions: ['.js', '.jsx'],
};
