import {Route, QL} from 'react-relay';

export default class extends Route {
  static queries = {
    viewer: () => QL`
      query {
        viewer
      }
    `,
  };
  static routeName = 'AppHomeRoute';
}
