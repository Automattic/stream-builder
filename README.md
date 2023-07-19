# StreamBuilder

StreamBuilder is Tumblr's custom framework we use to power the dashboard and most of the feeds on the platform. The primary architecture centers around “streams” of content. In our implementation, those streams can be posts from a blog, a list of blogs you’re following, posts using a specific tag, or posts relating to a search. These are separate kinds of streams, which can be mixed together, filtered based on certain criteria, ranked for relevancy or engagement likelihood, and more.

On the Tumblr dashboard today you can see how there are posts from blogs you follow, mixed with posts from tags you follow, mixed with blog recommendations. Each of those is a separate stream, with its own logic, but sharing this same framework. We inject those recommendations at certain intervals, filter posts based on who you’re blocking, and rank the posts for relevancy if you have the “Best stuff first” setting enabled. Those are all examples of the functionality StreamBuilder affords for us.

What's included in this repo:

- The full framework library of code that we use today, on Tumblr, to power almost every feed of content you see on the platform.
- A YAML syntax for composing streams of content, and how to filter, inject, and rank them.
- Abstractions for programmatically composing, filtering, ranking, injecting, and debugging streams.
- Abstractions for composing streams together—such as with carousels, for streams-within-streams.
- An abstraction for cursor-based pagination for complex stream templates.
- Unit tests covering the public interface for the library and most of the underlying code.

We're still working to add more documentation and examples. Check out [the announcement post on the Tumblr Engineering blog](https://engineering.tumblr.com/post/722102563011493888/streambuilder-our-open-source-framework-for) for more info.

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

## Creating a new release

**Only applies to Automatticians**

1. Merge your change via a PR in this repository; see the Contributing section.
2. Head over to https://github.com/Automattic/stream-builder/releases and click "Draft a new release".
3. Select "Choose a tag" and create a new version if you haven't already. Not a breaking change? Increment the minor version by 0.0.1. Breaking change? Increment the major version by 0.1.0.
4. Make sure you are targeting the `main` branch.
5. Click "generate release notes". This will generate a list of all the PRs merged since the last release.
6. Hit the green "Publish release" button.

Are you a Tumblr employee? You can now update the version number in any internal `composer.json` files and deploy your changes.

Release often! We want to make sure we are shipping new features and bug fixes as often as possible. We don't want to get into a situation where we are shipping a lot of changes at once.
