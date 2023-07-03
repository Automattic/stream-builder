# StreamBuilder

Get started by reading the [StreamBuilder Beginner's Guide](docs/StreamBuilder-Beginners-Guide.md). We also have an example app in the `example/` folder to try!

## Installation

StreamBuilder expects PHP 7.4+.

Install StreamBuilder by using [Composer](https://getcomposer.org/) and running `composer require automattic/stream-builder` in your project.

## Contributing

If you want to change the StreamBuilder code, please:

1. Create an issue and describe your idea or desired change for discussion. Please mention `Automattic/stream-builders` for visibility and feedback. Please do this before proceeding!
2. Create a PR and add `Automattic/stream-builders` to the reviewers. Please write unit tests or update unit tests for your change.
3. Make sure unit tests and PHPCS are returning green (use `make test` to run the unit test suite and `make cs` to fix code style issues).
4. Add a description of your changes and testing instructions.
5. If you are making a breaking change, make it really clear! Changing a namespace from an existing class? That's a breaking change. Changing the way a cursor works? That's a breaking change.

If you are making a major refactor, changing interfaces, or any other breaking changes, we may not approve your PR. Tumblr relies on StreamBuilder for all our feeds, and we wouldn't want to change a lot of code unless there is a very good reason to do so.

Once approved, we will merge your PR and publish a new version. You can then update the version number in your `composer.json` file and deploy your changes.

All breaking changes will be published as a major version. If you are using a major version, you will need to update your code to use the new interfaces. We don't expect that to occur often.
