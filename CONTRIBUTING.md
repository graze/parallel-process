# Contributing

Contributions are **welcome**!

We accept contributions via Pull Requests on [Github](https://github.com/graze/parallel-process). We also recommend reading [How to write the perfect Pull Request](https://github.com/blog/1943-how-to-write-the-perfect-pull-request) which has some great tips and advice.

## Reporting an Issue

Please report issues via the issue tracker on GitHub. For security-related issues, please email the maintainer directly.

## Pull Requests

Contributions are accepted via Pull Requests. In order for your Pull Request to be merged, please ensure it meets
the following criteria:

- **PSR-2 & PSR-4 Coding Standards**.
- **Tests** - your contribution will not be merged unless it has tests covering the changes.
- **Documentation** - please ensure that README.md and any other documentation relevant to your change is up-to-date.
- **Description** - please provide a description of your pull request that details the changes you've made, why you've
made them including any relevant information or justifications that will aid the person reviewing you changes.

## Development Environment

A Dockerfile is included in this repository for development. All make commands use the docker container to run the code.
An initial setup will need to be run to install the environment:

```shell
$ make build
```

A complete list of commands can be found by running: `$ make help`

## Running Tests

You can run all of the test suites in the project using:

```shell
$ make test
```

Or run individual suites using:

```shell
$ make test-unit
$ make test-integration
$ make test-matrix
```

You can get a coverage report in text, html and clover XML formats:

```shell
$ make test-coverage
$ make test-coverage-html
$ make test-coverage-clover
```
