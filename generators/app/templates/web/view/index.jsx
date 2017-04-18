import React from 'react';
import ReactDOM from 'react-dom';
import {Renderer, Store} from 'react-relay';
import App from './components/App';
import AppHomeRoute from './routes/AppHomeRoute';

ReactDOM.render(
  <Renderer
    environment={Store}
    Container={App}
    queryConfig={new AppHomeRoute()}
  />,
  document.getElementById('root'),
);
