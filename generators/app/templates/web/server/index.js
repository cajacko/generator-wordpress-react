/* @flow */

import express from 'express';
import { buildSchema } from 'graphql';
import graphqlHTTP from 'express-graphql';

const app = express();

app.listen(5000, () => {
  console.log('Example app listening on port 5000!');
});

app.get('/', (req, res) => {
  console.log('ping');
  res.send('Hello World!');
});

// Construct a schema, using GraphQL schema language
const schema = buildSchema(`
  type Query {
    hello: String
  }
`);

// The root provides a resolver function for each API endpoint
const root = {
  hello: () => {
    return 'Hello world!';
  },
};

app.use('/graphql', graphqlHTTP({
  schema,
  rootValue: root,
  graphiql: true, // Turn off for prod
}));
